<?php

namespace InitAfricaHQ\Cashier\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use InitAfricaHQ\Cashier\Cashier;
use InitAfricaHQ\Cashier\Events\SubscriptionCancelled;
use InitAfricaHQ\Cashier\Events\SubscriptionCreated;
use InitAfricaHQ\Cashier\Events\WebhookHandled;
use InitAfricaHQ\Cashier\Events\WebhookReceived;
use InitAfricaHQ\Cashier\Http\Middleware\VerifyWebhookSignature;
use InitAfricaHQ\Cashier\Subscription;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Create a new webhook controller instance.
     *
     * @return voCode
     */
    public function __construct()
    {
        if (config('cashier-paystack.secret_key')) {
            $this->middleware(VerifyWebhookSignature::class);
        }
    }

    /**
     * Handle a Paystack webhook call.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->json()->all();

        if (! isset($payload['event'])) {
            return new Response('Webhook received but no event was found.');
        }

        $method = 'handle'.Str::studly(str_replace('.', '_', $payload['event']));

        WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            try {
                $this->{$method}($payload);
            } catch (Exception $e) {
                return new Response('Webhook skipped due to error processing it.');
            }

            WebhookHandled::dispatch($payload);

            return new Response('Webhook was handled.');
        }

        return new Response('Webhook received but no handler found.');
    }

    /**
     * Handle customer subscription create.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionCreate(array $payload)
    {
        $data = $payload['data'];

        $billable = $this->resolveBillable($payload);

        $subscription = $this->findSubscription($data['subscription_code']);

        if ($billable && ! isset($subscription)) {
            $plan = $data['plan'];

            $builder = $billable->newSubscription($plan['name'], $plan['plan_code']);

            $data['id'] = null;

            $subscription = $builder->save($data);

            SubscriptionCreated::dispatch($billable, $subscription, $payload);
        }
    }

    /**
     * Handle a subscription disabled notification from paystack.
     *
     * @param  array  $payload
     */
    protected function handleSubscriptionDisable($payload)
    {
        $subscriptionCode = $payload['data']['subscription_code'];

        $subscription = $this->findSubscription($subscriptionCode);

        if ($subscription && (! $subscription->cancelled() || $subscription->onGracePeriod())) {
            $subscription->markAsCancelled();

            SubscriptionCancelled::dispatch($subscription->billable, $subscription, $payload);
        }
    }

    /**
     * Get the model for the given subscription Code.
     *
     * @param  string  $subscriptionCode
     */
    protected function findSubscription($subscriptionCode): ?Subscription
    {
        return Cashier::$subscriptionModel::where('paystack_code', $subscriptionCode)
            ->first();
    }

    /**
     * @return \InitAfricaHQ\Cashier\Billable
     *
     * @throws InvalidCustomPayload
     */
    private function resolveBillable(array $payload)
    {
        $customer = $payload['data']['customer']['customer_code'] ?? null;

        if (! isset($customer)) {
            throw new InvalidArgumentException('Customer data not found in payload');
        }

        return Cashier::$customerModel::query()
            ->where('paystack_code', $customer)
            ->first()
            ?->billable;
    }
}
