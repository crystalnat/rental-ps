<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyExpense;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CashflowController extends Controller
{
    private const CATEGORY_LABELS = [
        'purchase'    => 'Pembelian',
        'supplies'    => 'Perlengkapan',
        'utilities'  => 'Listrik/Utilities',
        'maintenance'=> 'Pemeliharaan',
        'salary'      => 'Gaji',
        'other'       => 'Lainnya',
    ];

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
            return Inertia::render('Admin/Cashflow/SelectStore', [
                'stores' => $stores,
            ]);
        }

        $dateFrom = $request->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo = $request->date_to ?? now()->toDateString();
        $search = $request->search ?? '';
        $typeFilter = $request->type ?? 'all';
        $categoryFilter = $request->category ?? 'all';
        $sortKey = $request->query('sort', 'date');
        $sortDir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['date', 'amount', 'description', 'type', 'category_label', 'detail'];
        if (! in_array($sortKey, $allowedSort, true)) {
            $sortKey = 'date';
        }
        $page = max(1, (int) $request->page);
        $perPage = 25;

        $income = Order::where('store_id', $store->id)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('final_amount');

        $expenses = DailyExpense::where('store_id', $store->id)
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->sum('amount');

        $net = (float) $income - (float) $expenses;
        $orderCount = Order::where('store_id', $store->id)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->count();

        $chartIncomeExpense = $this->buildChartIncomeExpense($store->id, $dateFrom, $dateTo);
        $chartExpenseByCategory = $this->buildChartExpenseByCategory($store->id, $dateFrom, $dateTo);

        $history = $this->buildHistory($store->id, $dateFrom, $dateTo, $search, $typeFilter, $categoryFilter);
        $history = $this->sortHistoryCollection($history, $sortKey, $sortDir);
        $paginated = new LengthAwarePaginator(
            $history->forPage($page, $perPage)->values(),
            $history->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Admin/Cashflow/Index', [
            'store'     => $store->only('id', 'name', 'slug'),
            'stores'    => $stores,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'income'    => (float) $income,
            'expenses'  => (float) $expenses,
            'net'       => $net,
            'order_count' => $orderCount,
            'history'   => [
                'data'         => $paginated->items(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
                'prev_page_url'=> $paginated->previousPageUrl(),
                'next_page_url'=> $paginated->nextPageUrl(),
                'links'        => $paginated->linkCollection()->toArray(),
            ],
            'filters' => [
                'search'   => $search,
                'type'     => $typeFilter,
                'category' => $categoryFilter,
                'sort'     => $sortKey,
                'dir'      => $sortDir,
            ],
            'category_options' => array_merge(
                [['value' => 'all', 'label' => 'Semua Kategori']],
                array_map(fn ($v, $l) => ['value' => $v, 'label' => $l], array_keys(self::CATEGORY_LABELS), self::CATEGORY_LABELS),
            ),
            'chart_income_expense'      => $chartIncomeExpense,
            'chart_expense_by_category' => $chartExpenseByCategory,
        ]);
    }

    private function buildChartIncomeExpense(int $storeId, string $dateFrom, string $dateTo): array
    {
        $incomeByDay = Order::where('store_id', $storeId)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('DATE(created_at) as date, SUM(final_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $expensesByDay = DailyExpense::where('store_id', $storeId)
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->selectRaw('expense_date as date, SUM(amount) as total')
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->get()
            ->keyBy(fn ($r) => \Carbon\Carbon::parse($r->date)->format('Y-m-d'));

        $dates = collect();
        $start = \Carbon\Carbon::parse($dateFrom);
        $end = \Carbon\Carbon::parse($dateTo);
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates->push($d->format('Y-m-d'));
        }

        $labels = $dates->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()->toArray();
        $incomeData = $dates->map(fn ($d) => (float) ($incomeByDay->get($d)?->total ?? 0))->values()->toArray();
        $expenseData = $dates->map(fn ($d) => (float) ($expensesByDay->get($d)?->total ?? 0))->values()->toArray();

        return [
            'labels' => $labels,
            'income' => $incomeData,
            'expenses' => $expenseData,
        ];
    }

    private function buildChartExpenseByCategory(int $storeId, string $dateFrom, string $dateTo): array
    {
        $data = DailyExpense::where('store_id', $storeId)
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        if ($data->isEmpty()) {
            return ['labels' => [], 'amounts' => []];
        }

        $labels = $data->map(fn ($r) => self::CATEGORY_LABELS[$r->category] ?? $r->category)->values()->toArray();
        $amounts = $data->map(fn ($r) => (float) $r->total)->values()->toArray();

        return ['labels' => $labels, 'amounts' => $amounts];
    }

    private function buildHistory(int $storeId, string $dateFrom, string $dateTo, string $search, string $typeFilter, string $categoryFilter): \Illuminate\Support\Collection
    {
        $items = collect();

        if ($typeFilter === 'all' || $typeFilter === 'income') {
            $orderQuery = Order::with('cashier')
                ->where('store_id', $storeId)
                ->where('payment_status', 'paid')
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo);

            if ($search !== '') {
                $orderQuery->where('order_code', 'like', '%' . $search . '%');
            }

            foreach ($orderQuery->orderByDesc('created_at')->get() as $o) {
                $items->push([
                    'id'          => 'ord-' . $o->id,
                    'type'        => 'income',
                    'date'        => $o->created_at->format('d M Y H:i'),
                    'date_sort'   => $o->created_at->format('Y-m-d H:i:s'),
                    'description' => $o->order_code,
                    'category'    => 'sale',
                    'category_label'=> 'Penjualan',
                    'amount'      => (float) $o->final_amount,
                    'detail'      => $o->cashier?->name,
                    'order_id'    => $o->id,
                ]);
            }
        }

        if ($typeFilter === 'all' || $typeFilter === 'expense') {
            $expenseQuery = DailyExpense::with('creator')
                ->where('store_id', $storeId)
                ->whereDate('expense_date', '>=', $dateFrom)
                ->whereDate('expense_date', '<=', $dateTo);

            if ($search !== '') {
                $expenseQuery->where('description', 'like', '%' . $search . '%');
            }
            if ($categoryFilter !== 'all') {
                $expenseQuery->where('category', $categoryFilter);
            }

            foreach ($expenseQuery->orderByDesc('expense_date')->orderByDesc('created_at')->get() as $e) {
                $items->push([
                    'id'            => 'exp-' . $e->id,
                    'type'          => 'expense',
                    'date'          => $e->expense_date->format('d M Y') . ' ' . $e->created_at->format('H:i'),
                    'date_sort'     => $e->expense_date->format('Y-m-d') . ' ' . ($e->created_at?->format('H:i:s') ?? '00:00:00'),
                    'description'   => $e->description,
                    'category'      => $e->category,
                    'category_label'=> self::CATEGORY_LABELS[$e->category] ?? $e->category,
                    'amount'        => (float) $e->amount,
                    'detail'        => $e->creator?->name,
                    'expense_id'    => $e->id,
                ]);
            }
        }

        return $items->sortByDesc('date_sort')->values();
    }

    private function sortHistoryCollection(\Illuminate\Support\Collection $collection, string $sortKey, string $sortDir): \Illuminate\Support\Collection
    {
        $asc = $sortDir === 'asc';

        return (match ($sortKey) {
            'amount' => $asc ? $collection->sortBy('amount') : $collection->sortByDesc('amount'),
            'description' => $asc ? $collection->sortBy('description') : $collection->sortByDesc('description'),
            'type' => $asc ? $collection->sortBy('type') : $collection->sortByDesc('type'),
            'category_label' => $asc ? $collection->sortBy('category_label') : $collection->sortByDesc('category_label'),
            'detail' => $asc ? $collection->sortBy(fn ($row) => $row['detail'] ?? '') : $collection->sortByDesc(fn ($row) => $row['detail'] ?? ''),
            default => $asc ? $collection->sortBy('date_sort') : $collection->sortByDesc('date_sort'),
        })->values();
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
