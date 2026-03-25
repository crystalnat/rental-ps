<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50)->comment('e.g. Meja 1, A1, VIP-01');
            $table->string('qr_code', 100)->unique()->comment('Unique token embedded in QR URL');
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->string('floor', 20)->nullable()->comment('e.g. Lantai 1, Outdoor');
            $table->enum('status', ['available', 'occupied', 'reserved', 'inactive'])->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'name']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
