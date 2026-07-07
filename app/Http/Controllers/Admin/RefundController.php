<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Store;
use App\Models\StoreInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RefundController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $stores = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        $storeId = $request->query('store') ?? $user->store_id;
        $store = null;

        if ($storeId) {
            $store = Store::where('id', $storeId)
                ->where('brand_id', $user->brand_id)
                ->first();
        } else {
            $store = Store::where('brand_id', $user->brand_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->first();
        }

        if (! $store) {
            return Inertia::render('Admin/Refunds/Index', [
                'stores'  => $stores,
                'store'   => null,
                'refunds' => ['data' => [], 'total' => 0],
            ]);
        }

        $query = Refund::with(['order', 'user:id,name'])
            ->where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('refund_code', 'like', "%{$s}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_code', 'like', "%{$s}%"));
            });
        }

        $refunds = $query->paginate(20)
            ->withQueryString()
            ->through(fn ($r) => [
                'id'            => $r->id,
                'refund_code'   => $r->refund_code,
                'order_code'    => $r->order?->order_code,
                'order_id'      => $r->order_id,
                'type'          => $r->type,
                'refund_amount' => (float) $r->refund_amount,
                'refund_method' => $r->refund_method,
                'reason'        => $r->reason,
                'user_name'     => $r->user?->name,
                'status'        => $r->status,
                'created_at'    => $r->created_at->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Refunds/Index', [
            'stores'  => $stores,
            'store'   => $store->only('id', 'name', 'slug'),
            'refunds' => $refunds,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }
    
    public function lookup(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $code = trim((string) $request->input('order_code'));

        if ($code === '') {
            return back()->with('error', 'Silakan masukkan kode pesanan.');
        }

        // Exact match diprioritaskan; partial hanya fallback dan ambil yang terbaru,
        // supaya kode ambigu tidak membuka order lama/yang sudah di-refund penuh.
        $order = Order::whereHas('store', fn($q) => $q->where('brand_id', $user->brand_id))
            ->where('payment_status', 'paid')
            ->where(fn($q) => $q->where('order_code', $code)
                ->orWhere('order_code', 'like', "%{$code}%"))
            ->orderByRaw('CASE WHEN order_code = ? THEN 0 ELSE 1 END', [$code])
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            return back()->with('error', "Pesanan dengan kode \"{$code}\" tidak ditemukan atau belum dibayar.");
        }

        return redirect()->route('admin.refunds.create', $order->id);
    }

    public function create(Order $order): Response
    {
        $user = Auth::user();

        if (! $order->store || $order->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        if ($order->payment_status !== 'paid') {
            abort(422, 'Hanya pesanan yang sudah dibayar yang bisa di-refund.');
        }

        $order->load(['store', 'items']);

        // Calculate already refunded quantities per order_item
        $refundedQty = RefundItem::whereHas('refund', fn ($q) =>
            $q->where('order_id', $order->id)->whereNull('deleted_at')
        )->selectRaw('order_item_id, SUM(quantity) as qty')
            ->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id');

        $items = $order->items->map(function ($item) use ($refundedQty) {
            $alreadyRefunded = (float) ($refundedQty[$item->id] ?? 0);
            $maxRefund = max(0, (float) $item->quantity - $alreadyRefunded);
            return [
                'id'               => $item->id,
                'product_id'       => $item->product_id,
                'product_name'     => $item->product_name,
                'quantity'         => (float) $item->quantity,
                'unit'             => $item->unit,
                'unit_price'       => (float) $item->unit_price,
                'discount_amount'  => (float) $item->discount_amount,
                'subtotal'         => (float) $item->subtotal,
                'already_refunded' => $alreadyRefunded,
                'max_refund'       => $maxRefund,
            ];
        });

        return Inertia::render('Admin/Refunds/Create', [
            'order' => [
                'id'             => $order->id,
                'order_code'     => $order->order_code,
                'final_amount'   => (float) $order->final_amount,
                'payment_method' => $order->payment_method,
                'store_name'     => $order->store->name,
                'items'          => $items,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'order_id'     => ['required', 'integer', 'exists:orders,id'],
            'reason'       => ['nullable', 'string', 'max:1000'],
            'refund_method'=> ['required', 'string', 'in:cash,original'],
            'items'        => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity'      => ['required', 'numeric', 'min:0.001'],
            'items.*.restock'       => ['required', 'boolean'],
        ]);

        $order = Order::with(['store', 'items'])->findOrFail($data['order_id']);

        if (! $order->store || $order->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        if ($order->payment_status !== 'paid') {
            return back()->with('error', 'Order belum dibayar.');
        }

        try {
            $refund = DB::transaction(function () use ($order, $data, $user) {
                $totalRefundAmount = 0;
                $refundItemsData = [];

                // Already refunded quantities
                $refundedQty = RefundItem::whereHas('refund', fn ($q) =>
                    $q->where('order_id', $order->id)->whereNull('deleted_at')
                )->selectRaw('order_item_id, SUM(quantity) as qty')
                    ->groupBy('order_item_id')
                    ->pluck('qty', 'order_item_id');

                foreach ($data['items'] as $itemData) {
                    $orderItem = $order->items->find($itemData['order_item_id']);
                    if (! $orderItem) {
                        throw new \RuntimeException("Item pesanan tidak ditemukan.");
                    }

                    $alreadyRefunded = (float) ($refundedQty[$orderItem->id] ?? 0);
                    $maxRefund = (float) $orderItem->quantity - $alreadyRefunded;
                    $qty = (float) $itemData['quantity'];

                    if ($qty > $maxRefund) {
                        throw new \RuntimeException("Qty refund {$orderItem->product_name} melebihi sisa ({$maxRefund}).");
                    }

                    $effectivePrice = (float) $orderItem->unit_price - (float) $orderItem->discount_amount;
                    $itemRefundAmount = $effectivePrice * $qty;
                    $totalRefundAmount += $itemRefundAmount;

                    $refundItemsData[] = [
                        'order_item'    => $orderItem,
                        'quantity'      => $qty,
                        'refund_amount' => $itemRefundAmount,
                        'restock'       => $itemData['restock'],
                    ];
                }

                // Determine type
                $totalOriginalQty = $order->items->sum('quantity');
                $totalRefundedAfter = $refundedQty->sum() + collect($data['items'])->sum('quantity');
                $type = $totalRefundedAfter >= $totalOriginalQty ? 'full' : 'partial';

                // Generate refund code
                $refundCode = 'RF-' . strtoupper(substr(md5(uniqid()), 0, 8));

                $openShiftId = \App\Models\CashierShift::where('store_id', $order->store_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'open')
                    ->value('id');

                $refund = Refund::create([
                    'order_id'      => $order->id,
                    'store_id'      => $order->store_id,
                    'shift_id'      => $openShiftId,
                    'user_id'       => $user->id,
                    'refund_code'   => $refundCode,
                    'type'          => $type,
                    'reason'        => $data['reason'] ?? null,
                    'refund_amount' => $totalRefundAmount,
                    'refund_method' => $data['refund_method'],
                    'status'        => 'completed',
                    'processed_at'  => now(),
                ]);

                foreach ($refundItemsData as $rid) {
                    RefundItem::create([
                        'refund_id'     => $refund->id,
                        'order_item_id' => $rid['order_item']->id,
                        'product_id'    => $rid['order_item']->product_id,
                        'product_name'  => $rid['order_item']->product_name,
                        'quantity'      => $rid['quantity'],
                        'unit_price'    => $rid['order_item']->unit_price,
                        'refund_amount' => $rid['refund_amount'],
                        'restock'       => $rid['restock'],
                    ]);

                    // Restock
                    if ($rid['restock'] && $rid['order_item']->product) {
                        $product = $rid['order_item']->product;
                        if ($product->track_stock) {
                            StoreInventory::where('store_id', $order->store_id)
                                ->where('product_id', $product->id)
                                ->increment('current_stock', $rid['quantity']);
                        }
                    }
                }

                return $refund;
            });

            return redirect("/admin/refunds?store={$order->store_id}")
                ->with('success', "Refund {$refund->refund_code} berhasil diproses.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(Refund $refund): Response
    {
        $user = Auth::user();
        $refund->load(['order', 'store', 'user', 'items']);

        if ($refund->store->brand_id !== $user->brand_id) {
            abort(403);
        }

        return Inertia::render('Admin/Refunds/Show', [
            'refund' => [
                'id'            => $refund->id,
                'refund_code'   => $refund->refund_code,
                'order_code'    => $refund->order?->order_code,
                'order_id'      => $refund->order_id,
                'type'          => $refund->type,
                'reason'        => $refund->reason,
                'refund_amount' => (float) $refund->refund_amount,
                'refund_method' => $refund->refund_method,
                'status'        => $refund->status,
                'user_name'     => $refund->user?->name,
                'store_name'    => $refund->store?->name,
                'processed_at'  => $refund->processed_at?->format('d M Y H:i'),
                'created_at'    => $refund->created_at->format('d M Y H:i'),
                'items'         => $refund->items->map(fn ($i) => [
                    'product_name'  => $i->product_name,
                    'quantity'      => (float) $i->quantity,
                    'unit_price'    => (float) $i->unit_price,
                    'refund_amount' => (float) $i->refund_amount,
                    'restock'       => $i->restock,
                ]),
            ],
        ]);
    }
}
