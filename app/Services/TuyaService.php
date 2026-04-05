<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\TuyaCommand;

class TuyaService
{
    /**
     * Antri perintah ke DB. Bridge lokal di kasir akan mengambil
     * dan mengeksekusinya secara polling.
     */
    public function sendCommand(string $deviceId, string $_code, $value): bool
    {
        // Cari store_id dari device_id lewat floor
        $table = DiningTable::where('tuya_device_id', $deviceId)->first();
        $storeId = $table
            ? \App\Models\Floor::find($table->floor_id)?->store_id ?? 0
            : 0;

        TuyaCommand::create([
            'store_id'  => $storeId,
            'device_id' => $deviceId,
            'switch'    => (bool) $value,
            'status'    => 'pending',
        ]);

        return true;
    }
}
