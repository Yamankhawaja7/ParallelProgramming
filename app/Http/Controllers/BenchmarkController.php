<?php

namespace App\Http\Controllers;

use App\Models\PerformanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BenchmarkController — Requirement #10
 *
 * Dashboard APIs for performance summary and Bottleneck Analysis.
 */
class BenchmarkController extends Controller
{
    // GET /api/benchmark/summary — ملخص الأداء الكامل
    public function summary(): JsonResponse
    {
        $stats = PerformanceLog::query()
            ->select([
                'endpoint',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('AVG(duration_ms) as avg_response_ms'),
                DB::raw('MAX(duration_ms) as max_response_ms'),
                DB::raw('MIN(duration_ms) as min_response_ms'),
                DB::raw('AVG(query_count) as avg_queries'),
                DB::raw('SUM(CASE WHEN bottleneck IS NOT NULL THEN 1 ELSE 0 END) as bottleneck_hits'),
            ])
            ->groupBy('endpoint')
            ->orderByDesc('avg_response_ms')
            ->get();

        return response()->json([
            'status' => 'ok',
            'data'   => $stats,
            'note'   => 'Higher avg_response_ms = potential bottleneck endpoint',
        ]);
    }

    // GET /api/benchmark/bottlenecks — الـ Bottlenecks فقط
    public function bottlenecks(): JsonResponse
    {
        $bottlenecks = PerformanceLog::query()
            ->whereNotNull('bottleneck')
            ->select(['endpoint', 'method', 'duration_ms', 'query_count', 'bottleneck', 'logged_at'])
            ->orderByDesc('duration_ms')
            ->limit(50)
            ->get();

        return response()->json([
            'status' => 'ok',
            'count'  => $bottlenecks->count(),
            'data'   => $bottlenecks,
        ]);
    }

    // GET /api/benchmark/compare — قبل/بعد التحسين
    public function compare(Request $request): JsonResponse
    {
        $endpoint = $request->query('endpoint', '/api/orders');

        // Compare first 50 runs against last 50 runs
        $before = PerformanceLog::where('endpoint', $endpoint)
            ->orderBy('logged_at')
            ->limit(50)
            ->avg('duration_ms');

        $after = PerformanceLog::where('endpoint', $endpoint)
            ->orderByDesc('logged_at')
            ->limit(50)
            ->avg('duration_ms');

        $improvement = $before > 0
            ? round((($before - $after) / $before) * 100, 1)
            : 0;

        return response()->json([
            'status'           => 'ok',
            'endpoint'         => $endpoint,
            'before_avg_ms'    => round($before ?? 0, 1),
            'after_avg_ms'     => round($after ?? 0, 1),
            'improvement_pct'  => "{$improvement}%",
            'verdict'          => $improvement > 0 ? 'Improved' : 'No improvement yet',
        ]);
    }
}
