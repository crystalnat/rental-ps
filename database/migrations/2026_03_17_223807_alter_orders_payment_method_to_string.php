<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY payment_method VARCHAR(30) NULL');
        } else {
            DB::statement('ALTER TABLE orders ALTER COLUMN payment_method TYPE VARCHAR(30)');
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cash','qris','bank_transfer','other') NULL");
    }
};
