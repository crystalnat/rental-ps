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
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->string('tuya_device_id')->nullable()->after('status');
            $table->boolean('tuya_status')->default(false)->after('tuya_device_id');
            $table->decimal('rental_price_per_hour', 12, 2)->default(0)->after('tuya_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn(['tuya_device_id', 'tuya_status', 'rental_price_per_hour']);
        });
    }
};
