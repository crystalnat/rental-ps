<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Floor;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\PriceLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CashierController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();

        // Resolve store: cashier uses their store; owner/admin can select
        $store = $this->resolveStore($user, $request);

        if (! $store) {
            return Inertia::render('Admin/Cashier/SelectStore', [
                'stores' => Store::where('brand_id', $user->brand_id)
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']),
            ]);
        }

        $products = $this->getProductsForStore($store);
        $categories = Category::where('brand_id', $store->brand_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);
        $tables = DiningTable::where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('floor')
            ->orderBy('name')
            ->get(['id', 'name', 'floor', 'status']);

        $stores = [];
        if ($user->role === 'owner' || $user->role === 'admin') {
            $stores = Store::where('brand_id', $user->brand_id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);
        }

        $paymentMethods = PaymentMethod::where('brand_id', $store->brand_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($pm) => [
                'id'                  => $pm->id,
                'name'                => $pm->name,
                'code'                => $pm->code,
                'requires_cash_input' => $pm->requires_cash_input,
                'qrcode_image'        => $pm->qrcode_image ? \Storage::disk('public')->url($pm->qrcode_image) : null,
                'account_name'        => $pm->account_name,
                'account_number'      => $pm->account_number,
            ]);

        $floorPlan = $this->buildFloorPlanForCashier($store);

        return Inertia::render('Admin/Cashier/Index', [
            'store'           => $store->only('id', 'name', 'slug'),
            'pending_orders_count' => Order::where('store_id', $store->id)
                ->whereNull('cashier_id')
                ->whereIn('status', ['pending', 'confirmed', 'processing', 'ready'])
                ->count(),
            'products'        => $products,
            'categories'      => $categories,
            'tables'          => $tables,
            'stores'          => $stores,
            'payment_methods' => $paymentMethods,
            'floor_plan'      => $floorPlan,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);

        if (! $store) {
            return back()->with('error', 'Pilih toko terlebih dahulu.');
        }

        $validCodes = PaymentMethod::where('brand_id', $store->brand_id)
            ->where('is_active', true)
            ->pluck('code')
            ->toArray();

        $data = $request->validate([
            'type'             => ['required', 'in:dine_in,takeaway,walk_in'],
            'table_id'         => ['nullable', 'exists:dining_tables,id'],
            'customer_name'    => ['nullable', 'string', 'max:255'],
            'customer_phone'   => ['nullable', 'string', 'max:20'],
            'customer_email'   => ['nullable', 'email', 'max:255'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.001'],
            'items.*.notes'      => ['nullable', 'string', 'max:500'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'payment_method'   => ['required', Rule::in($validCodes)],
            'cash_received'    => ['nullable', 'numeric', 'min:0'],
            'discount_amount'  => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($data['type'] === 'dine_in' && empty($data['table_id'])) {
            return back()->with('error', 'Pilih meja untuk pesanan dine-in.');
        }

        $order = DB::transaction(function () use ($store, $data, $user) {
            $orderCode = $this->generateOrderCode($store->id);
            $subtotal = 0;
            $itemsData = [];

            foreach ($data['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('brand_id', $store->brand_id)
                    ->firstOrFail();

                $priceLog = PriceLog::where('product_id', $product->id)
                    ->where(fn ($q) => $q->where('store_id', $store->id)->orWhereNull('store_id'))
                    ->whereNull('ended_at')
                    ->latest('started_at')
                    ->firstOrFail();

                $qty = (float) $item['quantity'];
                $unitPrice = (float) $priceLog->sell_price;
                $buyPrice = (float) $priceLog->buy_price;
                $discountPercent = (float) $product->discount_percent;
                $discount = $discountPercent > 0 ? round($unitPrice * ($discountPercent / 100)) : 0;
                $itemSubtotal = ($unitPrice - $discount) * $qty;
                $subtotal += $itemSubtotal;

                // Check stock if tracking
                if ($product->track_stock) {
                    $inventory = StoreInventory::where('store_id', $store->id)
                        ->where('product_id', $product->id)
                        ->first();
                    $currentStock = (float) ($inventory?->current_stock ?? 0);
                    if ($currentStock < $qty) {
                        throw new \RuntimeException("Stok {$product->name} tidak mencukupi. Tersedia: {$currentStock}");
                    }
                }

                $itemsData[] = [
                    'product'    => $product,
                    'price_log'  => $priceLog,
                    'quantity'   => $qty,
                    'unit_price' => $unitPrice,
                    'buy_price'  => $buyPrice,
                    'discount'   => $discount,
                    'subtotal'   => $itemSubtotal,
                    'notes'      => $item['notes'] ?? null,
                ];
            }

            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $taxRate = 0;
            $taxAmount = 0;
            $finalAmount = max(0, $subtotal - $discountAmount) + $taxAmount;

            $paymentMethod = PaymentMethod::where('brand_id', $store->brand_id)
                ->where('code', $data['payment_method'])
                ->first();
            $requiresCash = $paymentMethod?->requires_cash_input ?? ($data['payment_method'] === 'cash');

            $cashReceived = $requiresCash
                ? (float) ($data['cash_received'] ?? $finalAmount)
                : $finalAmount;
            $changeAmount = $requiresCash
                ? max(0, $cashReceived - $finalAmount)
                : 0;

            $customerId = $this->resolveCustomer($store->brand_id, $data);

            $order = Order::create([
                'store_id'        => $store->id,
                'table_id'        => $data['type'] === 'dine_in' ? $data['table_id'] : null,
                'customer_id'     => $customerId,
                'cashier_id'      => $user->id,
                'order_code'      => $orderCode,
                'type'            => $data['type'],
                'status'          => 'done',
                'notes'           => $data['notes'] ?? null,
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_rate'        => $taxRate,
                'tax_amount'      => $taxAmount,
                'final_amount'    => $finalAmount,
                'payment_method'  => $data['payment_method'],
                'payment_status'  => 'paid',
                'cash_received'   => $requiresCash ? $cashReceived : null,
                'change_amount'   => $changeAmount > 0 ? $changeAmount : null,
                'paid_at'         => now(),
                'completed_at'    => now(),
            ]);

            foreach ($itemsData as $item) {
                OrderItem::create([
                    'order_id'       => $order->id,
                    'product_id'     => $item['product']->id,
                    'price_log_id'   => $item['price_log']->id,
                    'product_name'   => $item['product']->name,
                    'quantity'       => $item['quantity'],
                    'unit'           => $item['product']->unit,
                    'unit_price'     => $item['unit_price'],
                    'buy_price'      => $item['buy_price'],
                    'discount_amount'=> $item['discount'],
                    'subtotal'       => $item['subtotal'],
                    'notes'          => $item['notes'],
                ]);

                // Deduct stock
                if ($item['product']->track_stock) {
                    StoreInventory::where('store_id', $store->id)
                        ->where('product_id', $item['product']->id)
                        ->decrement('current_stock', $item['quantity']);
                }
            }

            if ($customerId) {
                Customer::where('id', $customerId)->increment('total_orders');
                Customer::where('id', $customerId)->increment('total_spent', $finalAmount);
            }

            return $order;
        });

        return redirect()
            ->route('admin.cashier.index', ['store' => $store->id])
            ->with('success', "Pesanan {$order->order_code} berhasil dibuat.");
    }

    private function resolveCustomer(int $brandId, array $data): ?int
    {
        $name = trim($data['customer_name'] ?? '');
        $phone = trim($data['customer_phone'] ?? '');
        $email = trim($data['customer_email'] ?? '');

        if (! $phone && ! $email) {
            return null;
        }

        $name = $name ?: 'Pelanggan';
        $email = $email ?: null;
        $phone = $phone ?: null;

        $customer = null;
        if ($email) {
            $customer = Customer::where('brand_id', $brandId)
                ->where('email', $email)
                ->first();
        }
        if (! $customer && $phone) {
            $customer = Customer::where('brand_id', $brandId)
                ->where('phone', $phone)
                ->first();
        }

        if ($customer) {
            $customer->update([
                'name'  => $name,
                'email' => $email ?? $customer->email,
                'phone' => $phone ?? $customer->phone,
            ]);
            return $customer->id;
        }

        $customer = Customer::create([
            'brand_id' => $brandId,
            'name'     => $name,
            'email'    => $email,
            'phone'    => $phone,
        ]);

        return $customer->id;
    }

    public function pendingOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);

        if (! $store) {
            return response()->json(['count' => 0, 'latest' => null]);
        }

        $pending = Order::with(['table', 'customer'])
            ->where('store_id', $store->id)
            ->whereNull('cashier_id')
            ->whereIn('status', ['pending', 'confirmed', 'processing', 'ready'])
            ->orderByDesc('created_at')
            ->get(['id', 'order_code', 'table_id', 'customer_id', 'final_amount', 'notes', 'created_at']);

        $latest = $pending->first();

        return response()->json([
            'count'  => $pending->count(),
            'orders' => $pending->map(fn ($o) => [
                'id'              => $o->id,
                'order_code'      => $o->order_code,
                'table_name'      => $o->table?->name,
                'customer_name'   => $o->customer?->name,
                'customer_email'  => $o->customer?->email,
                'customer_phone'  => $o->customer?->phone,
                'notes'           => $o->notes,
                'final_amount'    => (float) $o->final_amount,
                'created_at'      => $o->created_at->format('H:i'),
            ])->values()->toArray(),
            'latest' => $latest ? [
                'order_code'      => $latest->order_code,
                'table_name'      => $latest->table?->name,
                'customer_name'   => $latest->customer?->name,
                'customer_email'  => $latest->customer?->email,
                'customer_phone'  => $latest->customer?->phone,
                'notes'           => $latest->notes,
                'final_amount'    => (float) $latest->final_amount,
                'created_at'      => $latest->created_at->toIso8601String(),
            ] : null,
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

        return null;
    }

    private function getProductsForStore(Store $store): array
    {
        return Product::with(['category', 'inventories' => fn ($q) => $q->where('store_id', $store->id)])
            ->where('brand_id', $store->brand_id)
            ->where('is_available', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($product) use ($store) {
                $inventory = $product->inventories->first();
                $price = PriceLog::where('product_id', $product->id)
                    ->where(fn ($q) => $q->where('store_id', $store->id)->orWhereNull('store_id'))
                    ->whereNull('ended_at')
                    ->latest('started_at')
                    ->first();

                $sellPrice = (float) ($price?->sell_price ?? 0);
                if ($sellPrice <= 0) {
                    return null;
                }

                return [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'category_id'    => $product->category_id,
                    'category_name'  => $product->category?->name,
                    'category_color' => $product->category?->color,
                    'unit'           => $product->unit,
                    'track_stock'    => $product->track_stock,
                    'current_stock'  => (float) ($inventory?->current_stock ?? 0),
                    'discount_percent' => (float) $product->discount_percent,
                    'sell_price'     => $sellPrice,
                    'image_url'      => $product->image ? \Storage::disk('public')->url($product->image) : null,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    private function generateOrderCode(int $storeId): string
    {
        $prefix = 'ORD-' . now()->format('Ymd') . '-';
        $last = Order::where('store_id', $storeId)
            ->where('order_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('order_code');

        $seq = 1;
        if ($last) {
            $seq = (int) substr($last, -4) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function buildFloorPlanForCashier(Store $store): array
    {
        $floors = Floor::where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $activeOrderIds = Order::where('store_id', $store->id)
            ->whereNotNull('table_id')
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get()
            ->groupBy('table_id');

        return $floors->map(function ($floor) use ($activeOrderIds) {
            $tables = $floor->diningTables()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function ($t) use ($activeOrderIds) {
                    $orders = $activeOrderIds->get($t->id, collect())
                        ->map(fn ($o) => [
                            'id'          => $o->id,
                            'order_code'  => $o->order_code,
                            'status'      => $o->status,
                            'final_amount'=> (float) $o->final_amount,
                            'items_count' => $o->items()->count(),
                            'created_at'  => $o->created_at->format('H:i'),
                        ])
                        ->values()
                        ->toArray();

                    return [
                        'id'            => $t->id,
                        'name'          => $t->name,
                        'capacity'      => $t->capacity,
                        'x_meters'      => (float) ($t->x_meters ?? 0),
                        'y_meters'      => (float) ($t->y_meters ?? 0),
                        'width_meters'  => (float) ($t->width_meters ?? 0.8),
                        'length_meters' => (float) ($t->length_meters ?? 1.2),
                        'rotation_deg'  => (int) ($t->rotation_deg ?? 0),
                        'shape'         => $t->shape ?? 'rectangle',
                        'active_orders' => $orders,
                        'has_orders'    => count($orders) > 0,
                    ];
                })
                ->values()
                ->toArray();

            return [
                'id'            => $floor->id,
                'name'          => $floor->name,
                'width_meters'  => (float) $floor->width_meters,
                'length_meters' => (float) $floor->length_meters,
                'tables'        => $tables,
            ];
        })->toArray();
    }
}
