<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\DailyExpense;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    /**
     * List semua shift (filter per toko, tanggal).
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $stores = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        $storeId = $request->query('store');
        $store = null;

        if ($storeId) {
            $store = Store::where('id', $storeId)
                ->where('brand_id', $user->brand_id)
                ->first();
        } elseif ($user->store_id) {
            $store = Store::find($user->store_id);
        } else {
            $store = Store::where('brand_id', $user->brand_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->first();
        }

        if (! $store) {
            return Inertia::render('Admin/Shifts/Index', [
                'stores' => $stores,
                'store'  => null,
                'shifts' => ['data' => [], 'total' => 0],
            ]);
        }

        $isCashier = $user->role === 'cashier';

        $query = CashierShift::with('user:id,name')
            ->where('store_id', $store->id)
            ->orderByDesc('opened_at');

        // Kasir hanya bisa melihat shift miliknya sendiri
        if ($isCashier) {
            $query->where('user_id', $user->id);
        } else {
            if ($request->filled('user_id') && $request->user_id !== 'all') {
                $query->where('user_id', $request->user_id);
            }
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->date_to);
        }

        $shifts = $query->paginate(20)
            ->withQueryString()
            ->through(fn ($shift) => [
                'id'              => $shift->id,
                'user_name'       => $shift->user?->name,
                'opened_at'       => $shift->opened_at->format('d M Y H:i'),
                'closed_at'       => $shift->closed_at?->format('d M Y H:i'),
                'scheduled_start' => $shift->scheduled_start,
                'scheduled_end'   => $shift->scheduled_end,
                'status'          => $shift->status,
                'opening_cash'    => (float) $shift->opening_cash,
                'total_sales'     => $shift->total_sales ? (float) $shift->total_sales : null,
                'total_orders'    => $shift->total_orders,
                'cash_difference' => $shift->cash_difference !== null ? (float) $shift->cash_difference : null,
            ]);

        // Filter kasir hanya untuk owner/admin
        $users = [];
        if (! $isCashier) {
            $users = CashierShift::where('cashier_shifts.store_id', $store->id)
                ->distinct()
                ->join('users', 'cashier_shifts.user_id', '=', 'users.id')
                ->pluck('users.name', 'users.id')
                ->map(fn ($name, $id) => ['value' => (string) $id, 'label' => $name])
                ->values()
                ->toArray();
        }

        return Inertia::render('Admin/Shifts/Index', [
            'stores'     => $stores,
            'store'      => $store->only('id', 'name', 'slug'),
            'shifts'     => $shifts,
            'users'      => $users,
            'is_cashier' => $isCashier,
            'filters'    => [
                'status'    => $request->status ?? 'all',
                'date_from' => $request->date_from,
                'date_to'   => $request->date_to,
                'user_id'   => $isCashier ? (string) $user->id : ($request->user_id ?? 'all'),
            ],
        ]);
    }

    /**
     * Detail shift.
     */
    public function show(CashierShift $shift): Response
    {
        $user = Auth::user();
        $shift->load(['store', 'user']);

        if ($shift->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        // Get orders in this shift
        $orders = Order::with('items')->where('shift_id', $shift->id)
            ->where('payment_status', 'paid')
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn ($o) => [
                'id'             => $o->id,
                'order_code'     => $o->order_code,
                'payment_method' => $o->payment_method,
                'final_amount'   => (float) $o->final_amount,
                'paid_at'        => $o->paid_at?->format('H:i'),
                'items'          => $o->items->map(fn ($i) => [
                    'product_name' => $i->product_name,
                    'quantity'     => (float) $i->quantity,
                    'unit'         => $i->unit,
                    'subtotal'     => (float) $i->subtotal,
                ]),
            ]);

        // Payment method breakdown
        $paymentBreakdown = $orders->groupBy('payment_method')->map(fn ($group, $method) => [
            'method' => $method,
            'count'  => $group->count(),
            'total'  => $group->sum('final_amount'),
        ])->values();

        // === Mutasi Kas (ledger uang tunai di laci untuk shift ini) ===
        $cashPaymentCodes = \App\Models\PaymentMethod::where('brand_id', $shift->store->brand_id)
            ->where('requires_cash_input', true)
            ->pluck('code')
            ->toArray();

        $cashOrders = Order::where('shift_id', $shift->id)
            ->where('payment_status', 'paid')
            ->whereNull('deleted_at')
            ->whereIn('payment_method', $cashPaymentCodes)
            ->get(['order_code', 'final_amount', 'paid_at']);

        $shiftExpenses = DailyExpense::where('shift_id', $shift->id)
            ->whereNull('deleted_at')
            ->get(['description', 'amount', 'created_at']);

        $cashRefunds = \App\Models\Refund::where('shift_id', $shift->id)
            ->where('refund_method', 'cash')
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->get(['refund_code', 'refund_amount', 'processed_at']);

        $movements = collect()
            ->push([
                'at'     => $shift->opened_at,
                'label'  => 'Kas Awal',
                'ref'    => null,
                'type'   => 'in',
                'amount' => (float) $shift->opening_cash,
            ])
            ->concat($cashOrders->map(fn ($o) => [
                'at'     => $o->paid_at ?? $shift->opened_at,
                'label'  => 'Penjualan Tunai',
                'ref'    => $o->order_code,
                'type'   => 'in',
                'amount' => (float) $o->final_amount,
            ]))
            ->concat($shiftExpenses->map(fn ($e) => [
                'at'     => $e->created_at,
                'label'  => 'Pengeluaran: ' . $e->description,
                'ref'    => null,
                'type'   => 'out',
                'amount' => (float) $e->amount,
            ]))
            ->concat($cashRefunds->map(fn ($r) => [
                'at'     => $r->processed_at ?? $shift->opened_at,
                'label'  => 'Refund Tunai',
                'ref'    => $r->refund_code,
                'type'   => 'out',
                'amount' => (float) $r->refund_amount,
            ]))
            ->sortBy('at')
            ->values();

        // Running balance (saldo laci berjalan)
        $balance = 0.0;
        $movements = $movements->map(function ($m) use (&$balance) {
            $balance += $m['type'] === 'in' ? $m['amount'] : -$m['amount'];

            return [
                'time'    => $m['at']->format('d M H:i'),
                'label'   => $m['label'],
                'ref'     => $m['ref'],
                'type'    => $m['type'],
                'amount'  => $m['amount'],
                'balance' => round($balance, 2),
            ];
        });

        return Inertia::render('Admin/Shifts/Show', [
            'shift' => [
                'id'                  => $shift->id,
                'store_name'          => $shift->store->name,
                'user_name'           => $shift->user->name,
                'scheduled_start'     => $shift->scheduled_start,
                'scheduled_end'       => $shift->scheduled_end,
                'opened_at'           => $shift->opened_at->format('d M Y H:i'),
                'closed_at'           => $shift->closed_at?->format('d M Y H:i'),
                'status'              => $shift->status,
                'opening_cash'        => (float) $shift->opening_cash,
                'expected_cash'       => $shift->expected_cash !== null ? (float) $shift->expected_cash : null,
                'actual_cash'         => $shift->actual_cash !== null ? (float) $shift->actual_cash : null,
                'cash_difference'     => $shift->cash_difference !== null ? (float) $shift->cash_difference : null,
                'total_sales'         => $shift->total_sales !== null ? (float) $shift->total_sales : null,
                'total_orders'        => $shift->total_orders,
                'total_cash_sales'    => $shift->total_cash_sales !== null ? (float) $shift->total_cash_sales : null,
                'total_non_cash_sales'=> $shift->total_non_cash_sales !== null ? (float) $shift->total_non_cash_sales : null,
                'total_expenses'      => $shift->total_expenses !== null ? (float) $shift->total_expenses : null,
                'total_refunds'       => $shift->total_refunds !== null ? (float) $shift->total_refunds : null,
                'notes'               => $shift->notes,
            ],
            'orders'            => $orders,
            'payment_breakdown' => $paymentBreakdown,
            'cash_movements'    => $movements,
        ]);
    }

    /**
     * Buka shift baru.
     */
    public function open(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'store_id'        => ['required', 'integer', 'exists:stores,id'],
            'opening_cash'    => ['required', 'numeric', 'min:0'],
            'scheduled_start' => ['nullable', 'date_format:H:i'],
            'scheduled_end'   => ['nullable', 'date_format:H:i'],
        ]);

        $store = Store::where('id', $data['store_id'])
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->first();

        if (! $store) {
            return back()->with('error', 'Toko tidak ditemukan.');
        }

        // Check if user already has an open shift at this store
        $existingShift = CashierShift::where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existingShift) {
            return back()->with('error', 'Anda sudah memiliki shift yang masih buka di toko ini.');
        }

        CashierShift::create([
            'store_id'        => $store->id,
            'user_id'         => $user->id,
            'opened_at'       => now(),
            'opening_cash'    => $data['opening_cash'],
            'scheduled_start' => $data['scheduled_start'] ?? null,
            'scheduled_end'   => $data['scheduled_end'] ?? null,
            'status'          => 'open',
        ]);

        return back()->with('success', 'Shift berhasil dibuka.');
    }

    /**
     * Tutup shift — otomatis hitung summary.
     */
    public function close(CashierShift $shift, Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($shift->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        // Kasir hanya boleh menutup shift miliknya sendiri; owner/admin boleh menutup shift siapa pun
        if ($user->role === 'cashier' && $shift->user_id !== $user->id) {
            abort(403);
        }

        if ($shift->status === 'closed') {
            return back()->with('error', 'Shift sudah ditutup.');
        }

        $data = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        // Calculate totals from orders in this shift
        $orders = Order::where('shift_id', $shift->id)
            ->where('payment_status', 'paid')
            ->whereNull('deleted_at')
            ->get();

        $totalSales = (float) $orders->sum('final_amount');
        $totalOrders = $orders->count();

        // Cash-based payments
        $cashPaymentCodes = \App\Models\PaymentMethod::where('brand_id', $shift->store->brand_id)
            ->where('requires_cash_input', true)
            ->pluck('code')
            ->toArray();

        $totalCashSales = (float) $orders->whereIn('payment_method', $cashPaymentCodes)->sum('final_amount');
        $totalNonCashSales = $totalSales - $totalCashSales;

        // Pengeluaran yang tercatat DI shift ini saja (bukan per-tanggal seluruh toko)
        $totalExpenses = (float) DailyExpense::where('shift_id', $shift->id)
            ->whereNull('deleted_at')
            ->sum('amount');

        // Refund tunai keluar dari laci kasir shift ini
        $totalCashRefunds = (float) \App\Models\Refund::where('shift_id', $shift->id)
            ->where('refund_method', 'cash')
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->sum('refund_amount');

        // Expected cash = kas awal + penjualan tunai - pengeluaran - refund tunai
        $expectedCash = (float) $shift->opening_cash + $totalCashSales - $totalExpenses - $totalCashRefunds;
        $actualCash = (float) $data['actual_cash'];
        $cashDifference = $actualCash - $expectedCash;

        $shift->update([
            'closed_at'            => now(),
            'status'               => 'closed',
            'actual_cash'          => $actualCash,
            'expected_cash'        => $expectedCash,
            'cash_difference'      => $cashDifference,
            'total_sales'          => $totalSales,
            'total_orders'         => $totalOrders,
            'total_cash_sales'     => $totalCashSales,
            'total_non_cash_sales' => $totalNonCashSales,
            'total_expenses'       => $totalExpenses,
            'total_refunds'        => $totalCashRefunds,
            'notes'                => $data['notes'] ?? $shift->notes,
        ]);

        return back()->with('success', 'Shift berhasil ditutup. Selisih kas: Rp ' . number_format($cashDifference, 0, ',', '.'));
    }

    /**
     * Get active shift for current user/store (JSON for cashier page).
     */
    public function active(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $storeId = $request->query('store') ?? $user->store_id;

        if (! $storeId) {
            return response()->json(['shift' => null]);
        }

        $shift = CashierShift::where('store_id', $storeId)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (! $shift) {
            return response()->json(['shift' => null]);
        }

        return response()->json([
            'shift' => [
                'id'              => $shift->id,
                'opened_at'       => $shift->opened_at->format('d M Y H:i'),
                'opening_cash'    => (float) $shift->opening_cash,
                'scheduled_start' => $shift->scheduled_start,
                'scheduled_end'   => $shift->scheduled_end,
            ],
        ]);
    }
}
