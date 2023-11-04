<?php

namespace InitAfricaHQ\Cashier\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class VerifyWebhookSignature
{
    /**
     * Handle the incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->isInvalidSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        return $next($request);
    }

    /**
     * Validate the API signature.
     */
    protected function isInvalidSignature(string $payload, string $signature): bool
    {
        $hash = hash_hmac('sha256', $payload, Config::get('cashier-paystack.secret_key'));

        return hash_equals($hash, $signature) === false;
    }
}
