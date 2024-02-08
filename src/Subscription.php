<?php

namespace InitAfricaHQ\Cashier;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InitAfricaHQ\Cashier\Database\Factories\SubscriptionFactory;
use LogicException;

class Subscription extends Model
{
    const DEFAULT_TYPE = 'default';
    const FREE_TRIAL = 'trial';

    const ACTIVE = 'active';

    const INACTIVE = 'inactive';

    protected $table = 'bunce_subscription_plans';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $casts = [
        'end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->billable();
    }

    /**
     * Get the billable model related to the subscription.
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid()
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active()
    {
        return is_null($this->end_date) || $this->onGracePeriod();
    }

    /**
     * Filter query by active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->where(function ($query) {
            $query->whereNull('end_date')
                ->orWhere(function ($query) {
                    $query->onGracePeriod();
                });
        });
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     *
     * @return bool
     */
    public function recurring()
    {
        return ! $this->onTrial() && ! $this->cancelled();
    }

    /**
     * Filter query by recurring.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeRecurring($query)
    {
        $query->notOnTrial()->notCancelled();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return ($this->status == self::INACTIVE);
    }

    /**
     * Filter query by cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeCancelled($query)
    {
        $query->where('status', self::INACTIVE);
    }

    /**
     * Filter query by not cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotCancelled($query)
    {
        $query->whereNot('status', self::ACTIVE);
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended()
    {
        return $this->cancelled() && ! $this->onGracePeriod();
    }

    /**
     * Filter query by ended.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeEnded($query)
    {
        $query->cancelled()->notOnGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        return $this->end_date && $this->end_date->isFuture();
    }

    /**
     * Filter query by on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnTrial($query)
    {
        $query->where('status', self::FREE_TRIAL)
            ->where('end_date', '>', Carbon::now());
    }

    /**
     * Filter query by not on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnTrial($query)
    {
        $query->whereNot('status', self::FREE_TRIAL)->orWhere('end_date', '<=', Carbon::now());
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        return $this->end_date && $this->end_date->isFuture();
    }

    /**
     * Filter query by on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnGracePeriod($query)
    {
        $query->whereNotNull('end_date')->where('end_date', '>', Carbon::now()->addDays(5));
    }

    /**
     * Filter query by not on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnGracePeriod($query)
    {
        $query->whereNull('end_date')->orWhere('end_date', '<=', Carbon::now());
    }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->end_date = null;

        return $this;
    }

    /**
     * Determine if the subscription is on a specific plan.
     * @todo change to subscription_plan_id
     */
    public function hasPlan(string $planId): bool
    {
        return $this->paystack_plan === $planId;
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
        $subscription = $this->asPaystackSubscription();

        Paystack::disableSubscription([
            'token' => $subscription['email_token'],
            'code' => $subscription['subscription_code'],
        ]);

        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // period and make that the end of the grace period for this current user.
        if ($this->onTrial()) {
//            @todo
        } else {
            $this->end_date = Carbon::parse(
                $subscription['next_payment_date']
            );
            $this->status = self::INACTIVE;
        }

        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $this->cancel();
        $this->markAsCancelled();

        return $this;
    }

    /**
     * Mark the subscription as cancelled.
     *
     * @return void
     */
    public function markAsCancelled()
    {
        $this->fill(['end_date' => Carbon::now(),'status' => self::INACTIVE])->save();
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     *
     * @throws \LogicException
     */
    public function resume()
    {
        $subscription = $this->asPaystackSubscription();

        // To resume the subscription we need to enable the Paystack
        // subscription. Then Paystack will resume this subscription
        // where we left off.
        Paystack::enableSubscription([
            'token' => $subscription['email_token'],
            'code' => $subscription['subscription_code'],
        ]);

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "cancelled". Then we will save this record in the database.
        $this->fill(['end_date' => null,'status' => self::ACTIVE])->save();

        return $this;
    }

    /**
     * Swap the subscription to a new plan.
     */
    public function swap(string $plan, array $attributes = []): self
    {
        $subscription = $this->asPaystackSubscription();

        if ($this->cancelled() === false) {
            $this->cancel();
        }

        Paystack::createSubscription(array_merge([
            'customer' => $subscription['customer']['customer_code'],
            'plan' => $plan,
            'start_date' => $subscription['next_payment_date'],
        ], $attributes));

        return $this;
    }

    /**
     * Get the subscription as a Paystack subscription object.
     *
     * @throws \LogicException
     */
    public function asPaystackSubscription()
    {
        $response = Paystack::fetchSubscription($this->subscription_code);
        $subscription = $response->json('data');

        if (! $subscription || empty($subscription)) {
            throw new Exception('Subscription not found.');
        }

        return $subscription;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }
}
