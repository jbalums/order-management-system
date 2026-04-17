<?php

namespace Database\Factories;

use App\Models\InventoryLog;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLog>
 */
class InventoryLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'order_id' => null,
            'change_type' => 'adjustment',
            'quantity_change' => fake()->numberBetween(-10, 10),
            'reason' => fake()->sentence(),
        ];
    }
}
