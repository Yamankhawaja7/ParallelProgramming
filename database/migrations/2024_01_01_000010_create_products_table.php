<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);

            // [CONCURRENCY POINT] quantity هو المورد المشترك الذي تتنافس عليه الـ Threads
            $table->unsignedInteger('quantity')->default(0);

            // [OPTIMISTIC LOCK] version يُستخدم لاكتشاف التعديل المتزامن
            $table->unsignedBigInteger('version')->default(0);

            $table->string('category')->default('general');
            $table->timestamps();

            // Index لتسريع قراءة المنتجات حسب الفئة (يقلل Bottleneck)
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
