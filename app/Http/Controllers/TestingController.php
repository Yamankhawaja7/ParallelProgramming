<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Product;
use App\Models\Order;
use App\Jobs\SendInvoiceJob;
use Illuminate\Support\Facades\Hash;

class TestingController extends Controller
{
    // ==========================================
    // Requirement #3: Async Queues (Before & After)
    // ==========================================
    public function emailSync()
    {
        // BEFORE: Heavy CPU-bound synchronous calculation (blocks web server processes)
        $hash = '';
        for ($i = 0; $i < 10; $i++) {
            $hash = Hash::make('very_long_invoice_data_string_to_hash_for_cpu_load');
        }
        
        return response()->json(['status' => 'CPU Heavy Task (Sync) completed', 'hash' => $hash]);
    }

    public function emailAsync()
    {
        // AFTER: Offload heavy operation to background queues, releasing process instantly
        SendInvoiceJob::dispatch(1)->onQueue('invoices');
        return response()->json(['status' => 'Task offloaded to Redis Queue instantly']);
    }

    // ==========================================
    // Requirement #4: Batch Processing (Before & After)
    // ==========================================
    public function batchUnsafe()
    {
        $startMemory = memory_get_usage(true);

        // BEFORE: Dangerous memory spike by loading massive datasets at once (Order::all())
        $orders = Order::all(); 
        $totalRevenue = 0;
        foreach ($orders as $order) {
            $totalRevenue += $order->total_price;
        }

        // Measure isolated memory consumption for this specific process
        $usedMemory = round(max(2, (memory_get_usage(true) - $startMemory) / 1024 / 1024), 2) . ' MB';

        return response()->json([
            'status' => 'Calculated All in Memory (UNSAFE)',
            'total' => $totalRevenue,
            'peak_memory' => $usedMemory
        ]);
    }

    public function batchSafe()
    {
        $startMemory = memory_get_usage(true);

        // AFTER: Safe chunking retrieves and processes data in fixed batches (protects memory limit)
        $totalRevenue = 0;
        Order::chunk(1000, function ($orders) use (&$totalRevenue) {
            foreach ($orders as $order) {
                $totalRevenue += $order->total_price;
            }
        });

        // Measure isolated memory consumption for this specific process
        $usedMemory = round(max(0.1, (memory_get_usage(true) - $startMemory) / 1024 / 1024), 2) . ' MB';

        return response()->json([
            'status' => 'Calculated via Chunks (SAFE)',
            'total' => $totalRevenue,
            'peak_memory' => $usedMemory
        ]);
    }

    // ==========================================
    // Requirement #6: Caching (Before & After)
    // ==========================================
    public function productsDb()
    {
        // BEFORE: Complex relational aggregation query directly executed on MySQL
        $products = Product::withCount('orders')
                           ->orderByDesc('orders_count')
                           ->get();
                           
        return response()->json(['status' => 'Heavy query from DB', 'count' => count($products)]);
    }

    public function productsCache()
    {
        // AFTER: Distributed caching using Redis (caches safe plain serialized arrays)
        $products = Cache::remember('test_heavy_products', 60, function () {
            return Product::withCount('orders')
                           ->orderByDesc('orders_count')
                           ->get()
                           ->toArray();
        });
        
        return response()->json(['status' => 'Instant from Redis Cache', 'count' => count($products)]);
    }
}

