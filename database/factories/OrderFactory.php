<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        // We assume product factory is used or products exist
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'total_price' => $quantity * $this->faker->randomFloat(2, 10, 1000),
            'status' => $this->faker->randomElement(['processing', 'completed', 'cancelled']),
        ];
    }
}
