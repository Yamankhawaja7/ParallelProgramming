<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProductCacheService — Requirement #6 (Distributed Caching)
 *
 * Caches frequently accessed products in Redis to reduce expensive DB aggregate operations.
 * Enforces a standard TTL of 300 seconds.
 */
class ProductCacheService
{
    private const CACHE_TTL     = 300;       // TTL in seconds
    private const CACHE_PREFIX  = 'product:';
    private const LIST_KEY      = 'products:list';
    private const HOT_KEY       = 'products:hot';

    // =========================================================
    // Fetch a single product via Cache-Aside Pattern
    // =========================================================
    public function getProduct(int $id): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $id;

        try {
            // [CACHE HIT] Return cached record directly without hitting the database
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
                // [CACHE MISS] Retrieve from database and persist in cache
                Log::debug("[CACHE MISS] Product #{$id} fetched from DB");
                $product = Product::find($id);
                return $product ? $product->toArray() : null;
            });
        } catch (\Exception $e) {
            Log::warning("[REDIS DOWN] Fallback to DB for getProduct: " . $e->getMessage());
            $product = Product::find($id);
            return $product ? $product->toArray() : null;
        }
    }

    // =========================================================
    // Paginated product list caching
    // =========================================================
    public function getProductList(string $category = 'all', int $page = 1): array
    {
        $cacheKey = self::LIST_KEY . ":{$category}:{$page}";

        try {
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($category, $page) {
                Log::debug("[CACHE MISS] Product list fetched from DB — category: {$category}");

                $query = Product::query()->select(['id', 'name', 'price', 'quantity', 'category']);

                if ($category !== 'all') {
                    $query->where('category', $category);
                }

                return $query->paginate(15, ['*'], 'page', $page)->toArray();
            });
        } catch (\Exception $e) {
            Log::warning("[REDIS DOWN] Fallback to DB for getProductList: " . $e->getMessage());
            $query = Product::query()->select(['id', 'name', 'price', 'quantity', 'category']);
            if ($category !== 'all') {
                $query->where('category', $category);
            }
            return $query->paginate(15, ['*'], 'page', $page)->toArray();
        }
    }

    // =========================================================
    // Redis Sorted Sets to track most frequently bought products
    // =========================================================
    public function incrementHotScore(int $productId): void
    {
        try {
            // ZINCRBY: Increment product popularity score atomically in Redis Sorted Set
            Cache::getRedis()->zincrby(self::HOT_KEY, 1, $productId);
        } catch (\Exception $e) {
            Log::warning("[REDIS DOWN] Cannot increment hot score: " . $e->getMessage());
        }
    }

    public function getHotProducts(int $limit = 5): array
    {
        try {
            // ZREVRANGE: Retrieve highest scoring product IDs
            $ids = Cache::getRedis()->zrevrange(self::HOT_KEY, 0, $limit - 1);

            if (empty($ids)) {
                // Fallback: Fetch most popular products from DB using query aggregation
                return Product::withCount('orders')
                    ->orderByDesc('orders_count')
                    ->limit($limit)
                    ->get(['id', 'name', 'price', 'quantity'])
                    ->toArray();
            }

            return Product::whereIn('id', $ids)
                ->get(['id', 'name', 'price', 'quantity'])
                ->toArray();
        } catch (\Exception $e) {
            Log::warning("[REDIS DOWN] Fallback to DB for hot products: " . $e->getMessage());
            return Product::withCount('orders')
                ->orderByDesc('orders_count')
                ->limit($limit)
                ->get(['id', 'name', 'price', 'quantity'])
                ->toArray();
        }
    }

    // =========================================================
    // Cache Invalidation (evicts outdated data on inventory changes)
    // =========================================================
    public function invalidateProduct(int $productId): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . $productId);
            
            // Invalidate associated product listings safely (check if keys exist first)
            $keys = Cache::getRedis()->keys(self::LIST_KEY . ':*');
            if (!empty($keys)) {
                Cache::getRedis()->del(...$keys);
            }
            
            Log::debug("[CACHE] Invalidated cache for product #{$productId}");
        } catch (\Exception $e) {
            Log::warning("[REDIS DOWN] Cannot invalidate cache: " . $e->getMessage());
        }
    }
}
