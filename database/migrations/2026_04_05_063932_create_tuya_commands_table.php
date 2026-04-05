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
        Schema::create('tuya_commands', function (Blueprint $table) {
            $table->id();
            $table->string('store_id')->index();
            $table->string('device_id');
            $table->boolean('switch');
            $table->enum('status', ['pending', 'done', 'failed'])->default('pending')->index();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tuya_commands');
    }
};
