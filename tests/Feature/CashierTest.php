<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InitAfricaHQ\Cashier\Customer;
use InitAfricaHQ\Cashier\Paystack;
use Tests\Support\Http\Controllers\WebhookController;
use Tests\Support\User;

function getTestCard()
{
    return [
        'number' => '408 408 408 408 408 1',
        'expiry_month' => 5,
        'expiry_year' => Carbon::now()->addYears(3)->format('Y'),
        'cvv' => '408',
    ];
}

function runTestCharge(User $user, $amount = 10000)
{
    return $user->charge($amount, ['card' => getTestCard()]);
}

function getTestPlan()
{
    $data = [
        'name' => 'Plan '.Str::random(4),
        'amount' => rand(50000, 100000),
        'interval' => 'monthly',
        'send_invoices' => false,
    ];

    $response = Paystack::createPlan($data);

    return $response->json('data');
}

it('can successfully attempt to charge a billable', function () {
    $user = User::factory()->create();

    $charge = $user->charge(500000);

    expect($charge['status'])->toBeTrue();
    expect($charge['message'])->toBe('Authorization URL created');
});

it('can successfully create a subscription for a billable', function () {
    $user = User::factory()->create();

    runTestCharge($user);

    $planCode = getTestPlan()['plan_code'];

    // Create Subscription
    $user->newSubscription('default', $planCode)->create();

    expect(count($user->subscriptions))->toBe(1);
    expect($user->subscription('default')->paystack_id)->toBeTruthy();
    expect($user->subscribed('default'))->toBeTrue();
    expect($user->subscribedToPlan($planCode, 'default'))->toBeTrue();
    expect($user->subscribedToPlan('PLN_cgumntiwkkda3cw', 'something'))->toBeFalse();
    expect($user->subscribedToPlan('PLN_cgumntiwkkda3cw', 'default'))->toBeFalse();
    expect($user->subscribed('default', $planCode))->toBeTrue();
    expect($user->subscribed('default', 'PLN_cgumntiwkkda3cw'))->toBeFalse();
    expect($user->subscription('default')->active())->toBeTrue();
    expect($user->subscription('default')->cancelled())->toBeFalse();
    expect($user->subscription('default')->onGracePeriod())->toBeFalse();
    expect($user->subscription('default')->recurring())->toBeTrue();
    expect($user->subscription('default')->ended())->toBeFalse();

    $subscription = $user->subscription('default');

    // Cancel Subscription
    $subscription->cancel();
    expect($subscription->active())->toBeFalse();
    expect($subscription->cancelled())->toBeTrue();
    expect($subscription->onGracePeriod())->toBeFalse();
    expect($subscription->recurring())->toBeFalse();
    expect($subscription->ended())->toBeTrue();

    // Modify Ends Date To Past
    $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();
    expect($subscription->active())->toBeFalse();
    expect($subscription->cancelled())->toBeTrue();
    expect($subscription->onGracePeriod())->toBeFalse();
    expect($subscription->recurring())->toBeFalse();
    expect($subscription->ended())->toBeTrue();
});

it('can successfully create a subscription for a billable from a webhook', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
        'event' => 'subscription.create',
        'data' => [
            'dodefault' => 'test',
            'status' => 'complete',
            'subscription_code' => 'SUB_vsyqdmlzble3uii',
            'amount' => 50000,
            'cron_expression' => '0 0 28 * *',
            'next_payment_date' => '2016-05-19T07:00:00.000Z',
            'open_invoice' => null,
            'createdAt' => '2016-03-20T00:23:24.000Z',
            'plan' => [
                'name' => 'Monthly retainer',
                'plan_code' => 'PLN_gx2wn530m0i3w3m',
                'description' => null,
                'amount' => 50000,
                'interval' => 'monthly',
                'send_invoices' => true,
                'send_sms' => true,
                'currency' => 'NGN',
            ],
            'authorization' => [
                'authorization_code' => 'AUTH_96xphygz',
                'bin' => '539983',
                'last4' => '7357',
                'exp_month' => '10',
                'exp_year' => '2017',
                'card_type' => 'MASTERCARD DEBIT',
                'bank' => 'GTBANK',
                'country_code' => 'NG',
                'brand' => 'MASTERCARD',
            ],
            'customer' => [
                'first_name' => 'BoJack',
                'last_name' => 'Horseman',
                'email' => 'bojack@horsinaround.com',
                'customer_code' => $user->customer->paystack_code,
                'phone' => '',
                'risk_action' => 'default',
            ],
            'created_at' => '2016-10-01T10:59:59.000Z',
        ],
    ]));

    $controller = new \InitAfricaHQ\Cashier\Http\Controllers\WebhookController;
    $response = $controller($request);

    $this->assertEquals(200, $response->getStatusCode());

    $user = $user->fresh();

    $subscription = $user->subscription('default');

    expect($subscription->active())->toBeTrue();
    expect($subscription->cancelled())->toBeFalse();
    expect($subscription->onGracePeriod())->toBeFalse();
    expect($subscription->recurring())->toBeTrue();
    expect($subscription->ended())->toBeFalse();
});

