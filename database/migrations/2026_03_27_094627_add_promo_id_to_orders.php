<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'promo_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('promo_id')->nullable()->after('cashier_id')->constrained()->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'promo_id')) {
            Schema::table('orders', function (Blueprint $table) {
                // $table->dropForeign(['promo_id']);
                $table->dropColumn('promo_id');
            });
        }
    }
};
