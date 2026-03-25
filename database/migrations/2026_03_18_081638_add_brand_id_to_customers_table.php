<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE customers MODIFY email VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE customers ALTER COLUMN email DROP NOT NULL');
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['brand_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'email']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE customers MODIFY email VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE customers ALTER COLUMN email SET NOT NULL');
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('email');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
        });
    }
};
