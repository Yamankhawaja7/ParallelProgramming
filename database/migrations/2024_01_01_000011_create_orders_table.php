<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity');
            $table->decimal('total_price', 10, 2);

            // [ACID] الحالات الممكنة للطلب — تُعكس نجاح أو فشل الـ Transaction
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending');

            $table->timestamps();

            // Index لتسريع استعلامات التقرير اليومي (Batch Processing)
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
