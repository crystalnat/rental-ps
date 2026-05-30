<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores type as TEXT — no ENUM alteration needed

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
        // SQLite stores type as TEXT — no ENUM alteration needed

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }
};
