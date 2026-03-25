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
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('sku', 100)->nullable()->comment('Stock Keeping Unit');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('unit', 30)->default('pcs')->comment('pcs, kg, liter, porsi, etc.');
            $table->boolean('is_available')->default(true)->comment('Available for ordering');
            $table->boolean('track_stock')->default(true)->comment('Whether to track stock for this product');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['brand_id', 'slug']);
            $table->index(['brand_id', 'is_available', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
