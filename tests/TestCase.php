<?php

namespace InitAfricaHQ\Cashier\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Unicodeveloper\Paystack\Facades\Paystack;
use Unicodeveloper\Paystack\PaystackServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Load package service provider
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [PaystackServiceProvider::class];
    }

    /**
     * Load package alias
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'laravel-paystack' => Paystack::class,
        ];
    }
}
