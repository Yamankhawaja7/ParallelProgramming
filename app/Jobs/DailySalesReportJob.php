<?php

namespace App\Jobs;

use App\Models\DailySalesReport;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Requirement #4 — Batch Processing Job
 *
 * Processes daily sales aggregates in fixed-size chunks to protect memory usage.
 * Enforces a CHUNK_SIZE of 500 rows.
 */
class DailySalesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 500; // Batch processing chunk size

    public int $tries   = 1;
    public int $timeout = 600; // 10 minutes timeout for very large tables

    public function __construct(private readonly string $date) {}

    // =========================================================
    // Safe Batch Chunking
    // =========================================================
    public function handle(): void
    {
        $reportDate = Carbon::parse($this->date)->toDateString();

        Log::info("[BATCH] Starting daily sales report for {$reportDate}");

        // Aggregation accumulators
        $totalOrders  = 0;
        $totalRevenue = 0.0;
        $itemsSold    = 0;
        $productSales = [];

        // Safe DB cursor chunking (limits memory load per loop cycle)
        Order::query()
            ->where('status', 'completed')
            ->whereDate('created_at', $reportDate)
            ->select(['id', 'product_id', 'quantity', 'total_price'])
            ->chunk(self::CHUNK_SIZE, function ($orders) use (
                &$totalOrders, &$totalRevenue, &$itemsSold, &$productSales
            ) {
                foreach ($orders as $order) {
                    $totalOrders++;
                    $totalRevenue += (float) $order->total_price;
                    $itemsSold    += $order->quantity;

                    $productSales[$order->product_id] =
                        ($productSales[$order->product_id] ?? 0) + $order->quantity;
                }

                Log::debug("[BATCH] Processed chunk of {$orders->count()} orders");
            });

        arsort($productSales);
        $topProducts = array_slice($productSales, 0, 5, true);

        // Safely persist or update daily aggregates
        DailySalesReport::updateOrCreate(
            ['report_date' => $reportDate],
            [
                'total_orders'  => $totalOrders,
                'total_revenue' => $totalRevenue,
                'items_sold'    => $itemsSold,
                'top_products'  => $topProducts,
            ]
        );

        Log::info("[BATCH] Report successfully generated for {$reportDate}: {$totalOrders} orders, revenue: {$totalRevenue}");
    }
}
