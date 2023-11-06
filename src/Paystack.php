<?php

namespace InitAfricaHQ\Cashier;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InitAfricaHQ\Cashier\Exceptions\PaystackApiError;

class Paystack
{
    /**
     * Perform a Paystack API call.
     *
     * @throws Exception
     * @throws PaystackApiError
     */
    public static function api(string $uri, string $method, array $payload = []): Response
    {
        if (empty($secretKey = Config::get('cashier-paystack.secret_key'))) {
            throw new Exception('Paystack API key not set.');
        }

        $response = Http::withToken($secretKey)
            ->timeout(20)
            ->withoutVerifying()
            ->accept('application/json')
            ->contentType('application/json')
            ->baseUrl('https://api.paystack.co')
            ->$method($uri, $payload);

        if ($response->failed()) {
            throw new PaystackApiError($response['message'], (int) $response['status']);
        }

        return $response;
    }

    public static function charge($data)
    {
        return static::api('/charge', 'post', $data);
    }

    public static function makePaymentRequest($data)
    {
        return static::api('/transaction/initialize', 'post', $data);
    }

    public static function chargeAuthorization($data)
    {
        return static::api('/charge_authorization', 'post', $data);
    }

    public static function refund($data)
    {
        return static::api('/refund', 'post', $data);
    }

    /**
     * @deprecated
     * https://paystack.com/docs/changelog/api/#march-2023
     */
    public static function checkAuthorization($data)
    {
        return static::api('/check_authorization', 'post', $data);
    }

    public static function deactivateAuthorization($code)
    {
        $data = ['authorization_code' => $code];

        return static::api('/deactivate_authorization', 'post', $data);
    }

    public static function fetchSubscription($code)
    {
        return static::api("/subscription/{$code}", 'get', []);
    }

    public static function createSubscription($data)
    {
        return static::api('/subscription', 'post', $data);
    }

    public static function createCustomer($data)
    {
        return static::api('/customer', 'post', $data);
    }

    /**
     * Fetch a customer based on id or code
     */
    public static function fetchCustomer($customerId)
    {
        return static::api("/customer/{$customerId}", 'get', []);
    }

    public static function customerSubscriptions($customerId)
    {
        $data = ['customer' => $customerId];

        return static::api('/subscription', 'get', $data);
    }

    /**
     * Enable a subscription using the subscription code and token
     *
     * @return array
     */
    public static function enableSubscription($data)
    {
        return static::api('/subscription/enable', 'post', $data);
    }

    public static function disableSubscription($data)
    {
        return static::api('/subscription/disable', 'post', $data);
    }

    public static function createInvoice($data)
    {
        return static::api('/paymentrequest', 'post', $data);
    }

    public static function fetchInvoices($data)
    {
        return static::api('/paymentrequest', 'get', $data);
    }

    public static function findInvoice($invoiceId)
    {
        return static::api("/paymentrequest/{$invoiceId}", 'get', []);
    }

    public static function updateInvoice($invoiceId, $data)
    {
        return static::api("/paymentrequest/{$invoiceId}", 'PUT', $data);
    }

    public static function verifyInvoice($invoiceCode)
    {
        return static::api("/paymentrequest/verify/{$invoiceCode}", 'get', []);
    }

    public static function notifyInvoice($invoiceId)
    {
        return static::api("/paymentrequest/notify/{$invoiceId}", 'post', []);
    }

    public static function finalizeInvoice($invoiceId)
    {
        return static::api("/paymentrequest/finalize/{$invoiceId}", 'post', []);
    }

    public static function archiveInvoice($invoiceId)
    {
        return static::api("/paymentrequest/archive/{$invoiceId}", 'post', []);
    }

    public static function createPlan($data)
    {
        return static::api('/plan', 'post', $data);
    }

    /**
     * Get all the plans that you have on Paystack
     */
    public static function fetchPlans()
    {
        return static::api('/plan', 'get', []);
    }

    /**
     * Get all the transactions that have happened overtime
     */
    public function fetchTransactions()
    {
        return static::api('/transaction', 'get', []);
    }
}
