<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->foreignId('floor_id')->nullable()->after('store_id')->constrained()->nullOnDelete();
            $table->decimal('x_meters', 8, 2)->default(0)->after('floor_id')->comment('Posisi X di denah (meter)');
            $table->decimal('y_meters', 8, 2)->default(0)->after('x_meters')->comment('Posisi Y di denah (meter)');
            $table->decimal('width_meters', 6, 2)->default(0.8)->after('y_meters')->comment('Lebar meja (meter)');
            $table->decimal('length_meters', 6, 2)->default(1.2)->after('width_meters')->comment('Panjang meja (meter)');
            $table->unsignedSmallInteger('rotation_deg')->default(0)->after('length_meters')->comment('Rotasi derajat');
            $table->string('shape', 20)->default('rectangle')->after('rotation_deg')->comment('rectangle, circle');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropForeign(['floor_id']);
            $table->dropColumn(['floor_id', 'x_meters', 'y_meters', 'width_meters', 'length_meters', 'rotation_deg', 'shape']);
        });
    }
};
