<?php

namespace App\Console\Commands;

use App\Models\PerformanceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkReportCommand extends Command
{
    protected $signature   = 'benchmark:report {--endpoint= : filter by endpoint}';
    protected $description = 'Show performance benchmark report with bottleneck analysis';

    public function handle(): int
    {
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Benchmark Report — Parallel E-Commerce System");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $endpoint = $this->option('endpoint');

        // ────────────────────────────────────────
        // Section 1: Per-Endpoint Statistics
        // ────────────────────────────────────────
        $query = PerformanceLog::query()
            ->select([
                'endpoint',
                'method',
                DB::raw('COUNT(*) as requests'),
                DB::raw('ROUND(AVG(duration_ms),1) as avg_ms'),
                DB::raw('MAX(duration_ms) as max_ms'),
                DB::raw('MIN(duration_ms) as min_ms'),
                DB::raw('ROUND(AVG(query_count),1) as avg_queries'),
                DB::raw('SUM(CASE WHEN bottleneck IS NOT NULL THEN 1 ELSE 0 END) as bottlenecks'),
            ])
            ->groupBy('endpoint', 'method')
            ->orderByDesc('avg_ms');

        if ($endpoint) {
            $query->where('endpoint', $endpoint);
        }

        $rows = $query->get()->map(fn($r) => [
            $r->endpoint,
            $r->method,
            $r->requests,
            $r->avg_ms . ' ms',
            $r->max_ms . ' ms',
            $r->min_ms . ' ms',
            $r->avg_queries,
            $r->bottlenecks > 0 ? "⚠️  {$r->bottlenecks}" : "✅  0",
        ])->toArray();

        $this->table(
            ['Endpoint', 'Method', 'Requests', 'Avg', 'Max', 'Min', 'Avg Queries', 'Bottlenecks'],
            $rows
        );

        // ────────────────────────────────────────
        // Section 2: Top Bottlenecks
        // ────────────────────────────────────────
        $this->newLine();
        $this->info("🔴 Top Bottlenecks Detected:");

        $bottlenecks = PerformanceLog::whereNotNull('bottleneck')
            ->select(['endpoint', 'duration_ms', 'query_count', 'bottleneck', 'logged_at'])
            ->orderByDesc('duration_ms')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                $r->endpoint,
                $r->duration_ms . ' ms',
                $r->query_count,
                $r->bottleneck,
                $r->logged_at,
            ])->toArray();

        if (empty($bottlenecks)) {
            $this->info("   ✅ No bottlenecks detected yet. Run stress test first.");
        } else {
            $this->table(
                ['Endpoint', 'Duration', 'Queries', 'Type', 'Time'],
                $bottlenecks
            );
        }

        // ────────────────────────────────────────
        // Section 3: Before vs After Comparison
        // ────────────────────────────────────────
        $this->newLine();
        $this->info("📈 Before vs After — Cache Impact on GET /api/products:");

        $allTimes = PerformanceLog::where('endpoint', 'like', '%products%')
            ->orderBy('logged_at')
            ->pluck('duration_ms');

        if ($allTimes->count() >= 2) {
            $half   = (int) ($allTimes->count() / 2);
            $before = round($allTimes->take($half)->avg(), 1);
            $after  = round($allTimes->skip($half)->avg(), 1);
            $pct    = $before > 0 ? round((($before - $after) / $before) * 100, 1) : 0;

            $this->table(
                ['Metric', 'Before (no cache)', 'After (with Redis)', 'Improvement'],
                [['Avg Response', $before . ' ms', $after . ' ms', $pct . '% faster']]
            );
        } else {
            $this->warn("   Not enough data. Send some requests first.");
        }

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return self::SUCCESS;
    }
}
