<?php

namespace InitAfricaHQ\Cashier;

use Carbon\Carbon;
use Exception;

class SubscriptionBuilder
{
    /**
     * The model that is subscribing.
     *
     * @var \InitAfricaHQ\Cashier\Billable
     */
    protected $billable;

    /**
     * The type of the subscription.
     *
     * @var string
     */
    protected $type;

    /**
     * The paystack code of the plan being subscribed to.
     *
     * @var string
     */
    protected $plan;

    /**
     * The number of trial days to apply to the subscription.
     *
     * @var int|null
     */
    protected $trialDays;

    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * The coupon code being applied to the customer.
     *
     * @var string|null
     */
    protected $coupon;

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $billable
     * @param  string  $type
     * @param  string  $plan
     * @return void
     */
    public function __construct($billable, $type, $plan)
    {
        $this->type = $type;
        $this->plan = $plan;
        $this->billable = $billable;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  int  $trialDays
     * @return $this
     */
    public function trialDays($trialDays)
    {
        $this->trialDays = $trialDays;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->skipTrial = true;

        return $this;
    }

    /**
     * Add a new Paystack subscription to the model.
     *
     * @return \InitAfricaHQ\Cashier\Subscription
     *
     * @throws \Exception
     */
    public function save(array $options = [])
    {
        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialDays ? Carbon::now()->addDays($this->trialDays) : null;
        }

        return $this->billable->subscriptions()->create([
            'type' => $this->type,
            'paystack_id' => $options['id'],
            'paystack_code' => $options['subscription_code'],
            'paystack_plan' => $this->plan,
            'quantity' => 1,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);
    }

    /**
     * Charge for a Paystack subscription.
     *
     * @return \InitAfricaHQ\Cashier\Subscription
     *
     * @throws \Exception
     */
    public function charge(array $options = [])
    {
        $optionsMetadata = json_decode($options['metadata'] ?? '') ?? [];
        unset($options['metadata']);

        $options = array_merge([
            'plan' => $this->plan,
            'metadata' => json_encode(array_merge([
                'billable_id' => $this->billable->getKey(),
                'billable_type' => $this->billable->getMorphClass(),
            ], $optionsMetadata)),
        ], $options);

        return $this->billable->charge(100, $options);
    }

    /**
     * Create a new Paystack subscription.
     *
     * @param  string|null  $token
     * @return \InitAfricaHQ\Cashier\Subscription
     *
     * @throws \Exception
     */
    public function create($token = null, array $options = [])
    {
        $payload = $this->getSubscriptionPayload(
            $this->getPaystackCustomer(), $options
        );

        // Set the desired authorization you wish to use for this subscription here.
        // If this is not supplied, the customer's most recent authorization would be used
        if (isset($token)) {
            $payload['authorization'] = $token;
        }

        $subscription = Paystack::createSubscription($payload);

        if (! $subscription['status']) {
            throw new Exception('Paystack failed to create subscription: '.$subscription['message']);
        }

        return $this->save($subscription['data']);
    }

    /**
     * Get the subscription payload data for Paystack.
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function getSubscriptionPayload($customer, array $options = [])
    {
        if ($this->skipTrial) {
            $startDate = Carbon::now();
        } else {
            $startDate = $this->trialDays ? Carbon::now()->addDays($this->trialDays) : Carbon::now();
        }

        return array_merge([
            'customer' => $customer['customer_code'], // customer email or code
            'plan' => $this->plan,
            'start_date' => $startDate->format('c'),
        ], $options);
    }

    /**
     * Get the Paystack customer instance for the current user and token.
     *
     * @return $customer
     */
    protected function getPaystackCustomer(array $options = [])
    {
        if (! $this->billable->customer?->paystack_id) {
            $customer = $this->billable->createAsCustomer($options);
        } else {
            $customer = $this->billable->asPaystackCustomer();
        }

        return $customer;
    }
}
