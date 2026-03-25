<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyExpense;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DailyExpenseController extends Controller
{
    private const CATEGORY_LABELS = [
        'purchase'    => 'Pembelian',
        'supplies'    => 'Perlengkapan',
        'utilities'   => 'Listrik/Utilities',
        'maintenance' => 'Pemeliharaan',
        'salary'      => 'Gaji',
        'other'       => 'Lainnya',
    ];

    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);

        $stores = [];
        if ($user->role === 'owner' || $user->role === 'admin') {
            $stores = Store::where('brand_id', $user->brand_id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->toArray();
        }

        if (! $store) {
            return Inertia::render('Admin/ExpenseBook/SelectStore', [
                'stores' => $stores,
            ]);
        }

        $selectedDate = $request->input('date');
        try {
            $selectedDate = $selectedDate
                ? \Carbon\Carbon::parse($selectedDate)->toDateString()
                : now()->toDateString();
        } catch (\Throwable) {
            $selectedDate = now()->toDateString();
        }

        $categoryFilter = $request->category ?? 'all';
        $search = $request->search ?? '';
        $page = max(1, (int) $request->page);
        $perPage = 20;

        $baseQuery = DailyExpense::with('creator')
            ->where('store_id', $store->id)
            ->whereDate('expense_date', $selectedDate);

        if ($search !== '') {
            $baseQuery->where('description', 'like', '%' . $search . '%');
        }
        if ($categoryFilter !== 'all') {
            $baseQuery->where('category', $categoryFilter);
        }

        $totalAmount = (float) (clone $baseQuery)->sum('amount');

        $expenses = $baseQuery->orderByDesc('expense_date')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $expenseItems = collect($expenses->items())->map(fn ($e) => [
            'id'           => $e->id,
            'category'     => $e->category,
            'description'  => $e->description,
            'amount'       => (float) $e->amount,
            'expense_date' => $e->expense_date->format('Y-m-d'),
            'created_at'   => $e->created_at->format('H:i'),
            'creator_name' => $e->creator?->name,
        ])->values()->all();

        return Inertia::render('Admin/ExpenseBook/Index', [
            'store'       => $store->only('id', 'name', 'slug'),
            'stores'      => $stores,
            'selected_date' => $selectedDate,
            'expenses'    => [
                'data'          => $expenseItems,
                'current_page'  => $expenses->currentPage(),
                'last_page'     => $expenses->lastPage(),
                'per_page'      => $expenses->perPage(),
                'total'         => $expenses->total(),
                'from'          => $expenses->firstItem(),
                'to'            => $expenses->lastItem(),
                'prev_page_url' => $expenses->previousPageUrl(),
                'next_page_url' => $expenses->nextPageUrl(),
                'links'         => $expenses->linkCollection()->toArray(),
            ],
            'total_amount'     => $totalAmount,
            'category_filter'  => $categoryFilter,
            'search'           => $search,
            'category_options' => array_merge(
                [['value' => 'all', 'label' => 'Semua Kategori']],
                array_map(fn ($v, $l) => ['value' => $v, 'label' => $l], array_keys(self::CATEGORY_LABELS), self::CATEGORY_LABELS),
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);

        if (! $store) {
            return back()->with('error', 'Pilih toko terlebih dahulu.');
        }

        $validated = $request->validate([
            'category'     => ['required', 'string', 'in:purchase,supplies,utilities,maintenance,salary,other'],
            'description'  => ['required', 'string', 'max:500'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
        ]);

        DailyExpense::create([
            'store_id'     => $store->id,
            'created_by'   => $user->id,
            'category'     => $validated['category'],
            'description'  => $validated['description'],
            'amount'       => $validated['amount'],
            'expense_date' => $validated['expense_date'],
        ]);

        return back()->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function update(Request $request, DailyExpense $dailyExpense): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeStore($user, $dailyExpense->store_id);

        $validated = $request->validate([
            'category'     => ['required', 'string', 'in:purchase,supplies,utilities,maintenance,salary,other'],
            'description'  => ['required', 'string', 'max:500'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
        ]);

        $dailyExpense->update($validated);

        return back()->with('success', 'Pengeluaran berhasil diubah.');
    }

    public function destroy(DailyExpense $dailyExpense): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeStore($user, $dailyExpense->store_id);

        $dailyExpense->delete();

        return back()->with('success', 'Pengeluaran berhasil dihapus.');
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

    private function authorizeStore($user, int $storeId): void
    {
        $store = Store::where('id', $storeId)->where('brand_id', $user->brand_id)->first();
        if (! $store) {
            abort(403);
        }
        if ($user->store_id && (int) $user->store_id !== $storeId) {
            abort(403);
        }
    }
}
