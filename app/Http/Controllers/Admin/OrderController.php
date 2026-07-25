<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);

        $storesQuery = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true);

        if ($user->role !== 'owner' && $user->role !== 'admin') {
            $storesQuery->where('id', $user->store_id);
        }

        $stores = $storesQuery->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        if (! $store) {
            return Inertia::render('Admin/Orders/SelectStore', [
                'stores' => $stores,
            ]);
        }

        // Pesanan = order dari meja (QR) saja, bukan dari kasir saat transaksi
        $query = Order::with(['store', 'cashier', 'customer', 'table', 'items'])
            ->where('store_id', $store->id)
            ->whereNull('cashier_id')
            ->whereNotNull('table_id');

        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        } else {
            $query->whereIn('payment_status', ['unpaid', 'paid']);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', '%' . $search . '%')
                    ->orWhereHas('cashier', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%'))
                    ->orWhereHas('table', fn ($q) => $q->where('name', 'like', '%' . $search . '%'));
            });
        }

        $sortBy = $request->sort ?? 'created_at';
        $sortDir = $request->dir === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['order_code', 'created_at', 'type', 'final_amount', 'item_count'];
        if (! in_array($sortBy, $allowedSort)) {
            $sortBy = 'created_at';
        }
        if ($sortBy === 'item_count') {
            $query->withCount('items')->orderBy('items_count', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $paymentMethods = Order::where('store_id', $store->id)
            ->where('payment_status', 'paid')
            ->distinct()
            ->pluck('payment_method')
            ->filter()
            ->values()
            ->map(fn ($c) => ['value' => $c, 'label' => ucfirst(str_replace('_', ' ', $c))])
            ->toArray();

        $orders = $query->paginate(25)
            ->withQueryString()
            ->through(fn ($order) => [
                'id'              => $order->id,
                'order_code'      => $order->order_code,
                'type'            => $order->type,
                'status'          => $order->status,
                'payment_status'  => $order->payment_status,
                'payment_method'  => $order->payment_method,
                'subtotal'       => (float) $order->subtotal,
                'discount_amount'=> (float) $order->discount_amount,
                'final_amount'   => (float) $order->final_amount,
                'item_count'     => $order->items->count(),
                'table_name'     => $order->table?->name,
                'cashier_name'   => $order->cashier?->name,
                'customer_name'  => $order->customer?->name,
                'created_at'     => $order->created_at->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'store'  => $store->only('id', 'name', 'slug'),
            'stores' => $stores,
            'orders' => $orders,
            'filters' => [
                'date_from'       => $request->date_from,
                'date_to'         => $request->date_to,
                'type'            => $request->type ?? 'all',
                'status'          => $request->status ?? 'all',
                'payment_status'  => $request->payment_status ?? 'all',
                'payment_method'  => $request->payment_method ?? 'all',
                'search'          => $request->search ?? '',
                'sort'            => $sortBy,
                'dir'             => $sortDir,
            ],
            'payment_method_options' => array_merge(
                [['value' => 'all', 'label' => 'Semua Pembayaran']],
                $paymentMethods,
            ),
            'status_options' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'confirmed', 'label' => 'Dikonfirmasi'],
                ['value' => 'processing', 'label' => 'Diproses'],
                ['value' => 'ready', 'label' => 'Siap'],
                ['value' => 'done', 'label' => 'Selesai'],
                ['value' => 'cancelled', 'label' => 'Batal'],
            ],
            'payment_status_options' => [
                ['value' => 'all', 'label' => 'Semua'],
                ['value' => 'unpaid', 'label' => 'Belum Bayar'],
                ['value' => 'paid', 'label' => 'Sudah Bayar'],
            ],
            'payment_methods' => PaymentMethod::where('brand_id', $store->brand_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($pm) => [
                    'id'                  => $pm->id,
                    'name'                => $pm->name,
                    'code'                => $pm->code,
                    'requires_cash_input'  => $pm->requires_cash_input,
                ])
                ->values()
                ->toArray(),
        ]);
    }

    public function detail(Order $order, Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            if ($order->store_id !== $user->store_id) {
                abort(403);
            }
        } elseif ($order->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        $order->load(['store', 'cashier', 'customer', 'table', 'items.modifiers']);

        return response()->json([
            'id'              => $order->id,
            'order_code'      => $order->order_code,
            'type'            => $order->type,
            'status'          => $order->status,
            'payment_status'  => $order->payment_status,
            'payment_method'  => $order->payment_method,
            'subtotal'        => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'final_amount'    => (float) $order->final_amount,
            'cash_received'   => $order->cash_received ? (float) $order->cash_received : null,
            'change_amount'   => $order->change_amount ? (float) $order->change_amount : null,
            'notes'           => $order->notes,
            'store_name'      => $order->store?->name,
            'table_name'      => $order->table?->name,
            'cashier_name'    => $order->cashier?->name,
            'customer_name'   => $order->customer?->name,
            'customer_phone'  => $order->customer?->phone,
            'customer_email'  => $order->customer?->email,
            'created_at'      => $order->created_at->format('d M Y H:i'),
            'is_rental'            => (bool) $order->is_rental,
            'rental_started_at'    => $order->rental_started_at?->format('H:i'),
            'rental_end_at'        => $order->rental_end_at?->format('H:i'),
            'rental_duration_minutes' => $order->rental_duration_minutes,
            'items'           => $order->items->map(fn ($i) => [
                'product_name' => $i->product_name,
                'quantity'     => (float) $i->quantity,
                'unit'         => $i->unit,
                'unit_price'   => (float) $i->unit_price,
                'subtotal'     => (float) $i->subtotal,
                'modifiers'    => $i->modifiers->map(fn($m) => [
                    'name' => $m->modifier_option_name,
                    'price_extra' => (float) $m->price_extra,
                ]),
            ]),
        ]);
    }

    public function show(Order $order, Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            if ($order->store_id !== $user->store_id) {
                abort(403);
            }
        } elseif ($order->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        $order->load(['store', 'cashier', 'customer', 'table', 'items.modifiers']);

        $paymentMethods = PaymentMethod::where('brand_id', $order->store->brand_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($pm) => [
                'id'                  => $pm->id,
                'name'                => $pm->name,
                'code'                => $pm->code,
                'requires_cash_input' => $pm->requires_cash_input,
            ]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id'              => $order->id,
                'order_code'      => $order->order_code,
                'type'            => $order->type,
                'status'          => $order->status,
                'payment_method'  => $order->payment_method,
                'payment_status'  => $order->payment_status,
                'subtotal'        => (float) $order->subtotal,
                'discount_amount' => (float) $order->discount_amount,
                'tax_amount'      => (float) $order->tax_amount,
                'final_amount'    => (float) $order->final_amount,
                'cash_received'   => $order->cash_received ? (float) $order->cash_received : null,
                'change_amount'   => $order->change_amount ? (float) $order->change_amount : null,
                'notes'           => $order->notes,
                'table_name'      => $order->table?->name,
                'cashier_name'    => $order->cashier?->name,
                'customer_name'   => $order->customer?->name,
                'customer_phone'  => $order->customer?->phone,
                'customer_email'  => $order->customer?->email,
                'created_at'      => $order->created_at->format('d M Y H:i'),
                'paid_at'         => $order->paid_at?->format('d M Y H:i'),
                'store_name'      => $order->store->name,
                'items'           => $order->items->map(fn ($i) => [
                    'product_name' => $i->product_name,
                    'quantity'     => (float) $i->quantity,
                    'unit'         => $i->unit,
                    'unit_price'   => (float) $i->unit_price,
                    'subtotal'     => (float) $i->subtotal,
                    'modifiers'    => $i->modifiers->map(fn($m) => [
                        'name' => $m->modifier_option_name,
                        'price_extra' => (float) $m->price_extra,
                    ]),
                ]),
            ],
            'payment_methods' => $paymentMethods,
        ]);
    }

    public function receipt(Order $order): Response
    {
        $this->authorizeOrder($order);
        $order->load(['store', 'cashier', 'customer', 'table', 'items.modifiers']);

        return Inertia::render('Admin/Orders/Receipt', [
            'order' => $this->formatOrderForPrint($order),
            'store' => $this->formatStoreForPrint($order->store),
        ]);
    }

    public function invoice(Order $order): Response
    {
        $this->authorizeOrder($order);
        $order->load(['store', 'cashier', 'customer', 'table', 'items.modifiers']);

        return Inertia::render('Admin/Orders/Invoice', [
            'order' => $this->formatOrderForPrint($order),
            'store' => $this->formatStoreForPrint($order->store),
        ]);
    }

    private function authorizeOrder(Order $order): void
    {
        $user = Auth::user();
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            if ($order->store_id !== $user->store_id) {
                abort(403);
            }
        } elseif ($order->store->brand_id !== $user->brand_id) {
            abort(403);
        }
    }

    private function formatOrderForPrint(Order $order): array
    {
        return [
            'id'              => $order->id,
            'order_code'      => $order->order_code,
            'type'            => $order->type,
            'status'          => $order->status,
            'payment_method'  => $order->payment_method,
            'payment_status'  => $order->payment_status,
            'subtotal'        => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'tax_rate'        => (float) $order->tax_rate,
            'tax_amount'      => (float) $order->tax_amount,
            'final_amount'    => (float) $order->final_amount,
            'cash_received'   => $order->cash_received ? (float) $order->cash_received : null,
            'change_amount'   => $order->change_amount ? (float) $order->change_amount : null,
            'notes'           => $order->notes,
            'table_name'      => $order->table?->name,
            'cashier_name'    => $order->cashier?->name,
            'customer_name'   => $order->customer?->name,
            'customer_phone'  => $order->customer?->phone,
            'customer_email'  => $order->customer?->email,
            'created_at'      => $order->created_at->format('d M Y H:i'),
            'paid_at'         => $order->paid_at?->format('d M Y H:i'),
            'is_rental'       => (bool) $order->is_rental,
            'rental_duration_minutes' => $order->rental_duration_minutes,
            'items'           => $order->items->map(fn ($i) => [
                'product_name'    => $i->product_name,
                'quantity'        => (float) $i->quantity,
                'unit'            => $i->unit,
                'unit_price'      => (float) $i->unit_price,
                'discount_amount' => (float) $i->discount_amount,
                'subtotal'        => (float) $i->subtotal,
                'modifiers'       => $i->modifiers->map(fn($m) => [
                    'name' => $m->modifier_option_name,
                    'price_extra' => (float) $m->price_extra,
                ]),
            ]),
        ];
    }

    private function formatStoreForPrint($store): array
    {
        return [
            'name'     => $store->name,
            'address'  => $store->address,
            'city'     => $store->city,
            'phone'    => $store->phone,
            'email'    => $store->email,
            'logo'     => $store->logo ? \Storage::disk('public')->url($store->logo) : null,
        ];
    }

    public function pay(Order $order, Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            if ($order->store_id !== $user->store_id) {
                abort(403);
            }
        } elseif ($order->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        if ($order->payment_status === 'paid') {
            return back()->with('error', 'Pesanan sudah dibayar.');
        }

        $validCodes = PaymentMethod::where('brand_id', $order->store->brand_id)
            ->where('is_active', true)
            ->pluck('code')
            ->toArray();

        $data = $request->validate([
            'payment_method' => ['required', \Illuminate\Validation\Rule::in($validCodes)],
            'cash_received'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $paymentMethod = PaymentMethod::where('brand_id', $order->store->brand_id)
            ->where('code', $data['payment_method'])
            ->first();
        $requiresCash = $paymentMethod?->requires_cash_input ?? ($data['payment_method'] === 'cash');
        $finalAmount = (float) $order->final_amount;

        $cashReceived = $requiresCash
            ? (float) ($data['cash_received'] ?? $finalAmount)
            : $finalAmount;
        $changeAmount = $requiresCash ? max(0, $cashReceived - $finalAmount) : 0;

        if ($requiresCash && $cashReceived < $finalAmount) {
            return back()->withErrors(['cash_received' => 'Jumlah tunai kurang dari total.']);
        }

        // Rental dibayar di awal: sesi masih berjalan, jadi jangan tutup order.
        // Order baru ditutup (done) saat Stop Rental atau saat waktu habis.
        $isRunningRental = $order->is_rental
            && $order->rental_end_at
            && \Carbon\Carbon::parse($order->rental_end_at)->isFuture()
            && ! in_array($order->status, ['ready', 'done', 'cancelled']);

        $order->update([
            'payment_status' => 'paid',
            'payment_method' => $data['payment_method'],
            'cashier_id'     => $user->id,
            'cash_received'  => $requiresCash ? $cashReceived : null,
            'change_amount'  => $changeAmount > 0 ? $changeAmount : null,
            'paid_at'        => now(),
            'status'         => $isRunningRental ? $order->status : 'done',
            'completed_at'   => $isRunningRental ? null : now(),
        ]);

        return back()
            ->with('success', "Pembayaran {$order->order_code} berhasil diterima.")
            ->with('last_order_id', $order->id);
    }

    public function update(Order $order, Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            if ($order->store_id !== $user->store_id) {
                abort(403);
            }
        } elseif ($order->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        if ($order->status === 'done' || $order->status === 'cancelled') {
            return back()->with('error', 'Pesanan tidak dapat diubah.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,processing,ready'],
        ]);

        // Bayar di awal: pesanan hanya boleh diproses (maju dari pending) setelah lunas.
        if ($data['status'] !== 'pending' && $order->payment_status !== 'paid') {
            return back()->with('error', 'Pesanan belum dibayar. Terima pembayaran dulu sebelum diproses.');
        }

        $order->update(['status' => $data['status']]);

        return back()->with('success', "Status pesanan {$order->order_code} diperbarui.");
    }

    public function destroy(Order $order, Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            if ($order->store_id !== $user->store_id) {
                abort(403);
            }
        } elseif ($order->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        if ($order->payment_status === 'paid') {
            return back()->with('error', 'Pesanan yang sudah dibayar tidak dapat dibatalkan.');
        }

        // Sudah dibatalkan → hapus dari daftar (soft delete). Belum → batalkan dulu.
        if ($order->status === 'cancelled') {
            $order->delete();

            return back()->with('success', "Pesanan {$order->order_code} dihapus.");
        }

        $order->update([
            'status'             => 'cancelled',
            'cancelled_at'       => now(),
            'cancellation_reason'=> $request->input('reason'),
        ]);

        return back()->with('success', "Pesanan {$order->order_code} dibatalkan.");
    }

    private function resolveStore($user, Request $request): ?Store
    {
        $storeId = $request->query('store') ?? $request->input('store');

        // Strictly enforce assigned store for non-owners/admins
        if ($user->role !== 'owner' && $user->role !== 'admin') {
            return $user->store_id ? Store::find($user->store_id) : null;
        }

        if ($storeId) {
            $store = Store::where('id', $storeId)
                ->where('brand_id', $user->brand_id)
                ->whereNull('deleted_at')
                ->first();
            if ($store) {
                return $store;
            }
        }

        if ($user->store_id) {
            return Store::find($user->store_id);
        }

        return Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
    }
}
