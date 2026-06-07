<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY type ENUM('dine_in', 'takeaway', 'walk_in', 'manual') NOT NULL DEFAULT 'dine_in'");
        }
        // SQLite & pgsql: no ENUM enforcement, already works
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY type ENUM('dine_in', 'takeaway', 'walk_in') NOT NULL DEFAULT 'dine_in'");
        }
    }
};
