<?php

namespace InitAfricaHQ\Cashier\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use InitAfricaHQ\Cashier\Customer;

class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billable_id' => rand(1, 1000),
            'billable_type' => 'user',
            'paystack_id' => rand(1, 1000),
            'paystack_code' => 'CUS_'.rand(1, 10000000),
            'card_brand' => $this->faker->randomElement(['visa', 'mastercard', 'american_express', 'discover', 'jcb', 'diners_club']),
            'card_last_four' => rand(1000, 9999),
            'trial_ends_at' => null,
        ];
    }
}
