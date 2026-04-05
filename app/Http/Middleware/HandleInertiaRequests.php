<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $stores = [];
        if ($user && $user->role === 'owner' && $user->brand_id) {
            $stores = Store::where('brand_id', $user->brand_id)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->toArray();
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'role'   => $user->role,
                    'avatar' => $user->avatar,
                    'brand'  => $user->brand?->only('id', 'name', 'slug', 'logo'),
                    'store'  => $user->store?->only('id', 'name', 'slug'),
                ] : null,
            ],
            'stores' => $stores,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'order_payment_method' => fn () => $request->session()->get('order_payment_method'),
                'order_final_amount'   => fn () => $request->session()->get('order_final_amount'),
                'last_stopped_order_id' => fn () => $request->session()->get('last_stopped_order_id'),
                'last_stopped_order_time' => fn () => $request->session()->get('last_stopped_order_time'),
                'last_order_id'        => fn () => $request->session()->get('last_order_id'),
            ],
        ]);
    }
}
