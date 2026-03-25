<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerOrderController extends Controller
{
    /**
     * QR mengarah ke: /order/{store_slug}/{table_qr_code}
     * Contoh: /order/warung-kopi/abc-123-uuid
     */
    public function show(string $storeSlug, string $qrCode): Response|RedirectResponse
    {
        $store = Store::where('slug', $storeSlug)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            abort(404, 'Toko tidak ditemukan.');
        }

        $table = DiningTable::where('store_id', $store->id)
            ->where('qr_code', $qrCode)
            ->where('is_active', true)
            ->first();

        if (! $table) {
            abort(404, 'Meja tidak ditemukan.');
        }

        $products = $this->getProductsForStore($store);
        $categories = Category::where('brand_id', $store->brand_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $qrisPayment = PaymentMethod::where('brand_id', $store->brand_id)
            ->where('code', 'qris')
            ->where('is_active', true)
            ->first();

        return Inertia::render('Order/Index', [
            'store'  => $store->only('id', 'name', 'slug'),
            'table'  => [
                'id'   => $table->id,
                'name' => $table->name,
            ],
            'products'   => $products,
            'categories' => $categories,
            'qris_payment' => $qrisPayment ? [
                'name'         => $qrisPayment->name,
                'qrcode_image' => $qrisPayment->qrcode_image ? \Storage::disk('public')->url($qrisPayment->qrcode_image) : null,
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_id'        => ['required', 'exists:stores,id'],
            'table_id'        => ['required', 'exists:dining_tables,id'],
            'payment_method'  => ['required', 'in:cash,qris'],
            'customer_name'   => ['required', 'string', 'max:255'],
            'customer_email'  => ['required', 'email', 'max:255'],
            'customer_phone'  => ['required', 'string', 'max:20'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.001'],
            'items.*.notes'       => ['nullable', 'string', 'max:500'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ]);

        $store = Store::where('id', $data['store_id'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $table = DiningTable::where('id', $data['table_id'])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->firstOrFail();

        $order = DB::transaction(function () use ($store, $table, $data) {
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
                    'product'   => $product,
                    'price_log' => $priceLog,
                    'quantity'  => $qty,
                    'unit_price'=> $unitPrice,
                    'buy_price' => $buyPrice,
                    'discount'  => $discount,
                    'subtotal'  => $itemSubtotal,
                    'notes'     => $item['notes'] ?? null,
                ];
            }

            $discountAmount = 0;
            $taxRate = 0;
            $taxAmount = 0;
            $finalAmount = $subtotal - $discountAmount + $taxAmount;

            $customerId = $this->resolveCustomer($store->brand_id, [
                'customer_name'  => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
            ]);

            $order = Order::create([
                'store_id'        => $store->id,
                'table_id'        => $table->id,
                'customer_id'     => $customerId,
                'cashier_id'      => null,
                'order_code'      => $orderCode,
                'type'            => 'dine_in',
                'status'          => 'pending',
                'notes'           => $data['notes'] ?? null,
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_rate'        => $taxRate,
                'tax_amount'      => $taxAmount,
                'final_amount'    => $finalAmount,
                'payment_method'  => $data['payment_method'],
                'payment_status'  => 'unpaid',
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

        $successMsg = $data['payment_method'] === 'cash'
            ? "Pesanan {$order->order_code} berhasil dibuat. Silakan bayar di kasir."
            : "Pesanan {$order->order_code} berhasil dibuat. Scan QRIS untuk membayar.";

        return redirect()
            ->route('order.show', ['storeSlug' => $store->slug, 'qrCode' => $table->qr_code])
            ->with('success', $successMsg)
            ->with('order_payment_method', $data['payment_method'])
            ->with('order_final_amount', (float) $order->final_amount);
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
                    'sell_price'     => $sellPrice,
                    'image_url'      => $product->image ? \Storage::disk('public')->url($product->image) : null,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    private function resolveCustomer(int $brandId, array $data): int
    {
        $name = trim($data['customer_name']);
        $email = trim($data['customer_email']);
        $phone = trim($data['customer_phone']);

        $customer = Customer::where('brand_id', $brandId)
            ->where('email', $email)
            ->first();

        if ($customer) {
            $customer->update([
                'name'  => $name,
                'phone' => $phone ?: $customer->phone,
            ]);
            return $customer->id;
        }

        $customer = Customer::create([
            'brand_id' => $brandId,
            'name'     => $name,
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
}
