<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, so recreate the table
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('
                CREATE TABLE customers_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    brand_id INTEGER NULL,
                    user_id INTEGER NULL,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NULL,
                    phone VARCHAR(255) NULL,
                    total_orders INTEGER NOT NULL DEFAULT 0,
                    total_spent NUMERIC(10,2) NOT NULL DEFAULT 0,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    deleted_at DATETIME NULL,
                    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL
                )
            ');
            DB::statement('INSERT INTO customers_new SELECT id, brand_id, user_id, name, email, phone, total_orders, total_spent, created_at, updated_at, deleted_at FROM customers');
            DB::statement('DROP TABLE customers');
            DB::statement('ALTER TABLE customers_new RENAME TO customers');
            DB::statement('CREATE UNIQUE INDEX customers_brand_id_email_unique ON customers (brand_id, email)');
            DB::statement('PRAGMA foreign_keys = ON');
        }
        // MySQL & pgsql: already handled in previous migration
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('
                CREATE TABLE customers_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    brand_id INTEGER NULL,
                    user_id INTEGER NULL,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    phone VARCHAR(255) NULL,
                    total_orders INTEGER NOT NULL DEFAULT 0,
                    total_spent NUMERIC(10,2) NOT NULL DEFAULT 0,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    deleted_at DATETIME NULL,
                    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL
                )
            ');
            DB::statement('INSERT INTO customers_new SELECT id, brand_id, user_id, name, email, phone, total_orders, total_spent, created_at, updated_at, deleted_at FROM customers');
            DB::statement('DROP TABLE customers');
            DB::statement('ALTER TABLE customers_new RENAME TO customers');
            DB::statement('CREATE UNIQUE INDEX customers_brand_id_email_unique ON customers (brand_id, email)');
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
};
