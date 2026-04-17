<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderActivity>
 */
class OrderActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'activity_type' => 'confirmed',
            'description' => fake()->sentence(),
            'status_from' => Order::STATUS_DRAFT,
            'status_to' => Order::STATUS_CONFIRMED,
        ];
    }
}
