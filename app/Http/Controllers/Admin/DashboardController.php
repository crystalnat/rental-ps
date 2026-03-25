<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DailyExpense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreInventory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $storeIds = $this->getStoreIds($user, $request);

        $stores = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        if ($storeIds->isEmpty()) {
            return Inertia::render('Admin/Dashboard/SelectStore', [
                'stores' => $stores,
            ]);
        }

        $today = now()->toDateString();
        $weekStart = now()->subDays(6)->toDateString();

        $stats = $this->buildStats($storeIds, $today);
        $chartSalesWeek = $this->buildChartSalesWeek($storeIds, $weekStart, $today);
        $chartIncomeExpenseWeek = $this->buildChartIncomeExpenseWeek($storeIds, $weekStart, $today);
        $topProducts = $this->buildTopProducts($storeIds, $today, 5);
        $lowStockAlerts = $this->buildLowStockAlerts($storeIds, 10);
        $stats['low_stock_count'] = count($lowStockAlerts);
        $perStoreSummary = $this->buildPerStoreSummary($storeIds, $today);
        $recentOrders = $this->buildRecentOrders($storeIds, 15);

        return Inertia::render('Admin/Dashboard', [
            'store'       => $this->resolveStoreForView($user, $request, $stores),
            'stores'      => $stores,
            'stats'       => $stats,
            'chart_sales_week' => $chartSalesWeek,
            'chart_income_expense_week' => $chartIncomeExpenseWeek,
            'top_products' => $topProducts,
            'low_stock_alerts' => $lowStockAlerts,
            'per_store_summary' => $perStoreSummary,
            'recent_orders' => $recentOrders,
        ]);
    }

    private function getStoreIds($user, Request $request): Collection
    {
        $storeId = $request->query('store') ?? $request->input('store');

        if ($storeId) {
            $store = Store::where('id', $storeId)
                ->where('brand_id', $user->brand_id)
                ->whereNull('deleted_at')
                ->first();
            if ($store) {
                return collect([$store->id]);
            }
        }

        if ($user->isOwner()) {
            return Store::where('brand_id', $user->brand_id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->pluck('id');
        }

        return $user->store_id ? collect([$user->store_id]) : collect();
    }

    private function resolveStoreForView($user, Request $request, array $stores): ?array
    {
        $storeId = $request->query('store') ?? $request->input('store');
        if ($storeId && $stores) {
            foreach ($stores as $s) {
                if ((int) $s['id'] === (int) $storeId) {
                    return $s;
                }
            }
        }
        return null; // null = Semua Toko
    }

    private function buildStats(Collection $storeIds, string $today): array
    {
        $todayRevenue = (float) Order::whereIn('store_id', $storeIds)
            ->whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('final_amount');

        $todayOrders = Order::whereIn('store_id', $storeIds)
            ->whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->count();

        $todayExpenses = (float) DailyExpense::whereIn('store_id', $storeIds)
            ->whereDate('expense_date', $today)
            ->sum('amount');

        $pendingOrders = Order::whereIn('store_id', $storeIds)
            ->whereIn('status', ['pending', 'confirmed', 'processing'])
            ->count();

        $monthRevenue = (float) Order::whereIn('store_id', $storeIds)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('payment_status', 'paid')
            ->sum('final_amount');

        $monthOrders = Order::whereIn('store_id', $storeIds)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('payment_status', 'paid')
            ->count();

        $monthExpenses = (float) DailyExpense::whereIn('store_id', $storeIds)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        $brandId = Store::whereIn('id', $storeIds)->value('brand_id');
        $totalProducts = Product::where('brand_id', $brandId)->where('is_active', true)->count();
        $totalCustomers = Customer::where('brand_id', $brandId)->count();
        $totalEmployees = User::where('brand_id', $brandId)->where('role', '!=', 'owner')->where('is_active', true)->count();

        return [
            'today_revenue'   => $todayRevenue,
            'today_orders'    => $todayOrders,
            'today_expenses'  => $todayExpenses,
            'today_net'       => $todayRevenue - $todayExpenses,
            'pending_orders'  => $pendingOrders,
            'month_revenue'   => $monthRevenue,
            'month_orders'    => $monthOrders,
            'month_expenses'  => $monthExpenses,
            'total_products'  => $totalProducts,
            'total_customers' => $totalCustomers,
            'total_employees' => $totalEmployees,
        ];
    }

    private function buildChartSalesWeek(Collection $storeIds, string $from, string $to): array
    {
        $salesByDay = Order::whereIn('store_id', $storeIds)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('DATE(created_at) as date, SUM(final_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $dates = collect();
        $start = \Carbon\Carbon::parse($from);
        $end = \Carbon\Carbon::parse($to);
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates->push($d->format('Y-m-d'));
        }

        return [
            'labels' => $dates->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()->toArray(),
            'data'   => $dates->map(fn ($d) => (float) ($salesByDay->get($d)?->total ?? 0))->values()->toArray(),
        ];
    }

    private function buildChartIncomeExpenseWeek(Collection $storeIds, string $from, string $to): array
    {
        $incomeByDay = Order::whereIn('store_id', $storeIds)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('DATE(created_at) as date, SUM(final_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $expenseByDay = DailyExpense::whereIn('store_id', $storeIds)
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->selectRaw('expense_date as date, SUM(amount) as total')
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->get()
            ->keyBy(fn ($r) => \Carbon\Carbon::parse($r->date)->format('Y-m-d'));

        $dates = collect();
        $start = \Carbon\Carbon::parse($from);
        $end = \Carbon\Carbon::parse($to);
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates->push($d->format('Y-m-d'));
        }

        return [
            'labels'  => $dates->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()->toArray(),
            'income'  => $dates->map(fn ($d) => (float) ($incomeByDay->get($d)?->total ?? 0))->values()->toArray(),
            'expenses'=> $dates->map(fn ($d) => (float) ($expenseByDay->get($d)?->total ?? 0))->values()->toArray(),
        ];
    }

    private function buildTopProducts(Collection $storeIds, string $today, int $limit): array
    {
        return OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.store_id', $storeIds)
            ->where('orders.payment_status', 'paid')
            ->whereDate('orders.created_at', $today)
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as total_qty, SUM(order_items.subtotal) as total_amount')
            ->groupBy('order_items.product_name')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'product_name' => $r->product_name,
                'total_qty'    => (float) $r->total_qty,
                'total_amount' => (float) $r->total_amount,
            ])
            ->values()
            ->toArray();
    }

    private function buildLowStockAlerts(Collection $storeIds, int $limit): array
    {
        return StoreInventory::query()
            ->whereIn('store_id', $storeIds)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->with(['product', 'store'])
            ->orderBy('current_stock')
            ->limit($limit)
            ->get()
            ->map(fn ($inv) => [
                'product_name'  => $inv->product?->name,
                'store_name'    => $inv->store?->name,
                'current_stock' => (float) $inv->current_stock,
                'min_stock'     => (float) $inv->min_stock,
                'unit'          => $inv->product?->unit ?? 'pcs',
            ])
            ->values()
            ->toArray();
    }

    private function buildPerStoreSummary(Collection $storeIds, string $today): array
    {
        if ($storeIds->count() <= 1) {
            return [];
        }

        $stores = Store::whereIn('id', $storeIds)->orderBy('name')->get();

        return $stores->map(function ($store) use ($today) {
            $revenue = (float) Order::where('store_id', $store->id)
                ->where('payment_status', 'paid')
                ->whereDate('created_at', $today)
                ->sum('final_amount');
            $orders = Order::where('store_id', $store->id)
                ->where('payment_status', 'paid')
                ->whereDate('created_at', $today)
                ->count();
            $expenses = (float) DailyExpense::where('store_id', $store->id)
                ->whereDate('expense_date', $today)
                ->sum('amount');

            return [
                'id'       => $store->id,
                'name'     => $store->name,
                'revenue'  => $revenue,
                'orders'   => $orders,
                'expenses' => $expenses,
                'net'      => $revenue - $expenses,
            ];
        })->toArray();
    }

    private function buildRecentOrders(Collection $storeIds, int $limit): array
    {
        return Order::with(['customer', 'table', 'cashier', 'store'])
            ->whereIn('store_id', $storeIds)
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn ($o) => [
                'id'             => $o->id,
                'order_code'     => $o->order_code,
                'status'         => $o->status,
                'payment_status' => $o->payment_status,
                'final_amount'   => (float) $o->final_amount,
                'type'           => $o->type,
                'customer_name'  => $o->customer?->name ?? 'Walk-in',
                'table_name'     => $o->table?->name,
                'store_name'     => $o->store?->name,
                'created_at'     => $o->created_at->format('Y-m-d H:i:s'),
            ])
            ->toArray();
    }
}
