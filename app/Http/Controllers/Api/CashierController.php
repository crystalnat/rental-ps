<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyExpense;
use App\Models\DiningTable;
use App\Models\Floor;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\PriceLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashierController extends Controller
{
    /**
     * Resolve store_id: dari request, user.store_id, atau (admin/owner) toko pertama brand.
     */
    private function resolveStoreId(Request $request, bool $fromBody = false): ?int
    {
        $user = $request->user();
        $storeId = $fromBody
            ? $request->input('store_id')
            : $request->query('store_id');

        if ($storeId) {
            return (int) $storeId;
        }

        if ($user->store_id) {
            return $user->store_id;
        }

        // Admin/owner tanpa store_id: ambil toko pertama dari brand
        if (in_array($user->role, ['owner', 'admin'])) {
            $first = Store::where('brand_id', $user->brand_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->value('id');

            return $first;
        }

        return null;
    }

    /**
     * Toko aktif kasir.
     * GET /api/cashier/store
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request);

        if (! $storeId) {
            return response()->json(['message' => 'Toko tidak ditemukan. Pilih toko terlebih dahulu.'], 422);
        }

        $store = Store::where('id', $storeId)
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        return response()->json([
            'store' => $this->formatStore($store),
        ]);
    }

    /**
     * Daftar toko (untuk owner/admin ganti toko).
     * GET /api/cashier/stores
     */
    public function stores(Request $request): JsonResponse
    {
        $user = $request->user();
        $stores = Store::where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active', 'receipt_print_enabled']);

        return response()->json([
            'stores' => $stores->map(fn ($s) => [
                'id'                      => $s->id,
                'name'                    => $s->name,
                'slug'                    => $s->slug,
                'is_active'               => $s->is_active,
                'receipt_print_enabled'   => (bool) $s->receipt_print_enabled,
            ]),
        ]);
    }

    /**
     * Daftar produk toko.
     * GET /api/cashier/products
     */
    public function products(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request);

        if (! $storeId) {
            return response()->json(['message' => 'Store ID diperlukan. Pilih toko terlebih dahulu.'], 422);
        }

        $store = Store::where('id', $storeId)
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        $products = $this->getProductsForStore($store);

        return response()->json(['products' => $products]);
    }

    /**
     * Daftar kategori.
     * GET /api/cashier/categories
     */
    public function categories(Request $request): JsonResponse
    {
        $user = $request->user();
        $categories = Category::where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'color', 'sort_order']);

        return response()->json([
            'categories' => $categories->map(fn ($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'slug'       => $c->slug,
                'icon'       => $c->icon,
                'color'      => $c->color,
                'sort_order' => $c->sort_order,
            ]),
        ]);
    }

    /**
     * Daftar meja toko.
     * GET /api/cashier/tables
     */
    public function tables(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request);

        if (! $storeId) {
            return response()->json(['message' => 'Store ID diperlukan. Pilih toko terlebih dahulu.'], 422);
        }

        $tables = DiningTable::where('store_id', $storeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'floor_id', 'capacity', 'status', 'is_active']);

        return response()->json([
            'tables' => $tables->map(fn ($t) => [
                'id'       => $t->id,
                'name'     => $t->name,
                'floor_id' => $t->floor_id,
                'capacity' => $t->capacity,
                'status'   => $t->status,
                'is_active'=> $t->is_active,
            ]),
        ]);
    }

    /**
     * Daftar metode pembayaran.
     * GET /api/cashier/payment-methods
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $user = $request->user();
        $methods = PaymentMethod::where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'payment_methods' => $methods->map(fn ($m) => [
                'id'                  => $m->id,
                'name'                => $m->name,
                'code'                => $m->code,
                'requires_cash_input' => $m->requires_cash_input,
                'qrcode_image'        => $m->qrcode_image ? \Storage::disk('public')->url($m->qrcode_image) : null,
            ]),
        ]);
    }

    /**
     * Buat order dari kasir (walk-in, dine-in, takeaway).
     * POST /api/cashier/orders
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request, fromBody: true);

        $validCodes = PaymentMethod::where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->pluck('code')
            ->toArray();

        if (empty($validCodes)) {
            return response()->json(['message' => 'Tidak ada metode pembayaran tersedia.'], 422);
        }

        $rules = [
            'store_id'         => ['nullable', 'integer', 'exists:stores,id'],
            'type'             => ['required', 'in:walk_in,dine_in,takeaway'],
            'table_id'         => ['nullable', 'integer', 'exists:dining_tables,id'],
            'customer_name'    => ['nullable', 'string', 'max:255'],
            'customer_phone'   => ['nullable', 'string', 'max:20'],
            'customer_email'   => ['nullable', 'email', 'max:255'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.001'],
            'items.*.notes'       => ['nullable', 'string', 'max:500'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'payment_method'   => ['required', 'string', 'in:' . implode(',', $validCodes)],
            'cash_received'    => ['nullable', 'numeric', 'min:0'],
            'created_at'       => ['nullable', 'string', 'date'],
            'paid_at'          => ['nullable', 'string', 'date'],
        ];

        $data = $request->validate($rules);

        $storeId = $data['store_id'] ?? $this->resolveStoreId($request, fromBody: true);
        if (! $storeId) {
            return response()->json(['message' => 'Store ID diperlukan. Pilih toko terlebih dahulu.'], 422);
        }

        $store = Store::where('id', $storeId)
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        if ($data['type'] === 'dine_in' && empty($data['table_id'])) {
            return response()->json(['message' => 'Meja wajib untuk pesanan dine-in.'], 422);
        }

        $table = null;
        if (! empty($data['table_id'])) {
            $table = DiningTable::where('id', $data['table_id'])
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->first();
            if (! $table) {
                return response()->json(['message' => 'Meja tidak ditemukan.'], 404);
            }
        }

        $paymentMethod = PaymentMethod::where('brand_id', $user->brand_id)
            ->where('code', $data['payment_method'])
            ->where('is_active', true)
            ->first();

        if (! $paymentMethod) {
            return response()->json(['message' => 'Metode pembayaran tidak valid.'], 422);
        }

        try {
            $order = DB::transaction(function () use ($store, $table, $data, $user, $paymentMethod) {
                $subtotal = 0;
                $itemsData = [];

                foreach ($data['items'] as $item) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('brand_id', $store->brand_id)
                        ->whereNull('deleted_at')
                        ->first();

                    if (! $product) {
                        throw new \RuntimeException("Produk tidak ditemukan.");
                    }

                    $priceLog = PriceLog::where('product_id', $product->id)
                        ->where(fn ($q) => $q->where('store_id', $store->id)->orWhereNull('store_id'))
                        ->whereNull('ended_at')
                        ->latest('started_at')
                        ->first();

                    if (! $priceLog) {
                        throw new \RuntimeException("Harga produk {$product->name} belum diatur.");
                    }

                    $qty = (float) $item['quantity'];
                    $unitPrice = (float) $priceLog->sell_price;
                    $buyPrice = (float) $priceLog->buy_price;
                    $discount = 0;
                    $itemSubtotal = ($unitPrice - $discount) * $qty;
                    $subtotal += $itemSubtotal;

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

                $discountAmount = 0;
                $taxRate = 0;
                $taxAmount = 0;
                $finalAmount = $subtotal - $discountAmount + $taxAmount;

                if ($paymentMethod->requires_cash_input) {
                    $cashReceived = (float) ($data['cash_received'] ?? 0);
                    if ($cashReceived < $finalAmount) {
                        throw new \RuntimeException("Uang tunai kurang. Total: Rp " . number_format($finalAmount, 0, ',', '.'));
                    }
                }

                $customerId = null;
                if (! empty($data['customer_name']) || ! empty($data['customer_email'])) {
                    $customerId = $this->resolveCustomer($store->brand_id, [
                        'customer_name'  => $data['customer_name'] ?? '',
                        'customer_email' => $data['customer_email'] ?? '',
                        'customer_phone' => $data['customer_phone'] ?? '',
                    ]);
                }

                $orderCode = $this->generateOrderCode($store->id);

                $cashReceived = (float) ($data['cash_received'] ?? $finalAmount);
                $changeAmount = $paymentMethod->requires_cash_input
                    ? max(0, $cashReceived - $finalAmount)
                    : null;

                $createdAt = ! empty($data['created_at'])
                    ? \Carbon\Carbon::parse($data['created_at'])
                    : now();
                $paidAt = ! empty($data['paid_at'])
                    ? \Carbon\Carbon::parse($data['paid_at'])
                    : $createdAt;

                $order = Order::create([
                    'store_id'         => $store->id,
                    'table_id'         => $table?->id,
                    'customer_id'      => $customerId,
                    'cashier_id'       => $user->id,
                    'order_code'       => $orderCode,
                    'type'             => $data['type'],
                    'status'           => 'done',
                    'notes'            => $data['notes'] ?? null,
                    'subtotal'         => $subtotal,
                    'discount_amount'  => $discountAmount,
                    'tax_rate'         => $taxRate,
                    'tax_amount'       => $taxAmount,
                    'final_amount'     => $finalAmount,
                    'payment_method'   => $data['payment_method'],
                    'payment_status'   => 'paid',
                    'cash_received'    => $paymentMethod->requires_cash_input ? $cashReceived : null,
                    'change_amount'    => $changeAmount,
                    'created_at'       => $createdAt,
                    'paid_at'          => $paidAt,
                ]);

                foreach ($itemsData as $item) {
                    OrderItem::create([
                        'order_id'        => $order->id,
                        'product_id'      => $item['product']->id,
                        'price_log_id'    => $item['price_log']->id,
                        'product_name'    => $item['product']->name,
                        'quantity'        => $item['quantity'],
                        'unit'            => $item['product']->unit,
                        'unit_price'      => $item['unit_price'],
                        'buy_price'       => $item['buy_price'],
                        'discount_amount' => $item['discount'],
                        'subtotal'        => $item['subtotal'],
                        'notes'           => $item['notes'],
                    ]);

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

            return response()->json([
                'message' => 'Pesanan berhasil dibuat.',
                'order'   => $this->formatOrder($order),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Daftar pesanan menunggu (dari QR, belum dibayar).
     * GET /api/cashier/pending-orders
     */
    public function pendingOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request);

        if (! $storeId) {
            return response()->json(['message' => 'Store ID diperlukan. Pilih toko terlebih dahulu.'], 422);
        }

        $store = Store::where('id', $storeId)
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        $orders = Order::with(['items', 'table:id,name', 'customer:id,name,email,phone'])
            ->where('store_id', $store->id)
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'orders' => $orders->map(fn ($o) => $this->formatOrder($o)),
        ]);
    }

    /**
     * Riwayat transaksi terbayar (read-only, untuk kasir).
     * GET /api/cashier/orders
     *
     * Query: store_id, page, per_page (max 50).
     * Filter paid_at (prioritas): date_from + date_to (Y-m-d, inklusif) |
     * days (1, 7, atau 30 = gulir N hari termasuk hari ini) |
     * date (Y-m-d, satu hari) |
     * default: 7 hari terakhir.
     */
    public function orderHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request);

        if (! $storeId) {
            return response()->json(['message' => 'Store ID diperlukan. Pilih toko terlebih dahulu.'], 422);
        }

        $store = Store::where('id', $storeId)
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $page = max((int) $request->query('page', 1), 1);

        $query = Order::query()
            ->where('store_id', $store->id)
            ->where('payment_status', 'paid')
            ->whereNull('deleted_at')
            ->orderByDesc('paid_at');

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $days = $request->query('days');
        $date = $request->query('date');

        $hasRange = is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            && is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo);

        if ($hasRange) {
            if ($dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }
            $query->whereDate('paid_at', '>=', $dateFrom)
                ->whereDate('paid_at', '<=', $dateTo);
        } elseif ($days !== null && $days !== '') {
            $d = (int) $days;
            if (in_array($d, [1, 7, 30], true)) {
                $query->whereDate('paid_at', '>=', now()->subDays($d - 1)->toDateString());
            } else {
                $query->whereDate('paid_at', '>=', now()->subDays(6)->toDateString());
            }
        } elseif (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $query->whereDate('paid_at', $date);
        } else {
            $query->whereDate('paid_at', '>=', now()->subDays(6)->toDateString());
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'orders' => $paginator->getCollection()->map(fn ($o) => $this->formatOrder($o, false))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Detail order.
     * GET /api/cashier/orders/{id}
     */
    public function showOrder(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $order = Order::with(['items', 'table:id,name', 'customer:id,name,email,phone'])
            ->where('id', $id)
            ->whereHas('store', fn ($q) => $q->where('brand_id', $user->brand_id))
            ->whereNull('deleted_at')
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        return response()->json(['order' => $this->formatOrder($order)]);
    }

    /**
     * Terima pembayaran pesanan dari meja.
     * POST /api/cashier/orders/{id}/pay
     */
    public function payOrder(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $order = Order::with(['items', 'table:id,name', 'store'])
            ->where('id', $id)
            ->whereHas('store', fn ($q) => $q->where('brand_id', $user->brand_id))
            ->whereNull('deleted_at')
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Pesanan sudah dibayar.'], 422);
        }

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'Pesanan telah dibatalkan.'], 422);
        }

        $validCodes = PaymentMethod::where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->pluck('code')
            ->toArray();

        if (empty($validCodes)) {
            return response()->json(['message' => 'Tidak ada metode pembayaran tersedia.'], 422);
        }

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:' . implode(',', $validCodes)],
            'cash_received'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $paymentMethod = PaymentMethod::where('brand_id', $user->brand_id)
            ->where('code', $data['payment_method'])
            ->where('is_active', true)
            ->first();

        if (! $paymentMethod) {
            return response()->json(['message' => 'Metode pembayaran tidak valid.'], 422);
        }

        $finalAmount = (float) $order->final_amount;

        if ($paymentMethod->requires_cash_input) {
            $cashReceived = (float) ($data['cash_received'] ?? 0);
            if ($cashReceived < $finalAmount) {
                return response()->json([
                    'message' => 'Uang tunai kurang. Total: Rp ' . number_format($finalAmount, 0, ',', '.'),
                ], 422);
            }
        }

        $cashReceived = (float) ($data['cash_received'] ?? $finalAmount);
        $changeAmount = $paymentMethod->requires_cash_input
            ? max(0, $cashReceived - $finalAmount)
            : null;

        $order->update([
            'cashier_id'     => $user->id,
            'payment_method' => $data['payment_method'],
            'payment_status' => 'paid',
            'cash_received'  => $paymentMethod->requires_cash_input ? $cashReceived : null,
            'change_amount'  => $changeAmount,
            'paid_at'        => now(),
            'status'         => 'done',
        ]);

        if ($order->customer_id) {
            Customer::where('id', $order->customer_id)->increment('total_orders');
            Customer::where('id', $order->customer_id)->increment('total_spent', $finalAmount);
        }

        $order->load(['items', 'table:id,name']);

        return response()->json([
            'message' => 'Pembayaran berhasil diterima.',
            'order'   => $this->formatOrder($order),
        ]);
    }

    /**
     * Denah meja + pesanan aktif per meja.
     * GET /api/cashier/floor-plan
     */
    public function floorPlan(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request);

        if (! $storeId) {
            return response()->json(['message' => 'Store ID diperlukan. Pilih toko terlebih dahulu.'], 422);
        }

        $store = Store::where('id', $storeId)
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        $floors = Floor::with([
            'diningTables' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            'diningTables.activeOrder' => ['items'],
            'elements',
        ])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $floorsData = $floors->map(function ($floor) {
            $tables = $floor->diningTables->map(function ($table) {
                $activeOrder = $table->activeOrder->first();
                return [
                    'id'           => $table->id,
                    'name'         => $table->name,
                    'capacity'     => $table->capacity,
                    'status'       => $table->status,
                    'x_meters'     => (float) $table->x_meters,
                    'y_meters'     => (float) $table->y_meters,
                    'width_meters' => (float) $table->width_meters,
                    'length_meters'=> (float) $table->length_meters,
                    'rotation_deg' => (int) $table->rotation_deg,
                    'shape'        => $table->shape,
                    'active_order' => $activeOrder ? [
                        'id'           => $activeOrder->id,
                        'order_code'   => $activeOrder->order_code,
                        'status'       => $activeOrder->status,
                        'payment_status'=> $activeOrder->payment_status,
                        'final_amount' => (float) $activeOrder->final_amount,
                        'items_count'  => $activeOrder->items->count(),
                    ] : null,
                ];
            })->toArray();

            $elements = $floor->elements->map(fn ($e) => [
                'id'           => $e->id,
                'type'         => $e->type,
                'name'         => $e->name,
                'x_meters'     => (float) $e->x_meters,
                'y_meters'     => (float) $e->y_meters,
                'width_meters' => (float) $e->width_meters,
                'length_meters'=> (float) $e->length_meters,
                'rotation_deg' => (int) $e->rotation_deg,
            ])->toArray();

            return [
                'id'            => $floor->id,
                'name'          => $floor->name,
                'width_meters'  => (float) $floor->width_meters,
                'length_meters' => (float) $floor->length_meters,
                'sort_order'    => $floor->sort_order,
                'tables'        => $tables,
                'elements'      => $elements,
            ];
        })->toArray();

        return response()->json([
            'floors' => $floorsData,
        ]);
    }

    /**
     * Ringkasan: pending count, penjualan hari ini.
     * GET /api/cashier/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request);

        if (! $storeId) {
            return response()->json(['message' => 'Store ID diperlukan. Pilih toko terlebih dahulu.'], 422);
        }

        $store = Store::where('id', $storeId)
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        $today = now()->toDateString();

        $pendingCount = Order::where('store_id', $store->id)
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->count();

        $salesToday = Order::where('store_id', $store->id)
            ->where('payment_status', 'paid')
            ->whereDate('paid_at', $today)
            ->whereNull('deleted_at')
            ->sum('final_amount');

        $ordersToday = Order::where('store_id', $store->id)
            ->where('payment_status', 'paid')
            ->whereDate('paid_at', $today)
            ->whereNull('deleted_at')
            ->count();

        $expensesToday = DailyExpense::where('store_id', $store->id)
            ->whereDate('expense_date', $today)
            ->whereNull('deleted_at')
            ->sum('amount');

        return response()->json([
            'pending_orders_count' => $pendingCount,
            'sales_today'          => (float) $salesToday,
            'orders_today'         => $ordersToday,
            'expenses_today'       => (float) $expensesToday,
        ]);
    }

    private const EXPENSE_CATEGORIES = [
        'purchase'    => 'Pembelian',
        'supplies'    => 'Perlengkapan',
        'utilities'   => 'Listrik/Utilities',
        'maintenance' => 'Pemeliharaan',
        'salary'      => 'Gaji',
        'other'       => 'Lainnya',
    ];

    /**
     * Daftar pengeluaran harian.
     * GET /api/cashier/expenses?store_id=&date_from=&date_to=
     */
    public function expenses(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request);

        if (! $storeId) {
            return response()->json(['message' => 'Store ID diperlukan.'], 422);
        }

        $store = Store::where('id', $storeId)
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        $dateFrom = $request->query('date_from', now()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $expenses = DailyExpense::with('creator:id,name')
            ->where('store_id', $store->id)
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->whereNull('deleted_at')
            ->orderByDesc('expense_date')
            ->orderByDesc('created_at')
            ->get();

        $totalAmount = (float) $expenses->sum('amount');

        return response()->json([
            'expenses'      => $expenses->map(fn ($e) => [
                'id'           => $e->id,
                'category'     => $e->category,
                'category_label' => self::EXPENSE_CATEGORIES[$e->category] ?? $e->category,
                'description'  => $e->description,
                'amount'       => (float) $e->amount,
                'expense_date' => $e->expense_date->format('Y-m-d'),
                'created_at'   => $e->created_at->toIso8601String(),
                'creator_name' => $e->creator?->name,
            ]),
            'total_amount'   => $totalAmount,
            'category_options' => array_map(
                fn ($v, $l) => ['value' => $v, 'label' => $l],
                array_keys(self::EXPENSE_CATEGORIES),
                array_values(self::EXPENSE_CATEGORIES),
            ),
        ]);
    }

    /**
     * Tambah pengeluaran harian.
     * POST /api/cashier/expenses
     */
    public function storeExpense(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $this->resolveStoreId($request, fromBody: true);

        if (! $storeId) {
            return response()->json(['message' => 'Store ID diperlukan.'], 422);
        }

        $store = Store::where('id', $storeId)
            ->where('brand_id', $user->brand_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Toko tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'store_id'     => ['nullable', 'integer', 'exists:stores,id'],
            'category'     => ['required', 'string', 'in:purchase,supplies,utilities,maintenance,salary,other'],
            'description'  => ['required', 'string', 'max:500'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
        ]);

        $expense = DailyExpense::create([
            'store_id'     => $store->id,
            'created_by'   => $user->id,
            'category'     => $data['category'],
            'description'  => $data['description'],
            'amount'       => $data['amount'],
            'expense_date' => $data['expense_date'],
        ]);

        return response()->json([
            'message' => 'Pengeluaran berhasil dicatat.',
            'expense'  => [
                'id'           => $expense->id,
                'category'     => $expense->category,
                'category_label' => self::EXPENSE_CATEGORIES[$expense->category] ?? $expense->category,
                'description'  => $expense->description,
                'amount'       => (float) $expense->amount,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'created_at'   => $expense->created_at->toIso8601String(),
            ],
        ], 201);
    }

    private function formatStore(Store $store): array
    {
        return [
            'id'                      => $store->id,
            'name'                    => $store->name,
            'slug'                    => $store->slug,
            'address'                 => $store->address,
            'city'                    => $store->city,
            'phone'                   => $store->phone,
            'open_time'               => $store->open_time,
            'close_time'              => $store->close_time,
            'is_active'               => $store->is_active,
            'receipt_print_enabled'   => (bool) $store->receipt_print_enabled,
        ];
    }

    private function formatOrder(Order $order, bool $includeItems = true): array
    {
        if ($includeItems) {
            $order->loadMissing(['items', 'table:id,name', 'customer:id,name,email,phone']);
        } else {
            $order->loadMissing(['table:id,name', 'customer:id,name,email,phone']);
        }

        return [
            'id'              => $order->id,
            'order_code'      => $order->order_code,
            'type'            => $order->type,
            'status'          => $order->status,
            'created_at'      => $order->created_at->toIso8601String(),
            'subtotal'        => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'tax_amount'      => (float) $order->tax_amount,
            'final_amount'    => (float) $order->final_amount,
            'payment_method'  => $order->payment_method,
            'payment_status'  => $order->payment_status,
            'cash_received'   => $order->cash_received ? (float) $order->cash_received : null,
            'change_amount'   => $order->change_amount ? (float) $order->change_amount : null,
            'paid_at'         => $order->paid_at?->toIso8601String(),
            'table'           => $order->table ? ['id' => $order->table->id, 'name' => $order->table->name] : null,
            'customer'        => $order->customer ? [
                'id'    => $order->customer->id,
                'name'  => $order->customer->name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
            ] : null,
            'items'           => $includeItems
                ? $order->items->map(fn ($i) => [
                    'product_id'   => $i->product_id,
                    'product_name' => $i->product_name,
                    'quantity'     => (float) $i->quantity,
                    'unit_price'   => (float) $i->unit_price,
                    'subtotal'     => (float) $i->subtotal,
                    'notes'        => $i->notes,
                ])->toArray()
                : [],
        ];
    }

    private function resolveCustomer(int $brandId, array $data): ?int
    {
        $name = trim($data['customer_name'] ?? '');
        $email = trim($data['customer_email'] ?? '');
        $phone = trim($data['customer_phone'] ?? '');

        if (empty($name) && empty($email)) {
            return null;
        }

        $email = $email ?: 'guest-' . uniqid() . '@temp.local';

        $customer = Customer::where('brand_id', $brandId)
            ->where('email', $email)
            ->first();

        if ($customer) {
            $customer->update([
                'name'  => $name ?: $customer->name,
                'phone' => $phone ?: $customer->phone,
            ]);

            return $customer->id;
        }

        $customer = Customer::create([
            'brand_id' => $brandId,
            'name'     => $name ?: 'Guest',
            'email'    => $email,
            'phone'    => $phone ?: null,
        ]);

        return $customer->id;
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

    private function getProductsForStore(Store $store): array
    {
        return Product::with(['category', 'inventories' => fn ($q) => $q->where('store_id', $store->id)])
            ->where('brand_id', $store->brand_id)
            ->where('is_available', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
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
                    'sell_price'     => $sellPrice,
                    'image_url'      => $product->image ? \Storage::disk('public')->url($product->image) : null,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }
}
