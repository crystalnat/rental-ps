<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TuyaService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $baseUrl;

    public function __construct()
    {
        $this->clientId = config('services.tuya.client_id');
        $this->clientSecret = config('services.tuya.client_secret');
        $this->baseUrl = config('services.tuya.base_url', 'https://openapi-sg.iotbing.com');
    }

    /**
     * Get Tuya Access Token with caching
     */
    public function getAccessToken(): ?string
    {
        return Cache::remember('tuya_access_token', 3600, function () {
            $t = (string) (int) (microtime(true) * 1000);
            $path = '/v1.0/token?grant_type=1';
            
            $sign = $this->generateSignature('GET', $path, '', '', $t);

            $response = Http::withHeaders([
                'client_id' => $this->clientId,
                'sign' => $sign,
                't' => $t,
                'sign_method' => 'HMAC-SHA256',
            ])->get($this->baseUrl . $path);

            if ($response->successful() && $response->json('success')) {
                return $response->json('result.access_token');
            }

            Log::error('Tuya Auth Failed', $response->json() ?? ['status' => $response->status()]);
            return null;
        });
    }

    /**
     * Send command to a device
     */
    public function sendCommand(string $deviceId, string $code, $value): bool
    {
        $token = $this->getAccessToken();
        if (!$token) return false;

        $t = (string) (int) (microtime(true) * 1000);
        $path = "/v1.0/devices/{$deviceId}/commands";
        
        $payload = [
            'commands' => [
                ['code' => $code, 'value' => $value]
            ]
        ];
        $body = json_encode($payload);

        $sign = $this->generateSignature('POST', $path, $body, $token, $t);

        $response = Http::withHeaders([
            'client_id' => $this->clientId,
            'sign' => $sign,
            't' => $t,
            'access_token' => $token,
            'sign_method' => 'HMAC-SHA256',
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . $path, $payload);

        if ($response->successful() && $response->json('success')) {
            return true;
        }

        Log::error("Tuya Command Failed for device {$deviceId}", $response->json() ?? ['status' => $response->status()]);
        return false;
    }

    /**
     * Generate Tuya API Signature
     */
    protected function generateSignature(string $method, string $path, string $body, string $token, string $t): string
    {
        $contentSha256 = hash('sha256', $body);
        $stringToSign = "{$method}\n{$contentSha256}\n\n{$path}";
        $strToSign = $this->clientId . $token . $t . $stringToSign;
        
        return strtoupper(hash_hmac('sha256', $strToSign, $this->clientSecret));
    }
}
