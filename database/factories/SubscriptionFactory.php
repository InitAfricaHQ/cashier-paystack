<?php

namespace InitAfricaHQ\Cashier\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use InitAfricaHQ\Cashier\Customer;
use InitAfricaHQ\Cashier\Subscription;

class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billable_id' => rand(1, 1000),
            'billable_type' => 'App\\Models\\User',
            'type' => Subscription::DEFAULT_TYPE,
            'paystack_plan' => rand(1, 1000),
            'paystack_id' => rand(1, 1000),
            'paystack_code' => rand(1, 1000),
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): self
    {
        return $this->afterCreating(function ($subscription) {
            Customer::factory()->create([
                'billable_id' => $subscription->billable_id,
                'billable_type' => $subscription->billable_type,
            ]);
        });
    }

    /**
     * Mark the subscription as being within a trial period.
     */
    public function trialing(DateTimeInterface $trialEndsAt = null): self
    {
        return $this->state([
            'trial_ends_at' => $trialEndsAt,
        ]);
    }

    /**
     * Mark the subscription as cancelled.
     */
    public function cancelled(): self
    {
        return $this->state([
            'ends_at' => now(),
        ]);
    }
}
