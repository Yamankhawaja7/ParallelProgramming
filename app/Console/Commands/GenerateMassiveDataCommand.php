<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;

class GenerateMassiveDataCommand extends Command
{
    protected $signature = 'db:massive {count=50000}';
    protected $description = 'Generate massive realistic data for JMeter testing (e.g. 50,000 orders)';

    public function handle()
    {
        $this->info("Generating 5,000 massive products to stress database...");
        
        // 1. Generate 5,000 products
        $productChunks = array_chunk(range(1, 5000), 1000);
        $categories = ['electronics', 'furniture', 'clothing', 'sports', 'books', 'beauty'];
        
        foreach ($productChunks as $chunk) {
            $productsData = [];
            foreach ($chunk as $i) {
                $productsData[] = [
                    'name' => "Epic Product " . uniqid() . " #{$i}",
                    'description' => "This is a super massive product number {$i} to overwhelm MySQL caching tests.",
                    'price' => rand(10, 2000) + (rand(0, 99) / 100),
                    'quantity' => rand(50, 1000),
                    'category' => $categories[array_rand($categories)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Product::insert($productsData);
        }
        $this->info("5,000 products successfully inserted!");

        // 2. Generate 100,000 orders linked to the newly generated products
        $this->info("Generating 100,000 massive orders to stress-test MySQL JOINs...");
        $users = User::pluck('id')->toArray();
        $products = Product::pluck('id')->toArray();

        if (empty($users) || empty($products)) {
            $this->error("Please run 'php artisan db:seed' first.");
            return;
        }

        $orderChunks = array_chunk(range(1, 100000), 2000);
        $bar = $this->output->createProgressBar(count($orderChunks));

        foreach ($orderChunks as $chunk) {
            $ordersData = [];
            foreach ($chunk as $i) {
                $qty = rand(1, 5);
                $ordersData[] = [
                    'user_id' => $users[array_rand($users)],
                    'product_id' => $products[array_rand($products)],
                    'quantity' => $qty,
                    'total_price' => $qty * rand(10, 500),
                    'status' => 'completed',
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now(),
                ];
            }
            Order::insert($ordersData);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully inserted 100,000 realistic orders into the database!");
        $this->info("Now JMeter will show substantial differences in Response Time & Memory Usage.");
    }
}
