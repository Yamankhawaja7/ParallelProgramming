<?php

use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\LoadBalancerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TestingController;
use App\Http\Middleware\PerformanceMonitorMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([PerformanceMonitorMiddleware::class])->group(function () {

    // ─────────────────────────────────────────────
    // Products — [Cache #6]
    // ─────────────────────────────────────────────
    Route::get('/products',         [OrderController::class, 'listProducts']);
    Route::get('/products/hot',     [OrderController::class, 'hotProducts']);
    Route::get('/products/{id}',    [OrderController::class, 'getProduct']);

    // ─────────────────────────────────────────────
    // Orders — [ACID #8 + Lock #7 + Async #3]
    // ─────────────────────────────────────────────
    Route::post('/orders',          [OrderController::class, 'placeOrder']);

    // 
    // Race Condition  — [#1]
    Route::prefix('inventory')->group(function () {
        Route::post('/unsafe-decrease', [OrderController::class, 'unsafeDecrease']);
        Route::post('/safe-decrease',   [OrderController::class, 'safeDecrease']);
        Route::post('/optimistic-decrease', [OrderController::class, 'optimisticDecrease']);
    });

    // Benchmarking — [#10]
    Route::prefix('benchmark')->group(function () {
        Route::get('/summary',      [BenchmarkController::class, 'summary']);
        Route::get('/bottlenecks',  [BenchmarkController::class, 'bottlenecks']);
        Route::get('/compare',      [BenchmarkController::class, 'compare']);
    });

    // Load Balancer — [#5 Load Distribution]
    Route::prefix('lb')->group(function () {
        Route::get('/health',       [LoadBalancerController::class, 'health']);
        Route::post('/simulate',    [LoadBalancerController::class, 'simulate']);
        Route::post('/dispatch',    [LoadBalancerController::class, 'dispatch']);
        Route::post('/server-down', [LoadBalancerController::class, 'markDown']);
        Route::post('/server-up',   [LoadBalancerController::class, 'markUp']);
    });

    // ─────────────────────────────────────────────
    // Testing Before & After Scenarios
    // ─────────────────────────────────────────────
    Route::prefix('test')->group(function () {
        Route::get('/email-sync',    [TestingController::class, 'emailSync']);
        Route::get('/email-async',   [TestingController::class, 'emailAsync']);
        Route::get('/batch-unsafe',  [TestingController::class, 'batchUnsafe']);
        Route::get('/batch-safe',    [TestingController::class, 'batchSafe']);
        Route::get('/products-db',   [TestingController::class, 'productsDb']);
        Route::get('/products-cache',[TestingController::class, 'productsCache']);
    });
});
