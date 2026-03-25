<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login kasir.
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda telah dinonaktifkan.'],
            ]);
        }

        // Hanya owner, admin, cashier yang boleh akses API kasir
        if (! in_array($user->role, ['owner', 'admin', 'cashier'])) {
            throw ValidationException::withMessages([
                'email' => ['Anda tidak memiliki akses ke aplikasi kasir.'],
            ]);
        }

        // Revoke token lama (optional: satu device saja)
        $user->tokens()->delete();

        $token = $user->createToken('cashier-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Logout.
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * Data user saat ini.
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    private function formatUser(User $user): array
    {
        $user->load(['brand:id,name', 'store:id,name,slug,receipt_print_enabled']);

        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'role'      => $user->role,
            'brand_id'  => $user->brand_id,
            'store_id'  => $user->store_id,
            'brand'     => $user->brand ? ['id' => $user->brand->id, 'name' => $user->brand->name] : null,
            'store'     => $user->store ? [
                'id'                      => $user->store->id,
                'name'                    => $user->store->name,
                'slug'                    => $user->store->slug,
                'receipt_print_enabled'   => (bool) $user->store->receipt_print_enabled,
            ] : null,
        ];
    }
}
