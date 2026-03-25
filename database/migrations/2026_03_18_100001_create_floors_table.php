<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50)->comment('e.g. Lantai 1, Lantai 2');
            $table->decimal('width_meters', 8, 2)->comment('Lebar denah dalam meter');
            $table->decimal('length_meters', 8, 2)->comment('Panjang denah dalam meter');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floors');
    }
};
