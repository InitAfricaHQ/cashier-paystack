<?php

namespace Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use InitAfricaHQ\Cashier\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpKernel\Exception\HttpException;

function sign($payload, $secret)
{
    return hash_hmac('sha256', $payload, $secret);
}

it('can successfully verify signature', function () {
    $secret = 'secret';
    Config::set('cashier-paystack.secret_key', $secret);

    $request = new Request([], [], [], [], [], [], 'Signed Body');
    $request->headers->set('x-paystack-signature', sign($request->getContent(), $secret));

    $called = false;

    (new VerifyWebhookSignature)->handle($request, function ($request) use (&$called) {
        $called = true;
    });

    expect($called)->toBeTrue();
});

it('can successfully abort request when cannot verify signature', function () {
    $secret = 'secret';
    Config::set('cashier-paystack.secret_key', $secret);

    $request = new Request([], [], [], [], [], [], 'Signed Body');
    $request->headers->set('x-paystack-signature', 't='.time().',v1=fail');

    (new VerifyWebhookSignature)->handle($request, function ($request) {
    });
})->throws(HttpException::class);

it('can successfully abort request when no or mismatch signature', function () {
    $secret = 'secret';
    Config::set('cashier-paystack.secret_key', $secret);

    $request = new Request([], [], [], [], [], [], 'Signed Body');
    $request->headers->set('x-paystack-signature', sign($request->getContent(), ''));

    (new VerifyWebhookSignature)->handle($request, function ($request) {
    });
})->throws(HttpException::class);
