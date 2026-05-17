<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [BATCH PROCESSING] جدول لتخزين نتائج التقرير اليومي
        Schema::create('daily_sales_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->unsignedInteger('total_orders');
            $table->decimal('total_revenue', 12, 2);
            $table->unsignedInteger('items_sold');
            $table->json('top_products')->nullable(); // أعلى 5 منتجات مبيعاً
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_reports');
    }
};
