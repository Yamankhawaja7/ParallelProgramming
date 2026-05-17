<?php

namespace App\Http\Middleware;

use App\Models\PerformanceLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requirement #10 — AOP Performance Monitor
 *
 * Aspect-Oriented Programming (AOP): Intercepts and measures performance of all incoming HTTP requests.
 * Tracks: Response Time | Query Count | Memory Usage | Bottleneck Detection
 */
class PerformanceMonitorMiddleware
{
    // Performance Bottleneck Thresholds
    private const SLOW_THRESHOLD_MS    = 500;  // Response time > 500ms -> Bottleneck
    private const HEAVY_QUERY_COUNT    = 10;   // Database queries > 10 -> Potential N+1 issue
    private const HIGH_MEMORY_MB       = 50;   // Memory consumption > 50MB -> RAM Bottleneck

    public function handle(Request $request, Closure $next): Response
    {
        // Before Request Hook - Start profiling
        $startTime   = microtime(true);
        $startMemory = memory_get_usage(true);

        // Enable DB query tracking for accurate benchmarking
        DB::enableQueryLog();

        // Process request through the router
        $response = $next($request);

        // After Request Hook - Stop profiling and measure metrics
        $durationMs  = (int) round((microtime(true) - $startTime) * 1000);
        $queryCount  = count(DB::getQueryLog());
        $memoryMb    = (int) round((memory_get_usage(true) - $startMemory) / 1024 / 1024);

        DB::disableQueryLog();

        // Detect bottlenecks programmatically
        $bottleneck = $this->detectBottleneck($durationMs, $queryCount, $memoryMb);

        if ($bottleneck) {
            Log::warning("[BOTTLENECK] {$bottleneck} | {$request->method()} {$request->path()} | {$durationMs}ms | {$queryCount} queries");
        }

        // Store log in database for benchmark history (using safe try-catch wrapper)
        try {
            PerformanceLog::create([
                'endpoint'    => '/' . $request->path(),
                'method'      => $request->method(),
                'duration_ms' => $durationMs,
                'query_count' => $queryCount,
                'memory_mb'   => max(0, $memoryMb),
                'status_code' => $response->getStatusCode(),
                'bottleneck'  => $bottleneck,
                'logged_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[AOP] Failed to persist performance log: ' . $e->getMessage());
        }

        // Inject custom HTTP headers to facilitate live debugging and JMeter profiling
        $response->headers->set('X-Response-Time',  "{$durationMs}ms");
        $response->headers->set('X-Query-Count',    (string) $queryCount);
        $response->headers->set('X-Memory-MB',      (string) $memoryMb);

        return $response;
    }

    private function detectBottleneck(int $durationMs, int $queryCount, int $memoryMb): ?string
    {
        if ($durationMs > self::SLOW_THRESHOLD_MS) {
            return "SLOW_RESPONSE:{$durationMs}ms";
        }
        if ($queryCount > self::HEAVY_QUERY_COUNT) {
            return "N+1_QUERIES:{$queryCount}";
        }
        if ($memoryMb > self::HIGH_MEMORY_MB) {
            return "HIGH_MEMORY:{$memoryMb}MB";
        }
        return null;
    }
}
