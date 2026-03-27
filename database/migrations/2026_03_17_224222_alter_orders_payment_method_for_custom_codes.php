<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Kosongkan logikanya karena masalah tipe data
        // payment_method sudah diperbaiki langsung di file 
        // migration `create_orders_table`.
    }

    public function down(): void
    {
        // ...
    }
};
