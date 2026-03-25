<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $paymentMethods = PaymentMethod::where('brand_id', $user->brand_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($pm) => [
                'id'                  => $pm->id,
                'name'                => $pm->name,
                'code'                => $pm->code,
                'sort_order'          => $pm->sort_order,
                'is_active'           => $pm->is_active,
                'requires_cash_input' => $pm->requires_cash_input,
                'qrcode_image'        => $pm->qrcode_image ? Storage::disk('public')->url($pm->qrcode_image) : null,
                'account_name'        => $pm->account_name,
                'account_number'      => $pm->account_number,
            ]);

        return Inertia::render('Admin/Settings/Index', [
            'payment_methods' => $paymentMethods,
        ]);
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:50'],
            'code'               => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique('payment_methods')->where('brand_id', $user->brand_id)],
            'sort_order'         => ['nullable', 'integer', 'min:0'],
            'is_active'          => ['boolean'],
            'requires_cash_input' => ['boolean'],
            'qrcode_image'        => [Rule::requiredIf($request->input('code') === 'qris'), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'account_name'       => [Rule::requiredIf(in_array($request->input('code'), ['bank_transfer', 'e_wallet'])), 'nullable', 'string', 'max:100'],
            'account_number'     => [Rule::requiredIf(in_array($request->input('code'), ['bank_transfer', 'e_wallet'])), 'nullable', 'string', 'max:50'],
        ]);

        $qrcodePath = null;
        if ($request->hasFile('qrcode_image')) {
            $qrcodePath = $request->file('qrcode_image')->store('payment-qrcodes', 'public');
        }

        PaymentMethod::create([
            'brand_id'            => $user->brand_id,
            'name'                => $data['name'],
            'code'                => $data['code'],
            'sort_order'           => $data['sort_order'] ?? 0,
            'is_active'           => $data['is_active'] ?? true,
            'requires_cash_input' => $data['requires_cash_input'] ?? false,
            'qrcode_image'        => $qrcodePath,
            'account_name'        => $data['account_name'] ?? null,
            'account_number'      => $data['account_number'] ?? null,
        ]);

        return back()->with('success', 'Metode pembayaran berhasil ditambahkan.');
    }

    public function updatePaymentMethod(PaymentMethod $paymentMethod, Request $request): RedirectResponse
    {
        $this->authorizeBrand($paymentMethod);

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:50'],
            'code'               => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique('payment_methods')->where('brand_id', $paymentMethod->brand_id)->ignore($paymentMethod->id)],
            'sort_order'         => ['nullable', 'integer', 'min:0'],
            'is_active'          => ['boolean'],
            'requires_cash_input' => ['boolean'],
            'qrcode_image'        => [
                Rule::requiredIf($paymentMethod->code === 'qris' && ! $request->boolean('remove_qrcode') && ! $paymentMethod->qrcode_image),
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
            'account_name'       => [Rule::requiredIf(in_array($paymentMethod->code, ['bank_transfer', 'e_wallet'])), 'nullable', 'string', 'max:100'],
            'account_number'     => [Rule::requiredIf(in_array($paymentMethod->code, ['bank_transfer', 'e_wallet'])), 'nullable', 'string', 'max:50'],
            'remove_qrcode'      => ['nullable', 'boolean'],
        ]);

        $update = [
            'name'                => $data['name'],
            'code'                => $data['code'],
            'sort_order'           => $data['sort_order'] ?? 0,
            'is_active'           => $data['is_active'] ?? true,
            'requires_cash_input' => $data['requires_cash_input'] ?? false,
            'account_name'        => $data['account_name'] ?? null,
            'account_number'      => $data['account_number'] ?? null,
        ];

        if ($request->boolean('remove_qrcode') && $paymentMethod->qrcode_image) {
            Storage::disk('public')->delete($paymentMethod->qrcode_image);
            $update['qrcode_image'] = null;
        } elseif ($request->hasFile('qrcode_image')) {
            if ($paymentMethod->qrcode_image) {
                Storage::disk('public')->delete($paymentMethod->qrcode_image);
            }
            $update['qrcode_image'] = $request->file('qrcode_image')->store('payment-qrcodes', 'public');
        }

        $paymentMethod->update($update);

        return back()->with('success', 'Metode pembayaran berhasil diperbarui.');
    }

    public function destroyPaymentMethod(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorizeBrand($paymentMethod);

        $hasOrders = \App\Models\Order::where('payment_method', $paymentMethod->code)->exists();
        if ($hasOrders) {
            return back()->with('error', 'Metode pembayaran tidak dapat dihapus karena sudah digunakan dalam transaksi.');
        }

        $paymentMethod->delete();

        return back()->with('success', 'Metode pembayaran berhasil dihapus.');
    }

    private function authorizeBrand(PaymentMethod $paymentMethod): void
    {
        abort_unless($paymentMethod->brand_id === Auth::user()->brand_id, 403);
    }
}
