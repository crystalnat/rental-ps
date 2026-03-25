<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_log_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Snapshot referensi harga saat order dibuat');

            $table->string('product_name')->comment('Snapshot nama produk agar tidak berubah jika produk diedit');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 30)->default('pcs');
            $table->decimal('unit_price', 12, 2)->comment('Snapshot harga jual saat order');
            $table->decimal('buy_price', 12, 2)->default(0)->comment('Snapshot harga beli untuk kalkulasi margin');
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->comment('(unit_price - discount) * quantity');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
