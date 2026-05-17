<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

class StressTestCommand extends Command
{
    protected $signature = 'app:stress {mode=safe} {requests=50}';
    protected $description = 'يحاكي التزامن واختبار الضغط (Race Condition vs ACID)';

    public function handle()
    {
        $mode = $this->argument('mode'); // safe or unsafe
        $requestsCount = (int) $this->argument('requests');
        $url = "http://127.0.0.1:8000/api/inventory/{$mode}-decrease";

        $this->info("🚀 Starting Concurrent Stress Test...");
        $this->info("Mode: " . strtoupper($mode));
        $this->info("Concurrent Requests: {$requestsCount}");
        
        $payload = [
            'user_id' => 1,
            'product_id' => 1,
            'quantity' => 1
        ];

        // إطلاق كل الطلبات في نفس اللحظة متزامنة (Concurrent Asynchronous Requests)
        $responses = Http::pool(function (Pool $pool) use ($requestsCount, $url, $payload) {
            $reqs = [];
            for ($i = 0; $i < $requestsCount; $i++) {
                $reqs[] = $pool->post($url, $payload);
            }
            return $reqs;
        });

        $success = 0;
        $failed = 0;

        foreach ($responses as $response) {
            if ($response instanceof \Illuminate\Http\Client\Response) {
                if ($response->ok()) {
                    $success++;
                } else {
                    $failed++;
                    $this->error("Failed Request Status: " . $response->status() . " Body: " . $response->body());
                }
            } else {
                $failed++;
                $this->error("Connection Exception/Failure.");
            }
        }

        $this->newLine();
        $this->info("✅ Successful Requests (Status 200/201): {$success}");
        $this->error("❌ Failed/Rejected Requests (Status 4xx/5xx): {$failed}");
        $this->newLine();

        if ($mode === 'unsafe') {
            $this->warn("قم بفحص قاعدة البيانات الآن! ستجد أن الكمية للأسف أصبحت بالسالب (Race Condition حدث للتو).");
        } else {
            $this->info("الطلبات التي نجحت تتوافق مع الكمية الفلعية المتاحة، والباقي تم رفضه كلياً بصورة آمنة.");
        }
    }
}
