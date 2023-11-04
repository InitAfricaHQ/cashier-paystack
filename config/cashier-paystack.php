<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paystack Public Key
    |--------------------------------------------------------------------------
    |
    | The Paystack public API key.
    |
    */

    'public_key' => env('PAYSTACK_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Paystack Secret Key From Dashboard
    |--------------------------------------------------------------------------
    |
    | The Paystack secret is used to verify that the webhook
    | requests are coming from Paystack. You can find your
    | secret in the Paystack dashboard under the "API & Webhooks" section.
    |
    */

    'secret_key' => env('PAYSTACK_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Paystack Url Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI where webhook from Paystack will be responsded to.
    | You must ensure to configure this route in your Paystack dashboard;
    | however, you can modify this path as you see fit for your application.
    |
    */

    'path' => env('PAYSTACK_PATH', 'paystack'),

    /*
    |--------------------------------------------------------------------------
    | Paystack Merchant Email
    |--------------------------------------------------------------------------
    |
    | This is an optional value for your Paystack account.
    |
    */

    'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),

];
