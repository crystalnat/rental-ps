<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Schedule: configurable shift times
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();

            // Actual open/close
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();

            // Cash management
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();

            // Summary (auto-calculated on close)
            $table->decimal('total_sales', 15, 2)->nullable();
            $table->integer('total_orders')->nullable();
            $table->decimal('total_cash_sales', 15, 2)->nullable();
            $table->decimal('total_non_cash_sales', 15, 2)->nullable();
            $table->decimal('total_expenses', 15, 2)->nullable();
            $table->decimal('total_refunds', 15, 2)->nullable();

            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');

            $table->timestamps();
        });

        // Add shift_id to orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('cashier_id')
                ->constrained('cashier_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
        Schema::dropIfExists('cashier_shifts');
    }
};
