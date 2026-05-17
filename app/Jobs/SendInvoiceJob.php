<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Requirement #3 — Asynchronous Queue Job
 *
 * Dispatches PDF invoice generation and email deliveries asynchronously in the background.
 * Routes execution specifically through the 'invoices' queue.
 */
class SendInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Retry and timeout configuration
    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(private readonly int $orderId) {}

    // Process heavy background work on dedicated queue workers
    public function handle(): void
    {
        $order = Order::with(['product', 'payment', 'user'])->find($this->orderId);

        if (!$order) {
            Log::warning("[INVOICE] Order #{$this->orderId} not found in queue.");
            return;
        }

        // Simulate PDF rendering and SMTP latency
        sleep(1);

        Log::info("[INVOICE] Invoice successfully dispatched for Order #{$this->orderId} | Total: {$order->total_price}");
    }

    // Handle complete processing failures (Fault Tolerance)
    public function failed(\Throwable $exception): void
    {
        Log::error("[INVOICE] Invoice processing completely failed for Order #{$this->orderId}: " . $exception->getMessage());
    }
}
