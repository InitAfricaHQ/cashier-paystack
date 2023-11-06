<?php

namespace InitAfricaHQ\Cashier\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use InitAfricaHQ\Cashier\Customer;
use InitAfricaHQ\Cashier\Exceptions\InvalidCustomer;
use InitAfricaHQ\Cashier\Paystack;

trait ManagesCustomer
{
    /**
     * Create a Paystack customer for the given model.
     *
     * @param  string  $token
     *
     * @throws Exception
     */
    public function createAsCustomer(array $options = [])
    {
        $options = array_key_exists('email', $options)
            ? $options
            : array_merge($options, ['email' => $this->paystackEmail()]);

        $response = Paystack::createCustomer($options);

        if (! $response['status']) {
            throw new Exception('Unable to create Paystack customer: '.$response['message']);
        }

        $this->customer()->create([
            'paystack_id' => $response['data']['id'],
            'paystack_code' => $response['data']['customer_code'],
        ]);

        return $response['data'];
    }

    /**
     * Get the billable's email address to associate with Paystack.
     */
    public function paystackEmail(): ?string
    {
        return $this->email ?? null;
    }

    /**
     * Get the Paystack customer for the model.
     *
     * @return $customer
     */
    public function asPaystackCustomer()
    {
        $response = Paystack::fetchCustomer($this->customer->paystack_code);
        $customer = $response->json('data');

        return $customer;
    }

    /**
     * Get the customer related to the billable model.
     */
    public function customer(): MorphOne
    {
        return $this->morphOne(Customer::class, 'billable');
    }

    /**
     * Determine if the billable is already a Lemon Squeezy customer and throw an exception if not.
     *
     * @throws InvalidCustomer
     */
    protected function assertCustomerExists(): void
    {
        if (is_null($this->customer) || is_null($this->customer->paystack_id)) {
            throw InvalidCustomer::notYetCreated($this);
        }
    }
}
