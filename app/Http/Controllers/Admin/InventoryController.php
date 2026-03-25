<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyExpense;
use App\Models\PriceLog;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockMutation;
use App\Models\Store;
use App\Models\StoreInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);

        if (! $store) {
            return Inertia::render('Admin/Inventory/SelectStore', [
                'stores' => Store::where('brand_id', $user->brand_id)
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']),
            ]);
        }

        $products = Product::with(['category'])
            ->where('brand_id', $store->brand_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'category_id'   => $p->category_id,
                'category_name' => $p->category?->name,
                'category_color'=> $p->category?->color,
                'unit'          => $p->unit,
                'track_stock'   => $p->track_stock,
                'current_stock' => (float) ($p->inventories()->where('store_id', $store->id)->first()?->current_stock ?? 0),
                'image_url'     => $p->image ? \Storage::disk('public')->url($p->image) : null,
            ]);

        $categories = Category::where('brand_id', $store->brand_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $recentStockIns = StockIn::with(['product', 'creator'])
            ->where('store_id', $store->id)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn ($s) => [
                'id'             => $s->id,
                'product_name'   => $s->product->name,
                'product_image'  => $s->product->image ? \Storage::disk('public')->url($s->product->image) : null,
                'quantity'       => (float) $s->quantity,
                'unit'           => $s->product->unit,
                'buy_price'      => (float) $s->buy_price,
                'total_amount'   => (float) $s->total_amount,
                'add_to_stock'   => $s->add_to_stock,
                'add_to_expense' => $s->add_to_expense,
                'notes'          => $s->notes,
                'created_at'     => $s->created_at->format('d M Y H:i'),
                'created_at_iso' => $s->created_at->toIso8601String(),
                'creator_name'   => $s->creator?->name,
            ]);

        $stores = [];
        if ($user->role === 'owner' || $user->role === 'admin') {
            $stores = Store::where('brand_id', $user->brand_id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);
        }

        return Inertia::render('Admin/Inventory/Index', [
            'store'          => $store->only('id', 'name', 'slug'),
            'products'       => $products,
            'categories'     => $categories,
            'recent_stock_ins' => $recentStockIns,
            'stores'         => $stores,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);

        if (! $store) {
            return back()->with('error', 'Pilih toko terlebih dahulu.');
        }

        $items = $request->input('items', []);
        if (! is_array($items) || count($items) === 0) {
            return back()->with('error', 'Tambahkan minimal satu item.');
        }

        $processed = 0;
        $errors = [];

        foreach ($items as $i => $raw) {
            $data = [
                'product_id'     => $raw['product_id'] ?? null,
                'is_new_product' => (bool) ($raw['is_new_product'] ?? false),
                'name'           => $raw['name'] ?? '',
                'category_id'    => $raw['category_id'] ?? null,
                'unit'           => $raw['unit'] ?? 'pcs',
                'sell_price'     => $raw['sell_price'] ?? null,
                'quantity'       => $raw['quantity'] ?? 0,
                'buy_price'      => $raw['buy_price'] ?? 0,
                'add_to_stock'   => $raw['add_to_stock'] ?? true,
                'add_to_expense' => $raw['add_to_expense'] ?? true,
                'notes'          => $raw['notes'] ?? null,
            ];

            $validator = \Illuminate\Support\Facades\Validator::make($data, [
                'product_id'     => ['required_if:is_new_product,false', 'nullable', 'exists:products,id'],
                'name'           => ['required_if:is_new_product,true', 'nullable', 'string', 'max:255'],
                'category_id'    => ['nullable', 'exists:categories,id'],
                'unit'           => ['required', 'string', 'max:50'],
                'sell_price'     => ['nullable', 'numeric', 'min:0'],
                'quantity'       => ['required', 'numeric', 'min:0.001'],
                'buy_price'      => ['required', 'numeric', 'min:0'],
                'add_to_stock'   => ['boolean'],
                'add_to_expense' => ['boolean'],
                'notes'          => ['nullable', 'string', 'max:500'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Item " . ($i + 1) . ": " . implode(', ', $validator->errors()->all());
                continue;
            }

            try {
                DB::transaction(function () use ($store, $data, $user) {
                    $product = null;

                    if ($data['is_new_product']) {
                        $product = Product::create([
                            'brand_id'     => $store->brand_id,
                            'category_id'  => $data['category_id'] ?? null,
                            'name'         => $data['name'],
                            'slug'         => $this->generateUniqueSlug($data['name']),
                            'unit'         => $data['unit'],
                            'track_stock'  => true,
                            'is_available' => true,
                            'is_active'    => true,
                        ]);
                        StoreInventory::create([
                            'store_id'      => $store->id,
                            'product_id'    => $product->id,
                            'current_stock' => 0,
                            'min_stock'     => 0,
                        ]);
                        PriceLog::create([
                            'product_id' => $product->id,
                            'store_id'   => $store->id,
                            'buy_price'  => (float) $data['buy_price'],
                            'sell_price' => (float) ($data['sell_price'] ?? $data['buy_price']),
                            'started_at' => now(),
                            'created_by' => $user->id,
                        ]);
                    } else {
                        $product = Product::where('id', $data['product_id'])
                            ->where('brand_id', $store->brand_id)
                            ->firstOrFail();
                    }

                    $qty = (float) $data['quantity'];
                    $buyPrice = (float) $data['buy_price'];
                    $totalAmount = $qty * $buyPrice;

                    $dailyExpenseId = null;
                    if ($data['add_to_expense']) {
                        $expense = DailyExpense::create([
                            'store_id'     => $store->id,
                            'created_by'   => $user->id,
                            'category'     => 'purchase',
                            'description'  => "Pembelian {$product->name} × {$qty} {$product->unit}",
                            'amount'       => $totalAmount,
                            'expense_date' => now()->toDateString(),
                        ]);
                        $dailyExpenseId = $expense->id;
                    }

                    $stockBefore = 0;
                    $stockAfter = 0;
                    if ($data['add_to_stock']) {
                        $inv = StoreInventory::firstOrCreate(
                            ['store_id' => $store->id, 'product_id' => $product->id],
                            ['current_stock' => 0, 'min_stock' => 0]
                        );
                        $stockBefore = (float) $inv->current_stock;
                        $stockAfter = $stockBefore + $qty;
                        $inv->update(['current_stock' => $stockAfter]);
                    }

                    $stockIn = StockIn::create([
                        'store_id'         => $store->id,
                        'product_id'       => $product->id,
                        'quantity'         => $qty,
                        'buy_price'        => $buyPrice,
                        'total_amount'     => $totalAmount,
                        'add_to_stock'     => $data['add_to_stock'],
                        'add_to_expense'   => $data['add_to_expense'],
                        'daily_expense_id' => $dailyExpenseId,
                        'notes'            => $data['notes'],
                        'created_by'       => $user->id,
                    ]);

                    if ($data['add_to_stock']) {
                        StockMutation::create([
                            'store_id'       => $store->id,
                            'product_id'     => $product->id,
                            'type'           => 'in',
                            'quantity'       => $qty,
                            'stock_before'   => $stockBefore,
                            'stock_after'    => $stockAfter,
                            'mutatable_type' => StockIn::class,
                            'mutatable_id'   => $stockIn->id,
                            'notes'          => $data['notes'],
                            'created_by'     => $user->id,
                        ]);
                    }
                });
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = "Item " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        if (count($errors) > 0 && $processed === 0) {
            return back()->with('error', implode(' ', $errors));
        }

        if ($processed > 0) {
            $msg = $processed === 1 ? 'Barang masuk berhasil dicatat.' : "{$processed} barang masuk berhasil dicatat.";
            return back()->with('success', $msg);
        }

        return back()->with('error', implode(' ', $errors));
    }

    public function update(Request $request, StockIn $stockIn): RedirectResponse
    {
        $user = Auth::user();
        $store = $this->resolveStore($user, $request);
        if (! $store) {
            return back()->with('error', 'Pilih toko terlebih dahulu.');
        }
        abort_unless($stockIn->store_id === $store->id, 403);

        $data = $request->validate([
            'quantity'       => ['required', 'numeric', 'min:0.001'],
            'buy_price'      => ['required', 'numeric', 'min:0'],
            'add_to_stock'   => ['boolean'],
            'add_to_expense' => ['boolean'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $oldQty = (float) $stockIn->quantity;
        $oldTotal = (float) $stockIn->total_amount;
        $oldAddToStock = $stockIn->add_to_stock;
        $oldAddToExpense = $stockIn->add_to_expense;
        $oldDailyExpenseId = $stockIn->daily_expense_id;

        $newQty = (float) $data['quantity'];
        $newBuyPrice = (float) $data['buy_price'];
        $newTotal = $newQty * $newBuyPrice;
        $newAddToStock = $data['add_to_stock'] ?? true;
        $newAddToExpense = $data['add_to_expense'] ?? true;

        $product = $stockIn->product;

        DB::transaction(function () use (
            $store, $stockIn, $product,
            $oldQty, $oldTotal, $oldAddToStock, $oldAddToExpense, $oldDailyExpenseId,
            $newQty, $newBuyPrice, $newTotal, $newAddToStock, $newAddToExpense,
            $data
        ) {
            // 1. Revert: kurangi stok lama jika dulu masuk stok
            if ($oldAddToStock) {
                $inv = StoreInventory::where('store_id', $store->id)
                    ->where('product_id', $product->id)
                    ->first();
                if ($inv) {
                    $inv->update(['current_stock' => max(0, (float) $inv->current_stock - $oldQty)]);
                }
            }

            // 2. Revert pengeluaran: hapus DailyExpense lama jika tidak lagi add_to_expense
            if ($oldAddToExpense && $oldDailyExpenseId && ! $newAddToExpense) {
                DailyExpense::find($oldDailyExpenseId)?->delete(); // soft delete
                $stockIn->daily_expense_id = null;
            }

            // 3. Revert StockMutation lama jika dulu add_to_stock
            if ($oldAddToStock) {
                StockMutation::where('mutatable_type', StockIn::class)
                    ->where('mutatable_id', $stockIn->id)
                    ->delete();
            }

            // 4. Apply stok baru
            $stockBefore = 0;
            $stockAfter = 0;
            if ($newAddToStock) {
                $inv = StoreInventory::firstOrCreate(
                    ['store_id' => $store->id, 'product_id' => $product->id],
                    ['current_stock' => 0, 'min_stock' => 0]
                );
                $stockBefore = (float) $inv->current_stock;
                $stockAfter = $stockBefore + $newQty;
                $inv->update(['current_stock' => $stockAfter]);

                StockMutation::create([
                    'store_id'       => $store->id,
                    'product_id'     => $product->id,
                    'type'           => 'in',
                    'quantity'       => $newQty,
                    'stock_before'   => $stockBefore,
                    'stock_after'    => $stockAfter,
                    'mutatable_type' => StockIn::class,
                    'mutatable_id'   => $stockIn->id,
                    'notes'          => $data['notes'] ?? null,
                    'created_by'     => Auth::id(),
                ]);
            }

            // 5. Apply pengeluaran
            if ($newAddToExpense) {
                if ($oldAddToExpense && $stockIn->daily_expense_id) {
                    DailyExpense::where('id', $stockIn->daily_expense_id)->update([
                        'amount'      => $newTotal,
                        'description' => "Pembelian {$product->name} × {$newQty} {$product->unit}",
                    ]);
                } else {
                    $expense = DailyExpense::create([
                        'store_id'     => $store->id,
                        'created_by'   => Auth::id(),
                        'category'     => 'purchase',
                        'description'  => "Pembelian {$product->name} × {$newQty} {$product->unit}",
                        'amount'       => $newTotal,
                        'expense_date' => $stockIn->created_at->toDateString(),
                    ]);
                    $stockIn->daily_expense_id = $expense->id;
                }
            }

            // 6. Update StockIn
            $stockIn->update([
                'quantity'       => $newQty,
                'buy_price'      => $newBuyPrice,
                'total_amount'   => $newTotal,
                'add_to_stock'   => $newAddToStock,
                'add_to_expense' => $newAddToExpense,
                'notes'          => $data['notes'] ?? null,
            ]);
        });

        return back()->with('success', 'Barang masuk berhasil diperbarui.');
    }

    public function destroy(StockIn $stockIn): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($stockIn->store->brand_id === $user->brand_id, 403);
        $store = $stockIn->store;

        $product = $stockIn->product;
        $qty = (float) $stockIn->quantity;
        $addToStock = $stockIn->add_to_stock;
        $dailyExpenseId = $stockIn->daily_expense_id;

        DB::transaction(function () use ($store, $stockIn, $product, $qty, $addToStock, $dailyExpenseId) {
            if ($addToStock) {
                $inv = StoreInventory::where('store_id', $store->id)
                    ->where('product_id', $product->id)
                    ->first();
                if ($inv) {
                    $inv->update(['current_stock' => max(0, (float) $inv->current_stock - $qty)]);
                }
                StockMutation::where('mutatable_type', StockIn::class)
                    ->where('mutatable_id', $stockIn->id)
                    ->delete();
            }
            if ($dailyExpenseId) {
                DailyExpense::find($dailyExpenseId)?->delete();
            }
            $stockIn->delete();
        });

        return back()->with('success', "Barang masuk \"{$product->name}\" berhasil dihapus.");
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

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $count = 1;
        while (Product::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = Str::slug($name) . '-' . $count++;
        }
        return $slug;
    }
}
