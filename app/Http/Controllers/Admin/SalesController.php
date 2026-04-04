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
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
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
        $topProductRows = $topProductsQuery
            ->select([
                'order_items.product_id',
                'order_items.product_name',
                'order_items.quantity',
                'order_items.subtotal',
                'products.is_rental_package',
                'products.included_items_json',
            ])
            ->get();

        $agg = [];
        foreach ($topProductRows as $row) {
            $name = $row->product_name;
            if (!isset($agg[$name])) $agg[$name] = ['total_qty' => 0, 'total_amount' => 0];
            $agg[$name]['total_qty']    += $row->quantity;
            $agg[$name]['total_amount'] += $row->subtotal;
            if ($row->is_rental_package && $row->included_items_json) {
                $included = is_array($row->included_items_json)
                    ? $row->included_items_json
                    : json_decode($row->included_items_json, true) ?? [];
                foreach ($included as $inc) {
                    $incName = $inc['product_name'] ?? null;
                    if (!$incName) continue;
                    if (!isset($agg[$incName])) $agg[$incName] = ['total_qty' => 0, 'total_amount' => 0];
                    $agg[$incName]['total_qty'] += ($inc['qty'] ?? 1) * $row->quantity;
                }
            }
        }
        $topProducts = collect($agg)
            ->map(fn ($v, $k) => ['product_name' => $k, 'total_qty' => (float) $v['total_qty'], 'total_amount' => (float) $v['total_amount']])
            ->sortByDesc('total_qty')
            ->take(15)
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
