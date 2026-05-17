<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Jobs\SendInvoiceJob;
use App\Jobs\SendNotificationJob;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

/**
 * InventoryService — Concurrency and Integrity Engine
 *
 * Implements Requirements: #1 (Race Condition), #7 (Locking), #8 (ACID)
 */
class InventoryService
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository
    ) {}

    // =========================================================
    // Requirement #1 — Unsafe Inventory Decrement (Race Condition Simulation)
    // =========================================================
    public function decreaseQuantityUnsafe(int $productId, int $amount): array
    {
        // UNSAFE: Read database without locking.
        $product = $this->inventoryRepository->findWithoutLock($productId);

        if (!$product) {
            throw new Exception("Product not found: #{$productId}");
        }

        if ($product->quantity < $amount) {
            throw new Exception("Insufficient stock (UNSAFE): only {$product->quantity} left");
        }

        // Dangerous Time Gap: Simulated delay of 500ms to predictably trigger concurrent race conditions.
        // Multiple threads will read the same state and execute concurrent writes.
        usleep(500000);

        $product->decrement('quantity', $amount);

        return [
            'method'       => 'UNSAFE',
            'product_id'   => $productId,
            'new_quantity' => $product->quantity,
            'warning'      => 'Race Condition possible! Do not use in production.',
        ];
    }

    // =========================================================
    // Requirement #7 — Concurrency Control (Pessimistic Locking)
    // =========================================================
    public function decreaseQuantitySafe(int $productId, int $amount): array
    {
        // [CONCURRENCY SYNCHRONIZATION POINT #1] Begin Database Transaction
        return $this->inventoryRepository->transaction(function () use ($productId, $amount) {

            // [CONCURRENCY SYNCHRONIZATION POINT #2] Pessimistic Row-Level Lock: SELECT ... FOR UPDATE
            // Blocks any concurrent database threads requesting the same record until current transaction commits or rolls back.
            $product = $this->inventoryRepository->lockForUpdate($productId);

            if ($product->quantity < $amount) {
                // Lock is released automatically upon rollback
                throw new Exception("Insufficient stock: only {$product->quantity} left");
            }

            // [CONCURRENCY SYNCHRONIZATION POINT #3] Atomic Decrement inside protected transaction
            $this->inventoryRepository->decrementStock($product, $amount);

            Log::info("[SAFE] Product #{$productId} decreased by {$amount}. Remaining: " . ($product->quantity - $amount));

            // [CONCURRENCY SYNCHRONIZATION POINT #4] Transaction Commit
            // DB::transaction automatically executes COMMIT on success, releasing all locks safely.
            return [
                'method'       => 'SAFE - Pessimistic Lock',
                'product_id'   => $productId,
                'new_quantity' => $product->fresh()->quantity,
            ];
        });
    }

    // =========================================================
    // Requirement #7 — Concurrency Control (Optimistic Locking)
    // =========================================================
    public function decreaseQuantityOptimistic(int $productId, int $amount): array
    {
        $product = $this->inventoryRepository->findWithoutLock($productId);

        if (!$product) {
            throw new Exception("Product not found: #{$productId}");
        }

        if ($product->quantity < $amount) {
            throw new Exception("Insufficient stock: only {$product->quantity} left");
        }

        $expectedVersion = $product->version;

        usleep(100000);

        $success = $this->inventoryRepository->updateWithOptimisticLock($product, $amount, $expectedVersion);

        if (!$success) {
            throw new Exception("Conflict detected: Product was updated by another concurrent user (Optimistic Lock Failure).");
        }

        return [
            'method'       => 'SAFE - Optimistic Lock',
            'product_id'   => $productId,
            'new_quantity' => $product->fresh()->quantity,
        ];
    }

    // =========================================================
    // Requirement #8 — Complete ACID Transaction
    // =========================================================
    public function placeOrder(int $userId, int $productId, int $quantity): array
    {
        // [ACID - ATOMICITY] Enforces all-or-nothing execution
        $order = $this->inventoryRepository->transaction(function () use ($userId, $productId, $quantity) {

            // [ACID - ISOLATION & LOCKING] Lock product row to prevent race conditions on stock quantity
            $product = $this->inventoryRepository->lockForUpdate($productId);

            if ($product->quantity < $quantity) {
                throw new Exception("Insufficient stock for order. Available: {$product->quantity}");
            }

            $totalPrice = $product->price * $quantity;

            // Step 1: Create Order
            $order = Order::create([
                'user_id'     => $userId,
                'product_id'  => $productId,
                'quantity'    => $quantity,
                'total_price' => $totalPrice,
                'status'      => 'processing',
            ]);

            // Step 2: Decrement Stock (Atomic inside ACID container)
            $this->inventoryRepository->decrementStock($product, $quantity);

            // Step 3: Create Payment Record
            $payment = Payment::create([
                'order_id'        => $order->id,
                'amount'          => $totalPrice,
                'status'          => 'completed',
                'transaction_ref' => 'TXN-' . strtoupper(Str::random(12)),
            ]);

            // Step 4: Update Order Status
            $order->update(['status' => 'completed']);

            // [ACID - CONSISTENCY: Failure in any step triggers a database rollback, protecting state integrity]
            Log::info("[ACID] Order #{$order->id} placed. Product #{$productId} qty: " . $product->fresh()->quantity);

            return $order->load('payment', 'product');
        });

        // [ASYNC - Requirement #3] Offload heavy non-blocking jobs to background queues
        SendInvoiceJob::dispatch($order->id)->onQueue('invoices');
        SendNotificationJob::dispatch($order->id, $order->user_id)->onQueue('notifications');

        return [
            'order_id'        => $order->id,
            'status'          => $order->status,
            'total_price'     => $order->total_price,
            'transaction_ref' => $order->payment->transaction_ref,
            'message'         => 'Order placed successfully. Invoice will be sent shortly.',
        ];
    }
}
