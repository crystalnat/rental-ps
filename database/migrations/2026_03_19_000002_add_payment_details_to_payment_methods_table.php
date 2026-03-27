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
        if (!Schema::hasColumn('payment_methods', 'qrcode_image')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->string('qrcode_image')->nullable()->after('requires_cash_input')->comment('QRIS: gambar barcode/QR static');
                $table->string('account_name', 100)->nullable()->after('qrcode_image')->comment('Transfer/E-Wallet: nama rekening');
                $table->string('account_number', 50)->nullable()->after('account_name')->comment('Transfer/E-Wallet: nomor rekening');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['qrcode_image', 'account_name', 'account_number']);
        });
    }
};
