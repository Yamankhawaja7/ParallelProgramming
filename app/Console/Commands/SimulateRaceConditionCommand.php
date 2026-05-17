<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class SimulateRaceConditionCommand extends Command
{
    protected $signature   = 'race:simulate
                                {--mode=safe   : safe | unsafe}
                                {--users=10    : number of concurrent users to simulate}
                                {--qty=1       : quantity each user wants to buy}';

    protected $description = 'Simulate concurrent inventory access to demonstrate Race Condition';

    public function handle(InventoryService $service): int
    {
        $mode  = $this->option('mode');
        $users = (int) $this->option('users');
        $qty   = (int) $this->option('qty');

        // إنشاء منتج تجريبي بكمية = users (حتى يمكن الجميع الشراء نظرياً)
        $product = Product::create([
            'name'     => "Race Test [{$mode}] " . now()->format('H:i:s'),
            'price'    => 10.00,
            'quantity' => $users * $qty,
            'category' => 'test',
        ]);

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🧪 Race Condition Simulation");
        $this->info("   Mode    : " . strtoupper($mode));
        $this->info("   Users   : {$users}");
        $this->info("   Product : #{$product->id} | Initial qty: {$product->quantity}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $successes = 0;
        $failures  = 0;
        $results   = [];

        for ($i = 1; $i <= $users; $i++) {
            try {
                if ($mode === 'unsafe') {
                    $result = $service->decreaseQuantityUnsafe($product->id, $qty);
                } else {
                    $result = $service->decreaseQuantitySafe($product->id, $qty);
                }
                $successes++;
                $results[] = "  User {$i}: ✅  remaining={$result['new_quantity']}";
            } catch (\Exception $e) {
                $failures++;
                $results[] = "  User {$i}: ❌  {$e->getMessage()}";
            }
        }

        foreach ($results as $line) {
            $this->line($line);
        }

        // جلب القيمة الفعلية من DB بعد كل العمليات
        $finalQty    = Product::find($product->id)->quantity;
        $expectedQty = max(0, ($users * $qty) - ($successes * $qty));

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Results:");
        $this->info("   Successes   : {$successes}");
        $this->info("   Failures    : {$failures}");
        $this->info("   DB qty now  : {$finalQty}");
        $this->info("   Expected    : {$expectedQty}");

        if ($finalQty === $expectedQty) {
            $this->info("   Verdict     : ✅ CORRECT — No data corruption");
        } else {
            $diff = abs($finalQty - $expectedQty);
            $this->error("   Verdict     : ❌ DATA CORRUPTION — Off by {$diff} units!");
            $this->error("   → This proves Race Condition. Run with --mode=safe to fix.");
        }
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // تنظيف بيانات الاختبار
        $product->delete();

        return self::SUCCESS;
    }
}
