<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * InventoryRepository
 * 
 * Enforces Single Responsibility and Repository Pattern for inventory database queries.
 * Manages row locking mechanisms.
 */
class InventoryRepository
{
    /**
     * Unlocked read (susceptible to concurrent race conditions).
     */
    public function findWithoutLock(int $productId): ?Product
    {
        return Product::find($productId);
    }

    /**
     * Pessimistic Locking query (SELECT ... FOR UPDATE).
     */
    public function lockForUpdate(int $productId): Product
    {
        return Product::lockForUpdate()->findOrFail($productId);
    }

    /**
     * تحديث المخزون (خصم) ورفع الإصدار
     */
    public function decrementStock(Product $product, int $amount): void
    {
        $product->decrement('quantity', $amount);
        $product->increment('version');
    }

    /**
     * تحديث المخزون باستخدام Optimistic Locking
     */
    public function updateWithOptimisticLock(Product $product, int $amount, int $expectedVersion): bool
    {
        return Product::where('id', $product->id)
            ->where('version', $expectedVersion)
            ->update([
                'quantity' => $product->quantity - $amount,
                'version'  => $expectedVersion + 1,
            ]) > 0;
    }

    /**
     * تنفيذ دالة داخل Database Transaction
     */
    public function transaction(\Closure $callback)
    {
        return DB::transaction($callback);
    }
}
