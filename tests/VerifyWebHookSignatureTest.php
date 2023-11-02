<?php

namespace InitAfricaHQ\Cashier\Tests;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use InitAfricaHQ\Cashier\Http\Middleware\VerifyWebhookSignature;
use Mockery as m;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class VerifyWebhookSignatureTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function test_signature_checks_out()
    {
        $secret = 'secret';
        $app = m::mock(Application::class);

        $config = m::mock(Config::class);
        $config->shouldReceive('get')->with('paystack.secretKey')->andReturn($secret);

        $request = new Request([], [], [], [], [], [], 'Signed Body');
        $request->headers->set('HTTP_X_PAYSTACK_SIGNATURE', 't='.time().',v1='.$this->sign($request->getContent(), $secret));

        $called = false;

        (new VerifyWebhookSignature($app, $config))->handle($request, function ($request) use (&$called) {
            $called = true;
        });

        self::assertTrue($called);
    }

    public function test_bad_signature_aborts()
    {
        $secret = 'secret';
        $app = m::mock(Application::class);
        $app->shouldReceive('abort')->andThrow(HttpException::class, 403);

        $config = m::mock(Config::class);
        $config->shouldReceive('get')->with('paystack.secretKey')->andReturn($secret);

        $request = new Request([], [], [], [], [], [], 'Signed Body');
        $request->headers->set('Paystack-Signature', 't='.time().',v1=fail');

        self::expectException(HttpException::class);
        (new VerifyWebhookSignature($app, $config))->handle($request, function ($request) {
        });
    }

    public function test_no_or_mismatching_secret_aborts()
    {
        $secret = 'secret';
        $app = m::mock(Application::class);
        $app->shouldReceive('abort')->andThrow(HttpException::class, 403);

        $config = m::mock(Config::class);
        $config->shouldReceive('get')->with('paystack.secretKey')->andReturn($secret);

        $request = new Request([], [], [], [], [], [], 'Signed Body');
        $request->headers->set('Paystack-Signature', 't='.time().',v1='.$this->sign($request->getContent(), ''));

        self::expectException(HttpException::class);

        (new VerifyWebhookSignature($app, $config))->handle($request, function ($request) {
        });
    }

    private function sign($payload, $secret)
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}
