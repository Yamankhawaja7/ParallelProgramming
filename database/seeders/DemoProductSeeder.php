<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Demo Product',
                'quantity' => 10,
                'price' => 100,
                'category' => 'test',
            ]
        );
    }
}
