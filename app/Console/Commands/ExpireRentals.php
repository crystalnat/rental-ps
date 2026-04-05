<?php

namespace App\Console\Commands;

use App\Models\DiningTable;
use App\Models\Order;
use App\Services\TuyaService;
use Illuminate\Console\Command;

class ExpireRentals extends Command
{
    protected $signature   = 'rentals:expire';
    protected $description = 'Matikan PS yang waktu rentalnya sudah habis';

    public function handle(TuyaService $tuya): void
    {
        $expired = Order::query()
            ->where('is_rental', true)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('rental_end_at')
            ->where('rental_end_at', '<=', now())
            ->get();

        foreach ($expired as $order) {
            $order->update(['status' => 'done']);

            if ($order->table_id) {
                $table = DiningTable::find($order->table_id);
                if ($table) {
                    $table->update(['tuya_status' => false]);
                    if ($table->tuya_device_id) {
                        $tuya->sendCommand($table->tuya_device_id, 'switch_1', false);
                    }
                }
            }

            $this->line("Expired: Order #{$order->order_code}");
        }

        $this->info("Selesai. {$expired->count()} rental dimatikan.");
    }
}
