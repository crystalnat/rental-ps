<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\TuyaCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TuyaBridgeController extends Controller
{
    /**
     * Bridge polling: ambil semua perintah pending milik toko ini.
     * GET /api/tuya/commands?store_id=1&token=xxx
     */
    public function pending(Request $request): JsonResponse
    {
        if ($request->query('token') !== config('services.tuya.bridge_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $storeId = $request->query('store_id');

        // Abaikan command yang lebih dari 30 detik (bridge mati lama → backlog)
        $commands = TuyaCommand::where('status', 'pending')
            ->where('store_id', $storeId)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'device_id', 'switch']);

        return response()->json($commands);
    }

    /**
     * Bridge lapor hasil eksekusi.
     * POST /api/tuya/commands/{id}/ack?token=xxx
     */
    public function ack(Request $request, int $id): JsonResponse
    {
        if ($request->query('token') !== config('services.tuya.bridge_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $cmd = TuyaCommand::findOrFail($id);
        $cmd->update([
            'status'      => $request->boolean('success') ? 'done' : 'failed',
            'executed_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Bridge push status perangkat.
     * POST /api/tuya/status?token=xxx
     * Body JSON: [{"device_id": "abc", "on": true}, ...]
     */
    public function updateStatus(Request $request): JsonResponse
    {
        if ($request->query('token') !== config('services.tuya.bridge_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $statuses = $request->json()->all();
        $updated  = 0;

        foreach ($statuses as $item) {
            $deviceId = $item['device_id'] ?? null;
            $on       = $item['on'] ?? null;
            if (!$deviceId || $on === null) continue;

            $rows = DiningTable::where('tuya_device_id', 'tuya:' . $deviceId)
                ->orWhere('tuya_device_id', $deviceId)
                ->update(['tuya_status' => (bool) $on]);
            $updated += $rows;
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }
}
