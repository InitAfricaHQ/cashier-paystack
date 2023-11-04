<?php

namespace InitAfricaHQ\Cashier;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InitAfricaHQ\Cashier\Http\Controllers\WebhookController;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Register the application.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cashier-paystack.php', 'cashier-paystack'
        );
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        if (Cashier::$registersRoutes) {
            Route::group([
                'prefix' => config('cashier-paystack.path'),
                'as' => 'paystack.',
            ], function () {
                Route::post('webhook', WebhookController::class)->name('webhook');
            });
        }

        if (Cashier::$runsMigrations && $this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier');

        $this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/cashier'),
        ], 'cashier-paystack-views');

        $this->publishes([
            __DIR__.'/../config/cashier-paystack.php' => $this->app->configPath('cashier-paystack.php'),
        ], 'cashier-paystack-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'cashier-paystack-migrations');
    }
}
