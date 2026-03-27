<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_mutations')) {
            Schema::create('stock_mutations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained()->restrictOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->enum('type', ['in', 'out', 'adjustment', 'waste'])
                    ->comment('in=restock, out=sold/used, adjustment=manual correction, waste=spoilage');
                $table->decimal('quantity', 12, 3)->comment('Always positive; direction determined by type');
                $table->decimal('stock_before', 12, 3);
                $table->decimal('stock_after', 12, 3);
                $table->nullableMorphs('mutatable', 'idx_mutatable');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['store_id', 'product_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
