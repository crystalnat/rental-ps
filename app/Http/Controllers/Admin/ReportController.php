<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyExpense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    private const EXPENSE_CATEGORY_LABELS = [
        'purchase'    => 'Pembelian',
        'supplies'    => 'Perlengkapan',
        'utilities'   => 'Listrik/Utilities',
        'maintenance' => 'Pemeliharaan',
        'salary'      => 'Gaji',
        'other'       => 'Lainnya',
    ];

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $storeIds = $this->getStoreIds($user);

        $stores = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $dateFrom = $request->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo = $request->date_to ?? now()->toDateString();

        // === RINGKASAN KESELURUHAN ===
        $overall = $this->buildOverallSummary($storeIds, $dateFrom, $dateTo);

        // === PER TOKO ===
        $perStore = [];
        foreach ($stores as $store) {
            if (! $storeIds->contains($store->id)) {
                continue;
            }
            $perStore[] = [
                'id'         => $store->id,
                'name'       => $store->name,
                'slug'       => $store->slug,
                'income'     => (float) Order::where('store_id', $store->id)->where('payment_status', 'paid')
                    ->whereDate('created_at', '>=', $dateFrom)->whereDate('created_at', '<=', $dateTo)->sum('final_amount'),
                'expenses'   => (float) DailyExpense::where('store_id', $store->id)
                    ->whereDate('expense_date', '>=', $dateFrom)->whereDate('expense_date', '<=', $dateTo)->sum('amount'),
                'order_count'=> Order::where('store_id', $store->id)->where('payment_status', 'paid')
                    ->whereDate('created_at', '>=', $dateFrom)->whereDate('created_at', '<=', $dateTo)->count(),
                'hpp'          => $this->hppForStores(collect([$store->id]), $dateFrom, $dateTo),
                'gross_profit'=> $this->grossProfitForStores(collect([$store->id]), $dateFrom, $dateTo),
                'chart_sales' => $this->buildChartSales($store->id, $dateFrom, $dateTo),
                'chart_expense_by_category' => $this->buildChartExpenseByCategory($store->id, $dateFrom, $dateTo),
                'top_products' => $this->buildTopProducts($store->id, $dateFrom, $dateTo, 15),
                'sales_by_cashier' => $this->buildSalesByCashier($store->id, $dateFrom, $dateTo),
                'sales_by_type' => $this->buildSalesByType($store->id, $dateFrom, $dateTo),
                'sales_by_payment' => $this->buildSalesByPayment($store->id, $dateFrom, $dateTo),
            ];
        }

        return Inertia::render('Admin/Reports/Index', [
            'stores'     => $stores->toArray(),
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'overall'    => $overall,
            'per_store'  => $perStore,
        ]);
    }

    private function getStoreIds($user): Collection
    {
        $base = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true);

        if ($user->isOwner()) {
            return $base->pluck('id');
        }
        if ($user->store_id) {
            return $base->where('id', $user->store_id)->pluck('id');
        }
        return $base->pluck('id');
    }

    private function buildOverallSummary(Collection $storeIds, string $dateFrom, string $dateTo): array
    {
        if ($storeIds->isEmpty()) {
            return $this->emptyOverall();
        }

        $income = (float) Order::whereIn('store_id', $storeIds)->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)->whereDate('created_at', '<=', $dateTo)->sum('final_amount');
        $expenses = (float) DailyExpense::whereIn('store_id', $storeIds)
            ->whereDate('expense_date', '>=', $dateFrom)->whereDate('expense_date', '<=', $dateTo)->sum('amount');
        $orderCount = Order::whereIn('store_id', $storeIds)->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)->whereDate('created_at', '<=', $dateTo)->count();
        $grossProfit = $this->grossProfitForStores($storeIds, $dateFrom, $dateTo);
        $hpp = $this->hppForStores($storeIds, $dateFrom, $dateTo);

        return [
            'income'       => $income,
            'hpp'          => $hpp,
            'expenses'     => $expenses,
            'net'          => $income - $expenses,
            'gross_profit' => $grossProfit,
            'order_count'  => $orderCount,
            'chart_sales'  => $this->buildChartSalesForStores($storeIds, $dateFrom, $dateTo),
            'chart_expense_by_category' => $this->buildChartExpenseByCategoryForStores($storeIds, $dateFrom, $dateTo),
            'chart_sales_by_store' => $this->buildChartSalesByStore($storeIds, $dateFrom, $dateTo),
            'chart_expense_by_store' => $this->buildChartExpenseByStore($storeIds, $dateFrom, $dateTo),
            'top_products' => $this->buildTopProductsForStores($storeIds, $dateFrom, $dateTo, 20),
            'sales_by_cashier' => $this->buildSalesByCashierForStores($storeIds, $dateFrom, $dateTo),
            'sales_by_type' => $this->buildSalesByTypeForStores($storeIds, $dateFrom, $dateTo),
            'sales_by_payment' => $this->buildSalesByPaymentForStores($storeIds, $dateFrom, $dateTo),
            'expense_by_category_detail' => $this->buildExpenseByCategoryDetail($storeIds, $dateFrom, $dateTo),
        ];
    }

    private function emptyOverall(): array
    {
        return [
            'income'       => 0,
            'hpp'          => 0,
            'expenses'     => 0,
            'net'          => 0,
            'gross_profit' => 0,
            'order_count'  => 0,
            'chart_sales'  => ['labels' => [], 'data' => []],
            'chart_expense_by_category' => ['labels' => [], 'amounts' => []],
            'chart_sales_by_store' => ['labels' => [], 'data' => []],
            'chart_expense_by_store' => ['labels' => [], 'data' => []],
            'top_products' => [],
            'sales_by_cashier' => [],
            'sales_by_type' => [],
            'sales_by_payment' => [],
            'expense_by_category_detail' => [],
        ];
    }

    private function grossProfitForStores(Collection $storeIds, string $dateFrom, string $dateTo): float
    {
        return (float) (OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.store_id', $storeIds)
            ->where('orders.payment_status', 'paid')
            ->whereDate('orders.created_at', '>=', $dateFrom)
            ->whereDate('orders.created_at', '<=', $dateTo)
            ->selectRaw('SUM((order_items.unit_price - order_items.buy_price) * order_items.quantity - COALESCE(order_items.discount_amount, 0)) as total')
            ->value('total') ?? 0);
    }

    private function hppForStores(Collection $storeIds, string $dateFrom, string $dateTo): float
    {
        return (float) (OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.store_id', $storeIds)
            ->where('orders.payment_status', 'paid')
            ->whereDate('orders.created_at', '>=', $dateFrom)
            ->whereDate('orders.created_at', '<=', $dateTo)
            ->selectRaw('SUM(order_items.buy_price * order_items.quantity) as total')
            ->value('total') ?? 0);
    }

    private function buildChartSales(int $storeId, string $dateFrom, string $dateTo): array
    {
        return $this->buildChartSalesForStores(collect([$storeId]), $dateFrom, $dateTo);
    }

    private function buildChartSalesForStores(Collection $storeIds, string $dateFrom, string $dateTo): array
    {
        $salesByDay = Order::whereIn('store_id', $storeIds)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('DATE(created_at) as date, SUM(final_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $dates = collect();
        $start = \Carbon\Carbon::parse($dateFrom);
        $end = \Carbon\Carbon::parse($dateTo);
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates->push($d->format('Y-m-d'));
        }

        return [
            'labels' => $dates->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()->toArray(),
            'data'   => $dates->map(fn ($d) => (float) ($salesByDay->get($d)?->total ?? 0))->values()->toArray(),
        ];
    }

    private function buildChartSalesByStore(Collection $storeIds, string $dateFrom, string $dateTo): array
    {
        $stores = Store::whereIn('id', $storeIds)->orderBy('name')->get();
        $data = Order::whereIn('store_id', $storeIds)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('store_id, SUM(final_amount) as total')
            ->groupBy('store_id')
            ->get()
            ->keyBy('store_id');

        return [
            'labels' => $stores->pluck('name')->toArray(),
            'data'   => $stores->map(fn ($s) => (float) ($data->get($s->id)?->total ?? 0))->toArray(),
        ];
    }

    private function buildChartExpenseByStore(Collection $storeIds, string $dateFrom, string $dateTo): array
    {
        $stores = Store::whereIn('id', $storeIds)->orderBy('name')->get();
        $data = DailyExpense::whereIn('store_id', $storeIds)
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->selectRaw('store_id, SUM(amount) as total')
            ->groupBy('store_id')
            ->get()
            ->keyBy('store_id');

        return [
            'labels' => $stores->pluck('name')->toArray(),
            'data'   => $stores->map(fn ($s) => (float) ($data->get($s->id)?->total ?? 0))->toArray(),
        ];
    }

    private function buildChartExpenseByCategory(int $storeId, string $dateFrom, string $dateTo): array
    {
        return $this->buildChartExpenseByCategoryForStores(collect([$storeId]), $dateFrom, $dateTo);
    }

    private function buildChartExpenseByCategoryForStores(Collection $storeIds, string $dateFrom, string $dateTo): array
    {
        $data = DailyExpense::whereIn('store_id', $storeIds)
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        if ($data->isEmpty()) {
            return ['labels' => [], 'amounts' => []];
        }

        return [
            'labels'  => $data->map(fn ($r) => self::EXPENSE_CATEGORY_LABELS[$r->category] ?? $r->category)->values()->toArray(),
            'amounts' => $data->map(fn ($r) => (float) $r->total)->values()->toArray(),
        ];
    }

    private function buildExpenseByCategoryDetail(Collection $storeIds, string $dateFrom, string $dateTo): array
    {
        $data = DailyExpense::whereIn('store_id', $storeIds)
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return $data->map(fn ($r) => [
            'category'      => $r->category,
            'category_label'=> self::EXPENSE_CATEGORY_LABELS[$r->category] ?? $r->category,
            'total'         => (float) $r->total,
            'count'         => (int) $r->count,
        ])->values()->toArray();
    }

    private function buildTopProducts(?int $storeId, string $dateFrom, string $dateTo, int $limit = 15): array
    {
        return $this->buildTopProductsForStores(collect([$storeId]), $dateFrom, $dateTo, $limit);
    }

    private function buildTopProductsForStores(Collection $storeIds, string $dateFrom, string $dateTo, int $limit = 20): array
    {
        return OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.store_id', $storeIds)
            ->where('orders.payment_status', 'paid')
            ->whereDate('orders.created_at', '>=', $dateFrom)
            ->whereDate('orders.created_at', '<=', $dateTo)
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

    private function buildSalesByCashier(int $storeId, string $dateFrom, string $dateTo): array
    {
        return $this->buildSalesByCashierForStores(collect([$storeId]), $dateFrom, $dateTo);
    }

    private function buildSalesByCashierForStores(Collection $storeIds, string $dateFrom, string $dateTo): array
    {
        $rows = Order::query()
            ->whereIn('store_id', $storeIds)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('cashier_id, COUNT(*) as order_count, SUM(final_amount) as total_amount')
            ->groupBy('cashier_id')
            ->orderByDesc('total_amount')
            ->get();

        $userIds = $rows->pluck('cashier_id')->filter()->unique()->values()->all();
        $users = $userIds ? User::whereIn('id', $userIds)->get()->keyBy('id') : collect();

        return $rows->map(fn ($r) => [
            'cashier_id'   => $r->cashier_id,
            'cashier_name' => $r->cashier_id ? ($users->get($r->cashier_id)?->name ?? '—') : 'Tanpa kasir',
            'order_count'  => (int) $r->order_count,
            'total_amount' => (float) $r->total_amount,
        ])->values()->toArray();
    }

    private function buildSalesByType(int $storeId, string $dateFrom, string $dateTo): array
    {
        return $this->buildSalesByTypeForStores(collect([$storeId]), $dateFrom, $dateTo);
    }

    private function buildSalesByTypeForStores(Collection $storeIds, string $dateFrom, string $dateTo): array
    {
        $typeLabels = ['dine_in' => 'Dine In', 'takeaway' => 'Take Away', 'walk_in' => 'Walk In'];
        $data = Order::whereIn('store_id', $storeIds)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('type, COUNT(*) as count, SUM(final_amount) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        return $data->map(fn ($r) => [
            'type'  => $r->type,
            'label' => $typeLabels[$r->type] ?? $r->type,
            'count' => (int) $r->count,
            'total' => (float) $r->total,
        ])->values()->toArray();
    }

    private function buildSalesByPayment(int $storeId, string $dateFrom, string $dateTo): array
    {
        return $this->buildSalesByPaymentForStores(collect([$storeId]), $dateFrom, $dateTo);
    }

    private function buildSalesByPaymentForStores(Collection $storeIds, string $dateFrom, string $dateTo): array
    {
        $methodLabels = ['cash' => 'Tunai', 'qris' => 'QRIS', 'bank_transfer' => 'Transfer Bank', 'e_wallet' => 'E-Wallet', 'other' => 'Lainnya'];
        $data = Order::whereIn('store_id', $storeIds)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(final_amount) as total')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        return $data->map(fn ($r) => [
            'method' => $r->payment_method ?? 'other',
            'label'  => $methodLabels[$r->payment_method ?? ''] ?? ($r->payment_method ?? 'Lainnya'),
            'count'  => (int) $r->count,
            'total'  => (float) $r->total,
        ])->values()->toArray();
    }
}
