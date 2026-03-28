<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_sections', function (Blueprint $table) {
            $table->unsignedInteger('section_bitmask')->default(1); // Default bit 0 = 1 (Visible)
        });

        Schema::table('landing_settings', function (Blueprint $table) {
            $table->unsignedInteger('settings_bitmask')->default(0);
        });
        
        // Data Migration: Move is_visible to section_bitmask
        DB::table('landing_sections')->where('is_visible', true)->update([
            'section_bitmask' => DB::raw('section_bitmask | 1')
        ]);
        DB::table('landing_sections')->where('is_visible', false)->update([
            'section_bitmask' => DB::raw('section_bitmask & ~1')
        ]);

        // Data Migration: Move is_published to settings_bitmask
        DB::table('landing_settings')->where('is_published', true)->update([
            'settings_bitmask' => DB::raw('settings_bitmask | 1')
        ]);

        Schema::table('landing_sections', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });

        Schema::table('landing_settings', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('landing_sections', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true);
        });

        Schema::table('landing_settings', function (Blueprint $table) {
            $table->boolean('is_published')->default(false);
        });

        // Restore data
        DB::table('landing_sections')->update([
            'is_visible' => DB::raw('section_bitmask & 1')
        ]);
        DB::table('landing_settings')->update([
            'is_published' => DB::raw('settings_bitmask & 1')
        ]);

        Schema::table('landing_sections', function (Blueprint $table) {
            $table->dropColumn('section_bitmask');
        });

        Schema::table('landing_settings', function (Blueprint $table) {
            $table->dropColumn('settings_bitmask');
        });
    }
};
