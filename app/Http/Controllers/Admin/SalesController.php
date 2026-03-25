<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);

        $stores = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        if (! $store) {
            return Inertia::render('Admin/Sales/SelectStore', [
                'stores' => $stores,
            ]);
        }

        $query = Order::with(['store', 'cashier', 'customer', 'table', 'items'])
            ->where('store_id', $store->id)
            ->where('payment_status', 'paid');

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

        $topProductsQuery = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.store_id', $store->id)
            ->where('orders.payment_status', 'paid');
        if ($request->filled('date_from')) {
            $topProductsQuery->whereDate('orders.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $topProductsQuery->whereDate('orders.created_at', '<=', $request->date_to);
        }
        if ($request->filled('type') && $request->type !== 'all') {
            $topProductsQuery->where('orders.type', $request->type);
        }
        $topProducts = $topProductsQuery
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as total_qty, SUM(order_items.subtotal) as total_amount')
            ->groupBy('order_items.product_name')
            ->orderByDesc('total_qty')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'product_name' => $r->product_name,
                'total_qty'    => (float) $r->total_qty,
                'total_amount' => (float) $r->total_amount,
            ])
            ->values()
            ->toArray();

        $orders = $query->paginate(25)
            ->withQueryString()
            ->through(fn ($order) => [
                'id'             => $order->id,
                'order_code'     => $order->order_code,
                'type'           => $order->type,
                'status'         => $order->status,
                'payment_method' => $order->payment_method,
                'subtotal'       => (float) $order->subtotal,
                'discount_amount'=> (float) $order->discount_amount,
                'final_amount'   => (float) $order->final_amount,
                'item_count'     => $order->items->count(),
                'table_name'     => $order->table?->name,
                'cashier_name'   => $order->cashier?->name,
                'customer_name'  => $order->customer?->name,
                'created_at'     => $order->created_at->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Sales/Index', [
            'store'  => $store->only('id', 'name', 'slug'),
            'stores' => $stores,
            'orders' => $orders,
            'filters' => [
                'date_from'      => $request->date_from,
                'date_to'        => $request->date_to,
                'type'           => $request->type ?? 'all',
                'payment_method' => $request->payment_method ?? 'all',
                'search'         => $request->search ?? '',
                'sort'           => $sortBy,
                'dir'            => $sortDir,
            ],
            'payment_method_options' => array_merge(
                [['value' => 'all', 'label' => 'Semua Pembayaran']],
                $paymentMethods,
            ),
            'chart_top_products' => $topProducts,
        ]);
    }

    private function resolveStore($user, Request $request): ?Store
    {
        $storeId = $request->query('store') ?? $request->input('store');

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
