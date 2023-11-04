<?php

namespace InitAfricaHQ\Cashier\Concerns;

use Exception;
use InitAfricaHQ\Cashier\Cashier;
use InitAfricaHQ\Cashier\Paystack;
use InitAfricaHQ\Cashier\ReferenceGenerator;

trait ManagesPayments
{
    /**
     * Make a "one off" or "recurring" charge on the customer
     * for the given amount or plan respectively
     *
     * @throws \Exception
     */
    public function charge($amount, array $options = [])
    {
        $options = array_merge([
            'currency' => $this->preferredCurrency(),
            'reference' => ReferenceGenerator::generate(),
        ], $options);

        $options['email'] = $this->email;
        $options['amount'] = intval($amount);

        if (array_key_exists('authorization_code', $options)) {
            $response = Paystack::chargeAuthorization($options);
        } elseif (array_key_exists('card', $options) || array_key_exists('bank', $options)) {
            $response = Paystack::charge($options);
        } else {
            $response = Paystack::makePaymentRequest($options);
        }

        if (! $response['status']) {
            throw new Exception('Paystack was unable to perform a charge: '.$response['message']);
        }

        return $response;
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string  $charge
     * @return $response
     *
     * @throws \InvalidArgumentException
     */
    public function refund($transaction, array $options = [])
    {
        $options['transaction'] = $transaction;

        $response = Paystack::refund($options);

        return $response;
    }

    /**
     * Get the Paystack supported currency used by the entity.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return Cashier::usesCurrency();
    }
}
