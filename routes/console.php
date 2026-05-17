<?php

use App\Jobs\DailySalesReportJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =========================================================
// [BATCH PROCESSING #4] جدولة التقرير اليومي
// يعمل كل يوم في منتصف الليل تلقائياً
// يُرسل Job إلى Queue → Worker يعالجه على Chunks
// =========================================================
Schedule::call(function () {
    DailySalesReportJob::dispatch(now()->subDay()->toDateString())
        ->onQueue('batch');
})->dailyAt('00:05')
  ->name('daily-sales-report')
  ->withoutOverlapping(); // [CONCURRENCY] يمنع تشغيل نسختين في نفس الوقت

// =========================================================
// [BENCHMARKING #10] تقرير أداء أسبوعي
// =========================================================
Schedule::command('benchmark:report')
    ->weekly()
    ->mondays()
    ->at('08:00')
    ->appendOutputTo(storage_path('logs/benchmark.log'));

