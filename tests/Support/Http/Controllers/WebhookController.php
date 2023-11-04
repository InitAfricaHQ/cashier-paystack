<?php

namespace Tests\Support\Http\Controllers;

use InitAfricaHQ\Cashier\Http\Controllers\WebhookController as Controller;

class WebhookController extends Controller
{
    public function __construct()
    {
        // Prevent setting middleware...
    }

    public function handleSubscriptionCreate($payload)
    {
        $_SERVER['__received'] = true;
    }
}
