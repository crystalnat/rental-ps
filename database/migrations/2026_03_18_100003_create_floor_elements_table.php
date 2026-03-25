<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->comment('pillar, stairs, cashier, wall, door, counter, other');
            $table->string('name', 50)->nullable();
            $table->decimal('x_meters', 8, 2)->default(0);
            $table->decimal('y_meters', 8, 2)->default(0);
            $table->decimal('width_meters', 6, 2)->default(0.5);
            $table->decimal('length_meters', 6, 2)->default(0.5);
            $table->unsignedSmallInteger('rotation_deg')->default(0);
            $table->json('meta')->nullable()->comment('Extra: label, color, etc');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['floor_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_elements');
    }
};
