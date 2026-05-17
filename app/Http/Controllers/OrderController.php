<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use App\Services\ProductCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

/**
 * OrderController — واجهة API الرئيسية
 *
 * يغطي: #1 Race Condition, #3 Async, #6 Cache, #7 Lock, #8 ACID
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly InventoryService    $inventoryService,
        private readonly ProductCacheService $cacheService,
    ) {}

    // =========================================================
    // GET /api/products — قائمة المنتجات (مع Redis Cache)
    // =========================================================
    public function listProducts(Request $request): JsonResponse
    {
        $category = $request->query('category', 'all');
        $page     = (int) $request->query('page', 1);

        // [CACHE #6] يُرجع من Redis إذا موجود — بدون DB query
        $products = $this->cacheService->getProductList($category, $page);

        return response()->json([
            'status' => 'ok',
            'source' => 'cache',  // سيكون 'db' أول مرة فقط
            'data'   => $products,
        ]);
    }

    // =========================================================
    // GET /api/products/{id} — منتج واحد (مع Redis Cache)
    // =========================================================
    public function getProduct(int $id): JsonResponse
    {
        $product = $this->cacheService->getProduct($id);

        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Product not found'], 404);
        }

        // [CACHE #6] تتبع المنتجات الأكثر طلباً في Redis Sorted Set
        $this->cacheService->incrementHotScore($id);

        return response()->json(['status' => 'ok', 'data' => $product]);
    }

    // =========================================================
    // GET /api/products/hot — المنتجات الأكثر طلباً
    // =========================================================
    public function hotProducts(): JsonResponse
    {
        $products = $this->cacheService->getHotProducts(5);
        return response()->json(['status' => 'ok', 'data' => $products]);
    }

    // =========================================================
    // POST /api/orders — إنشاء طلب (ACID + Lock + Async)
    // =========================================================
    public function placeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'    => 'required|integer|exists:users,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'required|integer|min:1|max:100',
        ]);

        try {
            // [#8 ACID + #7 Lock + #3 Async] داخل placeOrder
            $result = $this->inventoryService->placeOrder(
                $validated['user_id'],
                $validated['product_id'],
                $validated['quantity']
            );

            // [#6 Cache Invalidation] بعد تغيير المخزون
            $this->cacheService->invalidateProduct($validated['product_id']);

            return response()->json([
                'status'  => 'ok',
                'message' => 'Order placed. Invoice queued for background delivery.',
                'data'    => $result,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    // =========================================================
    // POST /api/inventory/unsafe-decrease — إثبات المشكلة #1
    // =========================================================
    public function unsafeDecrease(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'amount'     => 'required|integer|min:1',
        ]);

        try {
            $result = $this->inventoryService->decreaseQuantityUnsafe(
                $validated['product_id'],
                $validated['amount']
            );
            return response()->json(['status' => 'ok', 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    // =========================================================
    // POST /api/inventory/safe-decrease — إثبات الحل #1
    // =========================================================
    public function safeDecrease(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'amount'     => 'required|integer|min:1',
        ]);

        try {
            $result = $this->inventoryService->decreaseQuantitySafe(
                $validated['product_id'],
                $validated['amount']
            );
            return response()->json(['status' => 'ok', 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    // =========================================================
    // POST /api/inventory/optimistic-decrease — إثبات الحل البديل #7 (Optimistic Locking)
    // =========================================================
    public function optimisticDecrease(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'amount'     => 'required|integer|min:1',
        ]);

        try {
            $result = $this->inventoryService->decreaseQuantityOptimistic(
                $validated['product_id'],
                $validated['amount']
            );
            return response()->json(['status' => 'ok', 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
