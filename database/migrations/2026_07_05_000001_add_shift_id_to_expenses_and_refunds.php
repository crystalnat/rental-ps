<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_expenses', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('store_id')
                ->constrained('cashier_shifts')->nullOnDelete();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('store_id')
                ->constrained('cashier_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
