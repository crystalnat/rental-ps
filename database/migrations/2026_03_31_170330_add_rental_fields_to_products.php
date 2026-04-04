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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_rental_package')->default(false)->after('unit');
            $table->unsignedInteger('rental_duration_minutes')->nullable()->after('is_rental_package');
            $table->json('included_items_json')->nullable()->after('rental_duration_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_rental_package', 'rental_duration_minutes', 'included_items_json']);
        });
    }
};
