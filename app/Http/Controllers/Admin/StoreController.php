<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(): Response
    {
        $brand = Auth::user()->brand;

        $stores = Store::withTrashed()
            ->where('brand_id', $brand->id)
            ->withCount(['orders', 'users', 'diningTables'])
            ->with(['orders' => function ($q) {
                $q->where('payment_status', 'paid')
                  ->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
            }])
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn ($store) => [
                'id'           => $store->id,
                'name'         => $store->name,
                'slug'         => $store->slug,
                'city'         => $store->city,
                'province'     => $store->province,
                'phone'        => $store->phone,
                'email'        => $store->email,
                'open_time'    => $store->open_time,
                'close_time'   => $store->close_time,
                'is_active'    => $store->is_active,
                'deleted_at'   => $store->deleted_at,
                'orders_count' => $store->orders_count,
                'users_count'  => $store->users_count,
                'tables_count' => $store->dining_tables_count,
                'month_revenue' => $store->orders->sum('final_amount'),
            ]);

        return Inertia::render('Admin/Stores/Index', [
            'stores' => $stores,
            'brand'  => $brand->only('id', 'name', 'slug'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Stores/Form', [
            'store' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $brandId = Auth::user()->brand_id;

        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:100'],
            'address'                => ['nullable', 'string', 'max:500'],
            'city'                   => ['nullable', 'string', 'max:100'],
            'province'               => ['nullable', 'string', 'max:100'],
            'postal_code'            => ['nullable', 'string', 'max:10'],
            'phone'                  => ['nullable', 'string', 'max:20'],
            'email'                  => ['nullable', 'email', 'max:100'],
            'open_time'              => ['nullable', 'date_format:H:i'],
            'close_time'             => ['nullable', 'date_format:H:i'],
            'receipt_print_enabled'  => ['sometimes', 'boolean'],
        ]);

        $slug = $this->generateUniqueSlug($data['name']);

        $data['receipt_print_enabled'] = $request->boolean('receipt_print_enabled');

        Store::create(array_merge($data, [
            'brand_id'  => $brandId,
            'slug'      => $slug,
            'is_active' => true,
        ]));

        return redirect()->route('admin.stores.index')
            ->with('success', "Toko \"{$data['name']}\" berhasil ditambahkan.");
    }

    public function edit(Store $store): Response
    {
        $this->authorizeStore($store);

        // receipt_print_enabled dipaksa boolean agar Inertia/Vue selalu terisi konsisten.
        return Inertia::render('Admin/Stores/Form', [
            'store' => array_merge(
                $store->only([
                    'id', 'name', 'slug', 'address', 'city', 'province',
                    'postal_code', 'phone', 'email', 'open_time', 'close_time', 'is_active',
                ]),
                [
                    'receipt_print_enabled' => (bool) $store->receipt_print_enabled,
                ]
            ),
        ]);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $this->authorizeStore($store);

        // DEBUG: Log request sebelum validasi
        \Log::info('Store Update Request:', [
            'all' => $request->all(),
            'receipt_print_enabled' => $request->input('receipt_print_enabled'),
            'boolean_receipt' => $request->boolean('receipt_print_enabled'),
        ]);

        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:100'],
            'address'                => ['nullable', 'string', 'max:500'],
            'city'                   => ['nullable', 'string', 'max:100'],
            'province'               => ['nullable', 'string', 'max:100'],
            'postal_code'            => ['nullable', 'string', 'max:10'],
            'phone'                  => ['nullable', 'string', 'max:20'],
            'email'                  => ['nullable', 'email', 'max:100'],
            'open_time'              => ['nullable', 'date_format:H:i'],
            'close_time'             => ['nullable', 'date_format:H:i'],
            'receipt_print_enabled'  => ['required', 'boolean'],
        ]);

        if ($data['name'] !== $store->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $store->id);
        }

        // Pakai input mentah: lebih konsisten untuk JSON true/false/1/0 dari Inertia.
        $data['receipt_print_enabled'] = $request->boolean('receipt_print_enabled');

        \Log::info('Data before update:', ['data' => $data]);

        $store->update($data);

        \Log::info('Store after update:', [
            'receipt_print_enabled' => $store->receipt_print_enabled,
            'fresh' => $store->fresh()->receipt_print_enabled,
        ]);

        return redirect()->route('admin.stores.index')
            ->with('success', "Toko \"{$store->name}\" berhasil diperbarui.");
    }

    public function toggleActive(Store $store): RedirectResponse
    {
        $this->authorizeStore($store);

        $store->update(['is_active' => ! $store->is_active]);

        $status = $store->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Toko \"{$store->name}\" berhasil {$status}.");
    }

    public function destroy(Store $store): RedirectResponse
    {
        $this->authorizeStore($store);

        $hasActiveOrders = Order::where('store_id', $store->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->exists();

        if ($hasActiveOrders) {
            return back()->with('error', 'Tidak bisa menghapus toko yang masih memiliki pesanan aktif.');
        }

        $store->delete();

        return redirect()->route('admin.stores.index')
            ->with('success', "Toko \"{$store->name}\" berhasil dihapus.");
    }

    private function authorizeStore(Store $store): void
    {
        abort_unless($store->brand_id === Auth::user()->brand_id, 403);
    }

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug  = Str::slug($name);
        $count = 1;

        while (
            Store::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = Str::slug($name) . '-' . $count++;
        }

        return $slug;
    }
}
