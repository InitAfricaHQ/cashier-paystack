<?php

namespace InitAfricaHQ\Cashier;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Illuminate\Http\Client\PendingRequest;
use Exception;
use Illuminate\Http\Client\ConnectionException;

class PaystackService
{
    /**
     * Paystack API url
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Issue Secret Key from your Paystack Dashboard
     *
     * @var string
     */
    protected $secretKey;

    /**
     * Instance of Client
     *
     * @var Client
     */
    protected $client;

    /**
     *  Response from requests made to Paystack
     *
     * @var Response
     */
    protected $response;

    /**
     * Paystack API base Url
     *
     * @var string
     */
    public function __construct()
    {
        $this->setKey();
        $this->setBaseUrl();
        $this->setRequestOptions();
    }

    /**
     * Get Base Url from Paystack config file
     */
    public function setBaseUrl()
    {
        $this->baseUrl = Config::get('paystack.paymentUrl');
    }

    /**
     * Get secret key from Paystack config file
     */
    public function setKey()
    {
        $this->secretKey = Config::get('paystack.secretKey');
    }

    /**
     * Set options for making the Client request
     */
    private function setRequestOptions()
    {
        $this->client = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(20)
            ->baseUrl($this->baseUrl)
            ->withoutVerifying();
    }

    /**
     * @param  string  $relativeUrl
     * @param  string  $method
     * @param  array  $body
     * @return Paystack
     *
     * @throws InvalidArgumentException
     */
    private function setHttpResponse($relativeUrl, $method, $body = [])
    {
        if (is_null($method)) {
            throw new InvalidArgumentException('Empty method not allowed');
        }

        $this->response = $this->client->{strtolower($method)}(
            $this->baseUrl.$relativeUrl,
            $body
        );

        return $this;
    }

    /**
     * Get the whole response from a get operation
     *
     * @return array
     */
    private function getResponse()
    {
        return $this->response->json();
    }

    /**
     * Get the data response from a get operation
     *
     * @return array
     */
    private function getData()
    {
        return $this->getResponse()['data'];
    }

    public static function charge($data)
    {
        return (new self)->setHttpResponse('/charge', 'POST', $data)->getResponse();
    }

    public static function makePaymentRequest($data)
    {
        return (new self)->setHttpResponse('/transaction/initialize', 'POST', $data)->getResponse();
    }

    public static function chargeAuthorization($data)
    {
        return (new self)->setHttpResponse('/charge_authorization', 'POST', $data)->getResponse();
    }

    public static function refund($data)
    {
        return (new self)->setHttpResponse('/refund', 'POST', $data)->getResponse();
    }

    public static function checkAuthorization($data)
    {
        return (new self)->setHttpResponse('/check_authorization', 'POST', $data)->getResponse();
    }

    public static function deactivateAuthorization($auth_code)
    {
        $data = ['authorization_code' => $auth_code];

        return (new self)->setHttpResponse('/deactivate_authorization', 'POST', $data)->getResponse();
    }

    public static function createSubscription($data)
    {
        return (new self)->setHttpResponse('/subscription', 'POST', $data)->getResponse();
    }

    public static function createCustomer($data)
    {
        return (new self)->setHttpResponse('/customer', 'POST', $data)->getResponse();
    }

    public static function customerSubscriptions($customer_id)
    {
        $data = ['customer' => $customer_id];

        return (new self)->setHttpResponse('/subscription', 'GET', $data)->getData();
    }

    /**
     * Enable a subscription using the subscription code and token
     *
     * @return array
     */
    public static function enableSubscription($data)
    {
        return (new self)->setHttpResponse('/subscription/enable', 'POST', $data)->getResponse();
    }

    /**
     * Disable a subscription using the subscription code and token
     *
     * @return array
     */
    public static function disableSubscription($data)
    {
        return (new self)->setHttpResponse('/subscription/disable', 'POST', $data)->getResponse();
    }

    public static function createInvoice($data)
    {
        return (new self)->setHttpResponse('/paymentrequest', 'POST', $data)->getResponse();
    }

    public static function fetchInvoices($data)
    {
        return (new self)->setHttpResponse('/paymentrequest', 'GET', $data)->getData();
    }

    public static function findInvoice($invoice_id)
    {
        return (new self)->setHttpResponse('/paymentrequest'.$invoice_id, 'GET', [])->getData();
    }

    public static function updateInvoice($invoice_id, $data)
    {
        return (new self)->setHttpResponse('/paymentrequest'.$invoice_id, 'PUT', $data)->getResponse();
    }

    public static function verifyInvoice($invoice_code)
    {
        return (new self)->setHttpResponse('/paymentrequest/verify'.$invoice_code, 'GET', [])->getData();
    }

    public static function notifyInvoice($invoice_id)
    {
        return (new self)->setHttpResponse('/paymentrequest/notify'.$invoice_id, 'POST', [])->getResponse();
    }

    public static function finalizeInvoice($invoice_id)
    {
        return (new self)->setHttpResponse('/paymentrequest/finalize'.$invoice_id, 'POST', [])->getResponse();
    }

    public static function archiveInvoice($invoice_id)
    {
        return (new self)->setHttpResponse('/paymentrequest/archive'.$invoice_id, 'POST', [])->getResponse();
    }

    public static function createPlan($data)
    {
        return (new self)->setHttpResponse('/plan', 'POST', $data)->getData();
    }
}
