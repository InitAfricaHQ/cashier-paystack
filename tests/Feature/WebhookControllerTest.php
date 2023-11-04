<?php

namespace Tests;

use Illuminate\Http\Request;
use Tests\Support\Http\Controllers\WebhookController;

it('can successfully call right event method', function () {
    $_SERVER['__received'] = false;
    $request = Request::create(
        '/', 'POST', [], [], [], [], json_encode(['event' => 'subscription.create', 'data' => []])
    );

    (new WebhookController)($request);

    expect($_SERVER['__received'])->toBeTrue();
});

it('would send response when event method is missing', function () {
    $request = Request::create(
        '/', 'POST', [], [], [], [], json_encode(['event' => 'foo.bar', 'data' => []])
    );
    $response = (new WebhookController)($request);

    expect($response->getStatusCode())->toBe(200);
});
