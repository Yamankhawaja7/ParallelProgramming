<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [AOP / BENCHMARKING] يُسجّل كل طلب تلقائياً بدون تعديل Controller
        Schema::create('performance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');            // المسار المطلوب
            $table->string('method', 10);         // GET/POST/...
            $table->unsignedInteger('duration_ms'); // مدة الاستجابة بالميلي ثانية
            $table->unsignedInteger('query_count'); // عدد استعلامات DB
            $table->unsignedInteger('memory_mb');   // الذاكرة المُستخدمة
            $table->unsignedSmallInteger('status_code');
            $table->string('bottleneck')->nullable(); // إذا تجاوز الحد المحدد
            $table->timestamp('logged_at');

            // Index لتسريع استعلامات التحليل
            $table->index(['endpoint', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_logs');
    }
};
