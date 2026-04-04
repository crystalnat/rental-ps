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
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_rental')->default(false)->after('type');
            $table->timestamp('rental_started_at')->nullable()->after('is_rental');
            $table->unsignedInteger('rental_duration_minutes')->default(0)->after('rental_started_at');
            $table->timestamp('rental_end_at')->nullable()->after('rental_duration_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_rental', 'rental_started_at', 'rental_duration_minutes', 'rental_end_at']);
        });
    }
};
