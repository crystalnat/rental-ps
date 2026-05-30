<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceLog;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ManualInvoiceController extends Controller
{
    public function index(): Response
    {
        $user  = Auth::user();
        $store = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when($user->role !== 'owner' && $user->role !== 'admin', fn ($q) => $q->where('id', $user->store_id))
            ->first();

        $storeInfo = $store ? [
            'name'    => $store->name,
            'address' => $store->address,
            'city'    => $store->city,
            'phone'   => $store->phone,
            'email'   => $store->email,
            'logo'    => $store->logo ? \Storage::disk('public')->url($store->logo) : null,
        ] : null;

        $products = $store ? $this->getProducts($store) : [];

        return Inertia::render('Admin/ManualInvoice/Index', [
            'store'       => $storeInfo,
            'cashierName' => $user->name,
            'products'    => $products,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_code'     => ['required', 'string', 'max:50'],
            'date'             => ['required', 'date'],
            'customer_name'    => ['nullable', 'string', 'max:100'],
            'cashier_name'     => ['nullable', 'string', 'max:100'],
            'payment_method'   => ['required', 'string', 'max:50'],
            'cash_received'    => ['nullable', 'numeric', 'min:0'],
            'discount'         => ['nullable', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.name'     => ['required', 'string', 'max:200'],
            'items.*.qty'      => ['required', 'numeric', 'min:0.01'],
            'items.*.unit'     => ['required', 'string', 'max:30'],
            'items.*.price'    => ['required', 'numeric', 'min:0'],
            'items.*.product_id' => ['nullable', 'integer'],
        ]);

        $user  = Auth::user();
        $store = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when($user->role !== 'owner' && $user->role !== 'admin', fn ($q) => $q->where('id', $user->store_id))
            ->firstOrFail();

        $order = DB::transaction(function () use ($data, $user, $store) {
            $subtotal       = collect($data['items'])->sum(fn ($i) => $i['qty'] * $i['price']);
            $discount       = (float) ($data['discount'] ?? 0);
            $finalAmount    = max(0, $subtotal - $discount);
            $isCash         = strtolower($data['payment_method']) === 'tunai';
            $cashReceived   = $isCash ? (float) ($data['cash_received'] ?? $finalAmount) : $finalAmount;
            $changeAmount   = $isCash ? max(0, $cashReceived - $finalAmount) : 0;

            $order = Order::create([
                'store_id'        => $store->id,
                'cashier_id'      => $user->id,
                'order_code'      => $data['invoice_code'],
                'type'            => 'manual',
                'status'          => 'done',
                'notes'           => $data['notes'] ?? null,
                'subtotal'        => $subtotal,
                'discount_amount' => $discount,
                'tax_rate'        => 0,
                'tax_amount'      => 0,
                'final_amount'    => $finalAmount,
                'payment_method'  => $data['payment_method'],
                'payment_status'  => 'paid',
                'cash_received'   => $isCash ? $cashReceived : null,
                'change_amount'   => $changeAmount > 0 ? $changeAmount : null,
                'paid_at'         => $data['date'],
                'completed_at'    => $data['date'],
                'created_at'      => $data['date'],
                'updated_at'      => $data['date'],
            ]);

            foreach ($data['items'] as $item) {
                $subtotalItem = $item['qty'] * $item['price'];
                OrderItem::create([
                    'order_id'        => $order->id,
                    'product_id'      => $item['product_id'] ?? null,
                    'product_name'    => $item['name'],
                    'quantity'        => $item['qty'],
                    'unit'            => $item['unit'],
                    'unit_price'      => $item['price'],
                    'buy_price'       => 0,
                    'discount_amount' => 0,
                    'subtotal'        => $subtotalItem,
                ]);
            }

            return $order;
        });

        return redirect()->route('admin.manual-invoice.receipt', $order->id)
            ->with('success', 'Struk manual berhasil disimpan.');
    }

    public function receipt(Order $order): Response
    {
        $this->authorizeOrder($order);
        $order->load(['store', 'cashier', 'items']);

        $store = $order->store;

        return Inertia::render('Admin/Orders/Receipt', [
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
                'table_name'      => null,
                'cashier_name'    => $order->cashier?->name,
                'customer_name'   => null,
                'created_at'      => $order->created_at->format('d M Y H:i'),
                'paid_at'         => $order->paid_at?->format('d M Y H:i'),
                'is_rental'       => false,
                'rental_duration_minutes' => null,
                'items'           => $order->items->map(fn ($i) => [
                    'product_name'    => $i->product_name,
                    'quantity'        => (float) $i->quantity,
                    'unit'            => $i->unit,
                    'unit_price'      => (float) $i->unit_price,
                    'discount_amount' => (float) $i->discount_amount,
                    'subtotal'        => (float) $i->subtotal,
                    'modifiers'       => [],
                ]),
            ],
            'store' => [
                'name'    => $store->name,
                'address' => $store->address,
                'city'    => $store->city,
                'phone'   => $store->phone,
                'email'   => $store->email,
                'logo'    => $store->logo ? \Storage::disk('public')->url($store->logo) : null,
            ],
        ]);
    }

    private function authorizeOrder(Order $order): void
    {
        $user = Auth::user();
        abort_unless(
            $user->role === 'owner' || $user->role === 'admin' || $order->store_id === $user->store_id,
            403
        );
        abort_unless($order->store->brand_id === $user->brand_id, 403);
    }

    private function getProducts(Store $store): array
    {
        return Product::with(['inventories' => fn ($q) => $q->where('store_id', $store->id)])
            ->where('brand_id', $store->brand_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($product) use ($store) {
                $price = PriceLog::where('product_id', $product->id)
                    ->where(fn ($q) => $q->where('store_id', $store->id)->orWhereNull('store_id'))
                    ->whereNull('ended_at')
                    ->latest('started_at')
                    ->first();

                return [
                    'id'         => $product->id,
                    'name'       => $product->name,
                    'unit'       => $product->unit,
                    'sell_price' => (float) ($price?->sell_price ?? 0),
                ];
            })
            ->filter(fn ($p) => $p['sell_price'] > 0)
            ->values()
            ->toArray();
    }
}
