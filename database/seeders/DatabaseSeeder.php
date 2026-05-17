<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ────────────────────────────────────────
        // إنشاء 110 مستخدم تجريبي (100+ للـ Stress Test)
        // ────────────────────────────────────────
        $this->command->info('Creating 110 test users...');

        // مستخدم رئيسي للاختبار اليدوي
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'     => 'Test User',
                'password' => Hash::make('password'),
            ]
        );

        // 109 مستخدم إضافي للـ Stress Test
        for ($i = 1; $i <= 109; $i++) {
            User::firstOrCreate(
                ['email' => "user{$i}@example.com"],
                [
                    'name'     => "User {$i}",
                    'password' => Hash::make('password'),
                ]
            );
        }

        // ────────────────────────────────────────
        // إنشاء منتجات تجريبية
        // ────────────────────────────────────────
        $this->command->info('Creating test products...');

        $products = [
            ['name' => 'Laptop Pro X',     'price' => 1299.99, 'quantity' => 500,  'category' => 'electronics'],
            ['name' => 'Wireless Mouse',   'price' => 29.99,   'quantity' => 1000, 'category' => 'electronics'],
            ['name' => 'Mechanical KB',    'price' => 89.99,   'quantity' => 300,  'category' => 'electronics'],
            ['name' => 'USB-C Hub',        'price' => 49.99,   'quantity' => 800,  'category' => 'electronics'],
            ['name' => 'Monitor 4K',       'price' => 599.99,  'quantity' => 200,  'category' => 'electronics'],
            ['name' => 'Office Chair',     'price' => 349.99,  'quantity' => 150,  'category' => 'furniture'],
            ['name' => 'Standing Desk',    'price' => 499.99,  'quantity' => 100,  'category' => 'furniture'],
            ['name' => 'Desk Lamp',        'price' => 39.99,   'quantity' => 600,  'category' => 'furniture'],
            ['name' => 'Web Camera HD',    'price' => 79.99,   'quantity' => 400,  'category' => 'electronics'],
            ['name' => 'Headset Pro',      'price' => 149.99,  'quantity' => 350,  'category' => 'electronics'],
        ];

        foreach ($products as $data) {
            Product::firstOrCreate(['name' => $data['name']], $data);
        }

        // ────────────────────────────────────────
        // منتج واحد ثابت للاختبار المتوازي (ID=1)
        // ────────────────────────────────────────
        $this->call(\Database\Seeders\DemoProductSeeder::class);

        $this->command->info('✅ Seeding complete: 110 users + 10 products');
    }
}
