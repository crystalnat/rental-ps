<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'manual' to orders.type enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('dine_in','takeaway','walk_in','manual') NOT NULL DEFAULT 'dine_in'");

        // Make order_items.product_id nullable
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('dine_in','takeaway','walk_in') NOT NULL DEFAULT 'dine_in'");

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }
};
