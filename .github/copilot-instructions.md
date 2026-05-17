# Parallel E-Commerce System - AI Coding Agent Instructions

This project is a high-performance backend system for an E-Commerce platform designed to demonstrate Parallel Programming and Distributed Systems concepts.

## System Architecture & Constraints
- **Framework**: Laravel (optimized for Laravel Octane / Swoole for Thread/Connection Pooling).
- **Database**: Relational Database (MySQL/PostgreSQL) strictly utilizing **ACID Transactions**.
- **Caching**: **Redis** is natively integrated.

## Core Developer Workflows & Conventions

1. **Concurrency Control & Data Integrity (Crucial for `InventoryService`)**
   - **NEVER** decrement stock using normal ORM queries like `Product::find()->decrement()`.
   - **ALWAYS** use Pessimistic Locking with Transactions to prevent Race Conditions.
   - Example Pattern:
     ```php
     DB::transaction(function () use ($productId) {
         $product = Product::lockForUpdate()->find($productId);
         // Business Logic
         $product->decrement('quantity');
     });
     ```

2. **Asynchronous Background Processing (`Jobs`)**
   - Tasks that do not require an immediate HTTP response (like sending invoices, generating reports) **MUST** be pushed to Laravel Queues (`SendInvoiceJob`, `SendNotificationJob`). 
   - Never block the main HTTP thread for external API calls or email dispatches.

3. **Batch Processing for Memory Efficiency**
   - Background jobs processing large data (e.g. `DailySalesReportJob`) **MUST** use Chunking (`->chunk(500, function($records) { ... })`) to prevent Memory Exhaustion and collapse under load.

4. **Distributed Caching Pattern**
   - Top-requested endpoints use a Cache-Aside pattern via Redis (`ProductCacheService`).
   - Use Redis Sorted Sets (`zincrby`) to track hot items.
   - Any modification to inventory MUST trigger Cache Invalidation (`Cache::forget()`).

5. **Benchmarking & AOP (`Middleware`)**
   - `PerformanceMonitorMiddleware` acts as an Aspect-Oriented Programming (AOP) interceptor. It records execution time and database query counts for the Benchmarking endpoints. Do not bypass it for user-facing API routes.

## Creating New Features
- Read first from Redis cache if dealing with products. 
- Offload processing to queues.
- Guard mutations with DB locks.