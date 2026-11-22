<?php

namespace InitAfricaHQ\Cashier\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\RedirectResponse;
use InitAfricaHQ\Cashier\Cashier;
use InitAfricaHQ\Cashier\Paystack;
use InitAfricaHQ\Cashier\Subscription;
use InitAfricaHQ\Cashier\SubscriptionBuilder;

trait ManagesSubscriptions
{
    /**
     * Get all of the subscriptions for the billable.
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Cashier::$subscriptionModel, 'billable')->orderByDesc('created_at');
    }

    /**
     * Get a subscription instance by type.
     */
    public function subscription(string $type = Subscription::DEFAULT_TYPE): ?Subscription
    {
        return $this->subscriptions->where('type', $type)->first();
    }

    /**
     * Determine if the billable's trial has ended.
     */
    public function hasExpiredTrial(string $type = Subscription::DEFAULT_TYPE, string $plan = null): bool
    {
        if (func_num_args() === 0 && $this->hasExpiredGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->hasExpiredTrial()) {
            return false;
        }

        return $plan ? $subscription->hasPlan($plan) : true;
    }

    /**
     * Determine if the billable is on a "generic" trial at the model level.
     */
    public function onGenericTrial(): bool
    {
        if (is_null($this->customer)) {
            return false;
        }

        return $this->customer->onGenericTrial();
    }

    /**
     * Determine if the billable's "generic" trial at the model level has expired.
     */
    public function hasExpiredGenericTrial(): bool
    {
        if (is_null($this->customer)) {
            return false;
        }

        return $this->customer->hasExpiredGenericTrial();
    }

    /**
     * Get the ending date of the trial.
     */
    public function trialEndsAt(string $type = Subscription::DEFAULT_TYPE): ?Carbon
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->trial_ends_at;
        }

        return $this->customer->trial_ends_at;
    }

    /**
     * Begin creating a new subscription.
     *
     * @param  string  $subscription
     * @param  string  $plan
     */
    public function newSubscription($subscription, $plan): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $subscription, $plan);
    }

    /**
     * Determine if the billable is on trial.
     *
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrial(string $subscription = Subscription::DEFAULT_TYPE, $plan = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($subscription);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return $plan ? $subscription->hasPlan($plan) : true;
    }

    /**
     * Determine if the billable has a valid subscription.
     *
     * @param  string|null  $plan
     * @return bool
     */
    public function subscribed(string $subscription = Subscription::DEFAULT_TYPE, $plan = null)
    {
        $subscription = $this->subscription($subscription);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $plan ? $subscription->hasPlan($plan) : true;
    }

    /**
     * Determine if the model is actively subscribed to one of the given plans.
     *
     * @param  array|string  $plans
     * @param  string  $subscription
     * @return bool
     */
    public function subscribedToPlan($plans, $subscription = Subscription::DEFAULT_TYPE)
    {
        $subscription = $this->subscription($subscription);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $plans as $plan) {
            if ($subscription->hasPlan($plan)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the entity is on the given plan.
     *
     * @param  string  $plan
     * @return bool
     */
    public function onPlan($plan)
    {
        return ! is_null($this->subscriptions->first(function ($subscription) use ($plan) {
            return $subscription->paystack_plan === $plan;
        }));
    }

    /**
     * Get the customer portal url for this billable.
     */
    public function subscriptionPortalUrl($subscription = 'default'): string
    {
        $this->assertCustomerExists();

        $response = Paystack::api("subscription/{$this->subscription()->paystack_code}/manage/link", 'GET');

        return $response['data']['link'];
    }

    /**
     * Generate a redirect response to the billable's customer portal.
     */
    public function redirectToSubscriptionPortal(): RedirectResponse
    {
        return new RedirectResponse($this->subscriptionPortalUrl());
    }
}
