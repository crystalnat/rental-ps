<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\TuyaCommand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TuyaService
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId     = config('services.tuya.client_id', '');
        $this->clientSecret = config('services.tuya.client_secret', '');
        $this->baseUrl      = config('services.tuya.base_url', 'https://openapi-sg.iotbing.com');
    }

    /**
     * Kirim perintah ke device.
     * - Prefix "tuya:" atau bare Tuya device ID (bukan IP) → langsung Tuya Cloud API
     * - Prefix "samsung:", "vidaa:", atau IP:PORT → queue ke DB untuk bridge
     */
    public function sendCommand(string $deviceId, string $code, $value): bool
    {
        // Tuya device → coba cloud dulu, fallback ke bridge jika gagal
        $isTuya = str_starts_with($deviceId, 'tuya:')
            || (!str_contains($deviceId, ':') && !filter_var($deviceId, FILTER_VALIDATE_IP));

        if ($isTuya) {
            $bareId = str_starts_with($deviceId, 'tuya:') ? substr($deviceId, 5) : $deviceId;
            if ($this->sendTuyaCloud($bareId, $code, $value)) {
                return true;
            }
            Log::warning("Tuya Cloud gagal untuk {$bareId}, fallback ke bridge...");
        }

        return $this->queueCommand($deviceId, $value);
    }

    /**
     * Langsung hit Tuya Cloud API.
     */
    private function sendTuyaCloud(string $deviceId, string $code, $value): bool
    {
        try {
            $token = $this->getAccessToken();
            $t     = (string) (int) (microtime(true) * 1000);
            $path  = "/v1.0/iot-01/associated-users/devices/{$deviceId}/actions";
            $body  = json_encode(['commands' => [['code' => $code, 'value' => (bool) $value]]]);

            $resp = Http::withHeaders([
                'client_id'    => $this->clientId,
                'access_token' => $token,
                'sign'         => $this->sign($t, $token, 'POST', $path, $body),
                't'            => $t,
                'sign_method'  => 'HMAC-SHA256',
                'Content-Type' => 'application/json',
            ])->withBody($body, 'application/json')->post($this->baseUrl . $path);

            $data = $resp->json();

            Log::debug("Tuya Cloud endpoint1 response [{$deviceId}]: HTTP {$resp->status()} -> " . json_encode($data));

            if ($data['success'] ?? false) {
                Log::info("Tuya Cloud OK: {$deviceId} -> " . ($value ? 'ON' : 'OFF'));
                return true;
            }

            // Fallback endpoint
            $path2 = "/v1.0/devices/{$deviceId}/commands";
            $body2 = json_encode(['commands' => [['code' => $code, 'value' => (bool) $value]]]);
            $t2    = (string) (int) (microtime(true) * 1000);

            $resp2 = Http::withHeaders([
                'client_id'    => $this->clientId,
                'access_token' => $token,
                'sign'         => $this->sign($t2, $token, 'POST', $path2, $body2),
                't'            => $t2,
                'sign_method'  => 'HMAC-SHA256',
                'Content-Type' => 'application/json',
            ])->withBody($body2, 'application/json')->post($this->baseUrl . $path2);

            $data2 = $resp2->json();

            Log::debug("Tuya Cloud endpoint2 response [{$deviceId}]: HTTP {$resp2->status()} -> " . json_encode($data2));

            if ($data2['success'] ?? false) {
                Log::info("Tuya Cloud OK (fallback): {$deviceId} -> " . ($value ? 'ON' : 'OFF'));
                return true;
            }

            Log::error("Tuya Cloud ERR: {$deviceId} -> " . ($data2['msg'] ?? json_encode($data2)));
            return false;

        } catch (\Throwable $e) {
            Log::error("Tuya Cloud exception: {$deviceId} -> {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Queue ke DB untuk bridge (Samsung, VIDAA, ADB).
     */
    private function queueCommand(string $deviceId, $value): bool
    {
        $table   = DiningTable::where('tuya_device_id', $deviceId)->first();
        $storeId = $table?->store_id ?? 1;

        TuyaCommand::create([
            'store_id'  => $storeId,
            'device_id' => $deviceId,
            'switch'    => (bool) $value,
            'status'    => 'pending',
        ]);

        return true;
    }

    /**
     * Ambil Tuya access token (dengan cache di session/cache Laravel).
     */
    private function getAccessToken(): string
    {
        $cacheKey = 'tuya_access_token';
        $cached   = cache($cacheKey);
        if ($cached) {
            return $cached;
        }

        $t    = (string) (int) (microtime(true) * 1000);
        $path = '/v1.0/token?grant_type=1';

        $resp = Http::withHeaders([
            'client_id'   => $this->clientId,
            'sign'        => $this->sign($t, '', 'GET', $path),
            't'           => $t,
            'sign_method' => 'HMAC-SHA256',
        ])->get($this->baseUrl . $path);

        $data = $resp->json();

        Log::debug("Tuya getAccessToken response: HTTP {$resp->status()} -> " . json_encode($data));

        if (!($data['success'] ?? false)) {
            throw new \RuntimeException('Tuya auth gagal: ' . ($data['msg'] ?? json_encode($data)));
        }

        $token     = $data['result']['access_token'];
        $expiresIn = ($data['result']['expire_time'] ?? 7200) - 60;

        cache([$cacheKey => $token], $expiresIn);

        return $token;
    }

    /**
     * HMAC-SHA256 signature untuk Tuya Cloud API.
     */
    private function sign(string $t, string $token, string $method, string $path, string $body = ''): string
    {
        $contentSha256  = hash('sha256', $body);
        $stringToSign   = "{$method}\n{$contentSha256}\n\n{$path}";
        $strToSign      = $this->clientId . $token . $t . $stringToSign;

        return strtoupper(hash_hmac('sha256', $strToSign, $this->clientSecret));
    }
}
