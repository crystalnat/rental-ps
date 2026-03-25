<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete()
                ->comment('NULL = default price for all stores');
            $table->decimal('buy_price', 12, 2)->default(0)->comment('Harga beli / HPP');
            $table->decimal('sell_price', 12, 2)->comment('Harga jual ke customer');
            $table->timestamp('started_at')->comment('Kapan harga ini mulai berlaku');
            $table->timestamp('ended_at')->nullable()->comment('NULL = harga sedang aktif');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'store_id', 'ended_at'], 'idx_price_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_logs');
    }
};
