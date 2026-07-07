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
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Promo;
use Illuminate\Support\Facades\Cache;

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

        // Tidak di-cache: harga & stok berubah tiap transaksi; cache lama menampilkan harga/stok basi di layar kasir
        $products = $this->getProductsForStore($store);

        $categories = Category::where('brand_id', $store->brand_id)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'color'])
            ->values()
            ->toArray();

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
            'floor_plan'           => $floorPlan,
            'last_order_id'        => session('last_order_id'),
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
            'items.*.modifiers'  => ['nullable', 'array'],
            'items.*.modifiers.*.option_id' => ['required', 'exists:product_modifier_options,id'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'payment_method'   => ['required', Rule::in($validCodes)],
            'cash_received'    => ['nullable', 'numeric', 'min:0'],
            'discount_amount'  => ['nullable', 'numeric', 'min:0'],
            'promo_code'       => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['type'] === 'dine_in' && empty($data['table_id'])) {
            return back()->with('error', 'Pilih meja untuk pesanan dine-in.');
        }

        $order = DB::transaction(function () use ($store, $data, $user) {
            $shift = \App\Models\CashierShift::where('store_id', $store->id)
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->first();

            if (! $shift) {
                throw new \RuntimeException("Anda tidak memiliki shift yang sedang buka. Buka shift terlebih dahulu.");
            }

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
                
                // Track if this order has a rental package
                if ($product->is_rental_package && $product->rental_duration_minutes > 0) {
                    $hasRentalPackage = true;
                    $rentalDuration = $product->rental_duration_minutes;
                }

                $modifierPriceExtra = 0;
                $selectedModifiers = [];
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $mSelection) {
                        $opt = \App\Models\ProductModifierOption::with('group')->findOrFail($mSelection['option_id']);
                        if (! $opt->is_active || ! $opt->is_available) {
                            throw new \RuntimeException("Opsi '{$opt->name}' sedang tidak tersedia.");
                        }
                        $modifierPriceExtra += (float) $opt->price_extra;
                        $selectedModifiers[] = [
                            'modifier_option_id' => $opt->id,
                            'modifier_group_name' => $opt->group->name,
                            'modifier_option_name' => $opt->name,
                            'price_extra' => (float) $opt->price_extra,
                        ];
                    }
                }

                $itemSubtotal = ($unitPrice + $modifierPriceExtra - $discount) * $qty;
                $subtotal += $itemSubtotal;

                // Check stock if tracking
                // lockForUpdate: kunci baris stok sampai transaksi commit agar tidak oversell saat checkout paralel
                if ($product->track_stock) {
                    $inventory = StoreInventory::where('store_id', $store->id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
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
                    'selected_modifiers' => $selectedModifiers,
                ];
            }

            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $promoId = null;

            if (!empty($data['promo_code'])) {
                // lockForUpdate: serialize cek kuota + increment agar kuota tidak terlampaui saat 2 checkout paralel
                // strtoupper: konsisten dengan checkPromo() yang mencari uppercase
                $promo = Promo::where('brand_id', $store->brand_id)
                    ->where('code', strtoupper($data['promo_code']))
                    ->lockForUpdate()
                    ->first();

                if (!$promo || !$promo->isValid()) {
                    throw new \RuntimeException("Kode promo tidak valid atau sudah kedaluwarsa.");
                }
                if ($subtotal < $promo->min_purchase) {
                    throw new \RuntimeException("Total pesanan belum memenuhi minimum belanja promo.");
                }

                $calcDiskon = 0;
                if ($promo->type === 'percentage') {
                    $calcDiskon = round($subtotal * ($promo->value / 100));
                    if ($promo->max_discount && $calcDiskon > $promo->max_discount) {
                        $calcDiskon = $promo->max_discount;
                    }
                } else {
                    $calcDiskon = $promo->value;
                }

                $discountAmount = min($calcDiskon, $subtotal);
                $promoId = $promo->id;
                
                $promo->increment('used_count');
            }

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
                'shift_id'        => $shift->id,
                'table_id'        => ($data['type'] === 'dine_in' || $data['type'] === 'walk_in') ? $data['table_id'] : null,
                'customer_id'     => $customerId,
                'cashier_id'      => $user->id,
                'promo_id'        => $promoId,
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

                // Rental fields
                'is_rental'       => $hasRentalPackage ?? false,
                'rental_started_at' => ($hasRentalPackage ?? false) ? now() : null,
                'rental_duration_minutes' => $rentalDuration ?? 0,
                'rental_end_at'   => ($hasRentalPackage ?? false) ? now()->addMinutes(($rentalDuration ?? 0) + 10) : null,
            ]);

            // If rental package, turn on PS
            if (($hasRentalPackage ?? false) && $order->table_id) {
                $table = DiningTable::find($order->table_id);
                if ($table) {
                    $table->update(['tuya_status' => true]);
                    if ($table->tuya_device_id) {
                        app(\App\Services\TuyaService::class)->sendCommand($table->tuya_device_id, 'switch_1', true);
                    }
                }
            }

            foreach ($itemsData as $item) {
                $orderItem = OrderItem::create([
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

                foreach ($item['selected_modifiers'] as $m) {
                    $orderItem->modifiers()->create($m);
                }

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

        // Notifikasi dikirim SETELAH commit: agar kunci stok/promo cepat lepas
        // dan kegagalan kirim notifikasi tidak me-rollback order yang sudah dibayar.
        // try/catch: order sudah tersimpan; jangan sampai gagal notif bikin layar 500 lalu kasir submit dobel.
        try {
            $this->handlePostOrderNotifications($order);
        } catch (\Throwable $e) {
            \Log::warning('Gagal kirim notifikasi order ' . $order->order_code . ': ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.cashier.index', ['store' => $store->id])
            ->with('success', "Pesanan {$order->order_code} berhasil dibuat.")
            ->with('last_order_id', $order->id);
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

        $pending = Order::with(['table', 'customer', 'items.product'])
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
                'items'           => $o->items->map(fn($i) => [
                    'product_name' => $i->product_name,
                    'quantity'     => (float) $i->quantity,
                    'unit'         => $i->unit,
                    'subtotal'     => (float) $i->subtotal,
                ])->values()->toArray()
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

    public function checkPromo(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $request->validate([
            'code' => ['required', 'string'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $promo = Promo::where('brand_id', $store->brand_id)
            ->where('code', strtoupper($request->code))
            ->first();

        if (!$promo) {
            return response()->json(['error' => 'Kode promo tidak ditemukan.'], 404);
        }

        if (!$promo->isValid()) {
            return response()->json(['error' => 'Kode promo sudah kedaluwarsa atau mencapai limit kuota.'], 400);
        }

        if ($request->subtotal < $promo->min_purchase) {
            return response()->json(['error' => 'Belum memenuhi minimal belanja Rp ' . number_format($promo->min_purchase, 0, ',', '.')], 400);
        }

        $discount = 0;
        if ($promo->type === 'percentage') {
            $discount = round($request->subtotal * ($promo->value / 100));
            if ($promo->max_discount && $discount > $promo->max_discount) {
                $discount = $promo->max_discount;
            }
        } else {
            $discount = $promo->value;
        }

        $discount = min($discount, $request->subtotal);

        return response()->json([
            'success' => true,
            'promo' => $promo->only(['id', 'code', 'type', 'value', 'max_discount']),
            'discount_amount' => $discount,
            'message' => 'Promo berhasil digunakan.'
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
            ->first();
    }

    private function getProductsForStore(Store $store): array
    {
        $products = Product::with([
            'category',
            'inventories' => fn ($q) => $q->where('store_id', $store->id),
            'modifierGroups.options' => fn ($q) => $q->where('is_active', true)
        ])
            ->where('brand_id', $store->brand_id)
            ->where('is_available', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Batch harga aktif (hindari N+1): 1 query, ambil per produk yg started_at terbaru
        $priceByProduct = PriceLog::whereIn('product_id', $products->pluck('id'))
            ->where(fn ($q) => $q->where('store_id', $store->id)->orWhereNull('store_id'))
            ->whereNull('ended_at')
            ->orderBy('started_at') // asc: keyBy simpan yg terakhir = started_at terbaru
            ->get(['product_id', 'sell_price', 'buy_price'])
            ->keyBy('product_id');

        return $products
            ->map(function ($product) use ($priceByProduct) {
                $inventory = $product->inventories->first();
                $price = $priceByProduct->get($product->id);

                $sellPrice = (float) ($price?->sell_price ?? 0);
                if ($sellPrice <= 0) {
                    return null;
                }

                return [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'sku'            => $product->sku,
                    'category_id'    => $product->category_id,
                    'category_name'  => $product->category?->name,
                    'category_color' => $product->category?->color,
                    'unit'           => $product->unit,
                    'track_stock'    => $product->track_stock,
                    'current_stock'  => (float) ($inventory?->current_stock ?? 0),
                    'discount_percent' => (float) $product->discount_percent,
                    'sell_price'     => $sellPrice,
                    'image_url'      => $product->image ? \Storage::disk('public')->url($product->image) : null,
                    'modifier_groups' => $product->modifierGroups->map(fn($g) => [
                        'id' => $g->id,
                        'name' => $g->name,
                        'is_required' => $g->is_required,
                        'min_select' => $g->min_select,
                        'max_select' => $g->max_select,
                        'options' => $g->options->map(fn($o) => [
                            'id' => $o->id,
                            'name' => $o->name,
                            'price_extra' => (float) $o->price_extra,
                            'is_active' => (bool) $o->is_active,
                            'is_available' => (bool) $o->is_available,
                        ])
                    ]),
                    'is_rental_package' => (bool) $product->is_rental_package,
                    'rental_duration_minutes' => (int) $product->rental_duration_minutes,
                    'included_items_json' => $product->included_items_json,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    private function generateOrderCode(int $storeId): string
    {
        // ponytail: lockForUpdate serialize dua checkout paralel di store yg sama; dipanggil di dalam DB::transaction store().
        // Ceiling: order pertama hari itu (belum ada baris) tak terkunci — upgrade ke unique index (store_id, order_code) jika kolisi awal-hari muncul.
        $prefix = 'ORD-' . now()->format('Ymd') . '-';
        $last = Order::where('store_id', $storeId)
            ->where('order_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('order_code');

        $seq = 1;
        if ($last) {
            $seq = (int) substr($last, -4) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function buildFloorPlanForCashier(Store $store): array
    {
        $floors = Floor::with(['diningTables' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $activeOrdersByTable = Order::with('items')
            ->where('store_id', $store->id)
            ->whereNotNull('table_id')
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get()
            ->groupBy('table_id');

        return $floors->map(function ($floor) use ($activeOrdersByTable) {
            $tables = $floor->diningTables
                ->map(function ($t) use ($activeOrdersByTable) {
                    $orders = $activeOrdersByTable->get($t->id, collect())
                        ->map(fn ($o) => [
                            'id'          => $o->id,
                            'order_code'  => $o->order_code,
                            'status'      => $o->status,
                            'payment_status' => $o->payment_status,
                            'final_amount'=> (float) $o->final_amount,
                            'items_count' => $o->items->count(),
                            'created_at'  => $o->created_at->format('H:i'),
                            'is_rental'   => (bool) $o->is_rental,
                            'rental_started_at' => $o->rental_started_at?->toIso8601String(),
                            'rental_end_at' => $o->rental_end_at?->toIso8601String(),
                            'rental_duration_minutes' => $o->rental_duration_minutes,
                            'notes'       => $o->notes,
                            'items'       => $o->items->map(fn($item) => [
                                'name' => $item->product_name,
                                'qty' => (float) $item->quantity,
                                'price' => (float) $item->unit_price,
                                'total' => (float) $item->subtotal,
                            ])->values()->all(),
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
                        'tuya_device_id' => $t->tuya_device_id,
                        'tuya_status'   => (bool) $t->tuya_status,
                        'rental_price_per_hour' => (float) ($t->rental_price_per_hour ?? 0),
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
    private function handlePostOrderNotifications(Order $order): void
    {
        $order->load(['store', 'items.product', 'table']);
        $store = $order->store;
        $brandId = $store->brand_id;

        // Get users to notify (Admins of store + Brand Owner)
        $usersToNotify = User::where('brand_id', $brandId)
            ->where(function($q) use ($store) {
                $q->where('role', 'owner')
                  ->orWhere(function($sq) use ($store) {
                      $sq->where('store_id', $store->id)
                         ->whereIn('role', ['admin', 'manager']);
                  });
            })
            ->where('is_active', true)
            ->get();

        // 1. New Order Notification
        \Illuminate\Support\Facades\Notification::send($usersToNotify, new \App\Notifications\NewOrderNotification($order));

        // 2. Low Stock Checking
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && $product->track_stock) {
                $inventory = StoreInventory::where('store_id', $store->id)
                    ->where('product_id', $product->id)
                    ->first();
                
                if ($inventory && $inventory->current_stock <= $inventory->min_stock && $inventory->min_stock > 0) {
                    \Illuminate\Support\Facades\Notification::send($usersToNotify, new \App\Notifications\LowStockNotification(
                        $product->name,
                        $store->name,
                        (float) $inventory->current_stock,
                        $product->unit ?? 'pcs'
                    ));
                }
            }
        }

        // 3. Daily Target Checking
        if ($store->daily_sales_target > 0) {
            $todayRevenue = (float) Order::where('store_id', $store->id)
                ->whereDate('created_at', now()->toDateString())
                ->where('payment_status', 'paid')
                ->sum('final_amount');

            // Find previous and current revenue to check if crossing the threshold
            $prevRevenue = $todayRevenue - $order->final_amount;
            
            if ($prevRevenue < $store->daily_sales_target && $todayRevenue >= $store->daily_sales_target) {
                \Illuminate\Support\Facades\Notification::send($usersToNotify, new \App\Notifications\DailyTargetReachedNotification(
                    $store->name,
                    (float) $store->daily_sales_target,
                    $todayRevenue
                ));
            }
        }
    }
}