it('can enable billable configured generic trials', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    expect($user->onGenericTrial())->toBeFalse();

    $user->customer()->update(['trial_ends_at' => Carbon::tomorrow()]);
    expect($user->onGenericTrial())->toBeFalse();

    $user->customer()->update(['trial_ends_at' => Carbon::today()->subDays(5)]);
    expect($user->onGenericTrial())->toBeFalse();
});

it('can successfully create a subscription with trial', function () {
    $user = User::factory()->create();

    runTestCharge($user, 1);

    $planCode = getTestPlan()['plan_code'];

    // Create Subscription
    $user->newSubscription('default', $planCode)
        ->trialDays(7)
        ->create();

    $subscription = $user->subscription('default');
    expect($subscription->active())->toBeTrue();
    expect($subscription->onTrial())->toBeTrue();
    expect($subscription->recurring())->toBeFalse();
    expect($subscription->ended())->toBeFalse();
    expect($subscription->trial_ends_at->day)->toBe(Carbon::today()->addDays(7)->day);

    // Cancel Subscription
    $subscription->cancel();
    expect($subscription->active())->toBeTrue();
    expect($subscription->onGracePeriod())->toBeTrue();
    expect($subscription->recurring())->toBeFalse();
    expect($subscription->ended())->toBeFalse();
});

it('can successfully mark a subscription as cancelled from a webhook', function () {
    $user = User::factory()->create();

    runTestCharge($user, 1);

    $planCode = getTestPlan()['plan_code'];
    $user->createAsCustomer();

    // Create Subscription
    $user->newSubscription('default', $planCode)->create();

    // Fetch Subscription
    $subscription = $user->subscription('default');

    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
        'event' => 'subscription.disable',
        'data' => [
            'dodefault' => 'test',
            'status' => 'complete',
            'subscription_code' => $subscription->paystack_code,
            'amount' => 50000,
            'cron_expression' => '0 0 28 * *',
            'next_payment_date' => '2016-05-19T07:00:00.000Z',
            'open_invoice' => null,
            'createdAt' => '2016-03-20T00:23:24.000Z',
            'plan' => [
                'name' => 'Monthly retainer',
                'plan_code' => $subscription->paystack_plan,
                'description' => null,
                'amount' => 50000,
                'interval' => 'monthly',
                'send_invoices' => true,
                'send_sms' => true,
                'currency' => 'NGN',
            ],
            'authorization' => [
                'authorization_code' => 'AUTH_96xphygz',
                'bin' => '539983',
                'last4' => '7357',
                'exp_month' => '10',
                'exp_year' => '2017',
                'card_type' => 'MASTERCARD DEBIT',
                'bank' => 'GTBANK',
                'country_code' => 'NG',
                'brand' => 'MASTERCARD',
            ],
            'customer' => [
                'first_name' => 'BoJack',
                'last_name' => 'Horseman',
                'email' => 'bojack@horsinaround.com',
                'customer_code' => $user->customer->paystack_code,
                'phone' => '',
                'risk_action' => 'default',
            ],
            'created_at' => '2016-10-01T10:59:59.000Z',
        ],
    ]));

    $controller = new WebhookController;
    $response = $controller($request);

    expect($response->getStatusCode())->toBe(200);

    $user = $user->fresh();
    $subscription = $user->subscription('default');

    expect($subscription->cancelled())->toBeTrue();
});

it('can successfully create one off invoice for a billable', function () {
    $user = User::factory()->create();

    $user->createAsCustomer();

    // Create Invoice
    $options['due_date'] = 'Next Week';
    $user->invoiceFor('Paystack Cashier', 100000, $options);

    // Invoice Tests
    $invoice = $user->invoices()[0];
    expect($invoice->total())->toBe('₦1,000.00');
    expect($invoice->description)->toBe('Paystack Cashier');
});
