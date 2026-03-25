<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\PriceLog;
use App\Models\Store;
use App\Models\StoreInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StoreProductController extends Controller
{
    public function index(Store $store): Response
    {
        $this->authorizeStore($store);

        $products = Product::with(['category', 'inventories' => fn ($q) => $q->where('store_id', $store->id)])
            ->where('brand_id', $store->brand_id)
            ->withCount(['inventories as has_inventory' => fn ($q) => $q->where('store_id', $store->id)])
            ->orderBy('name')
            ->get()
            ->map(function ($product) use ($store) {
                $inventory = $product->inventories->first();
                $price = PriceLog::where('product_id', $product->id)
                    ->where(fn ($q) => $q->where('store_id', $store->id)->orWhereNull('store_id'))
                    ->whereNull('ended_at')
                    ->latest('started_at')
                    ->first();

                return [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'sku'            => $product->sku,
                    'category'       => $product->category?->name,
                    'category_id'    => $product->category_id,
                    'category_color' => $product->category?->color,
                    'description'   => $product->description,
                    'unit'          => $product->unit,
                    'is_available'  => $product->is_available,
                    'track_stock'   => $product->track_stock,
                    'has_inventory' => $product->has_inventory > 0,
                    'current_stock' => (float) ($inventory?->current_stock ?? 0),
                    'min_stock'     => (float) ($inventory?->min_stock ?? 0),
                    'sell_price'    => (float) ($price?->sell_price ?? 0),
                    'buy_price'     => (float) ($price?->buy_price ?? 0),
                    'image_url'     => $product->image ? \Storage::disk('public')->url($product->image) : null,
                ];
            });

        $otherStores = Store::where('brand_id', $store->brand_id)
            ->where('id', '!=', $store->id)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'is_active']);

        $categories = Category::where('brand_id', $store->brand_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return Inertia::render('Admin/StoreProducts/Index', [
            'store'        => $store->only('id', 'name', 'slug', 'city'),
            'products'     => $products,
            'other_stores' => $otherStores,
            'categories'   => $categories,
        ]);
    }

    public function copyProducts(Store $store, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);

        $data = $request->validate([
            'product_ids'       => ['required', 'array', 'min:1'],
            'product_ids.*'     => ['exists:products,id'],
            'target_store_ids'  => ['required', 'array', 'min:1'],
            'target_store_ids.*' => ['exists:stores,id'],
            'copy_prices'       => ['boolean'],
            'copy_stock'        => ['boolean'],
        ]);

        $targetStores = Store::whereIn('id', $data['target_store_ids'])
            ->where('brand_id', $store->brand_id)
            ->get();

        $products = Product::whereIn('id', $data['product_ids'])
            ->where('brand_id', $store->brand_id)
            ->get();

        $copied = 0;

        DB::transaction(function () use ($store, $targetStores, $products, $data, &$copied) {
            foreach ($products as $product) {
                $sourceInventory = StoreInventory::where('store_id', $store->id)
                    ->where('product_id', $product->id)
                    ->first();

                $sourcePrice = PriceLog::where('product_id', $product->id)
                    ->where('store_id', $store->id)
                    ->whereNull('ended_at')
                    ->latest('started_at')
                    ->first();

                foreach ($targetStores as $targetStore) {
                    if ($data['copy_stock'] ?? true) {
                        StoreInventory::updateOrCreate(
                            [
                                'store_id'   => $targetStore->id,
                                'product_id' => $product->id,
                            ],
                            [
                                'current_stock' => $sourceInventory?->current_stock ?? 0,
                                'min_stock'     => $sourceInventory?->min_stock ?? 0,
                            ]
                        );
                    }

                    if (($data['copy_prices'] ?? true) && $sourcePrice) {
                        $existingPrice = PriceLog::where('product_id', $product->id)
                            ->where('store_id', $targetStore->id)
                            ->whereNull('ended_at')
                            ->first();

                        if ($existingPrice) {
                            $existingPrice->update(['ended_at' => now()]);
                        }

                        PriceLog::create([
                            'product_id' => $product->id,
                            'store_id'   => $targetStore->id,
                            'buy_price'  => $sourcePrice->buy_price,
                            'sell_price' => $sourcePrice->sell_price,
                            'started_at' => now(),
                            'created_by' => Auth::id(),
                        ]);
                    }

                    $copied++;
                }
            }
        });

        $storeNames = $targetStores->pluck('name')->join(', ', ' dan ');

        return back()->with('success', "{$copied} produk berhasil disalin ke {$storeNames}.");
    }

    public function updateStock(Store $store, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);

        $data = $request->validate([
            'product_id'    => ['required', 'exists:products,id'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'min_stock'     => ['required', 'numeric', 'min:0'],
        ]);

        $product = Product::where('id', $data['product_id'])
            ->where('brand_id', $store->brand_id)
            ->firstOrFail();

        StoreInventory::updateOrCreate(
            [
                'store_id'   => $store->id,
                'product_id' => $product->id,
            ],
            [
                'current_stock' => $data['current_stock'],
                'min_stock'     => $data['min_stock'],
            ]
        );

        return back()->with('success', "Stok {$product->name} berhasil diperbarui.");
    }

    public function updatePrice(Store $store, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'buy_price'  => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
        ]);

        $product = Product::where('id', $data['product_id'])
            ->where('brand_id', $store->brand_id)
            ->firstOrFail();

        $existingPrice = PriceLog::where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->whereNull('ended_at')
            ->first();

        if ($existingPrice) {
            $existingPrice->update(['ended_at' => now()]);
        }

        PriceLog::create([
            'product_id' => $product->id,
            'store_id'   => $store->id,
            'buy_price'  => $data['buy_price'],
            'sell_price' => $data['sell_price'],
            'started_at' => now(),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', "Harga khusus {$product->name} untuk toko ini berhasil diatur.");
    }

    public function resetPrice(Store $store, Product $product): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($product->brand_id === $store->brand_id, 403);

        PriceLog::where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        return back()->with('success', "Harga {$product->name} dikembalikan ke harga default.");
    }

    public function addProduct(Store $store, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);

        $data = $request->validate([
            'category_id'   => ['nullable', 'exists:categories,id'],
            'name'          => ['required', 'string', 'max:255'],
            'sku'           => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'description'   => ['nullable', 'string'],
            'unit'          => ['required', 'string', 'max:50'],
            'buy_price'     => ['required', 'numeric', 'min:0'],
            'sell_price'    => ['required', 'numeric', 'min:0'],
            'track_stock'   => ['boolean'],
            'is_available'  => ['boolean'],
            'current_stock' => ['required_if:track_stock,true', 'numeric', 'min:0'],
            'min_stock'     => ['required_if:track_stock,true', 'numeric', 'min:0'],
            'image'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        DB::transaction(function () use ($store, $data, $request) {
            $slug = $this->generateUniqueSlug($data['name']);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('products', 'public');
            }

            $product = Product::create([
                'brand_id'      => $store->brand_id,
                'category_id'   => $data['category_id'] ?? null,
                'name'          => $data['name'],
                'slug'          => $slug,
                'sku'           => $data['sku'] ?? null,
                'description'   => $data['description'] ?? null,
                'unit'          => $data['unit'],
                'track_stock'   => $data['track_stock'] ?? true,
                'is_available'  => $data['is_available'] ?? true,
                'image'         => $imagePath,
            ]);

            PriceLog::create([
                'product_id' => $product->id,
                'store_id'   => $store->id,
                'buy_price'  => $data['buy_price'],
                'sell_price' => $data['sell_price'],
                'started_at' => now(),
                'created_by' => Auth::id(),
            ]);

            if ($data['track_stock'] ?? true) {
                StoreInventory::create([
                    'store_id'      => $store->id,
                    'product_id'    => $product->id,
                    'current_stock' => $data['current_stock'] ?? 0,
                    'min_stock'     => $data['min_stock'] ?? 0,
                ]);
            }
        });

        return back()->with('success', "Produk {$data['name']} berhasil ditambahkan ke toko ini.");
    }

    public function updateProduct(Store $store, Product $product, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($product->brand_id === $store->brand_id, 403);

        $data = $request->validate([
            'category_id'   => ['nullable', 'exists:categories,id'],
            'name'          => ['required', 'string', 'max:255'],
            'sku'           => ['nullable', 'string', 'max:100', "unique:products,sku,{$product->id}"],
            'description'   => ['nullable', 'string'],
            'unit'          => ['required', 'string', 'max:50'],
            'track_stock'   => ['boolean'],
            'is_available'  => ['boolean'],
            'image'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'buy_price'     => ['required', 'numeric', 'min:0'],
            'sell_price'    => ['required', 'numeric', 'min:0'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock'     => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($product->name !== $data['name']) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $product->id);
        }

        if ($request->hasFile('image')) {
            if ($product->image) {
                \Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        DB::transaction(function () use ($store, $product, $data) {
            $product->update([
                'category_id'  => $data['category_id'],
                'name'         => $data['name'],
                'slug'         => $data['slug'] ?? $product->slug,
                'sku'          => $data['sku'] ?? null,
                'description'  => $data['description'] ?? null,
                'unit'         => $data['unit'],
                'track_stock'  => $data['track_stock'] ?? true,
                'is_available' => $data['is_available'] ?? true,
                'image'        => $data['image'] ?? $product->image,
            ]);

            // Update harga toko
            PriceLog::where('product_id', $product->id)
                ->where('store_id', $store->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            PriceLog::create([
                'product_id' => $product->id,
                'store_id'   => $store->id,
                'buy_price'  => $data['buy_price'],
                'sell_price' => $data['sell_price'],
                'started_at' => now(),
                'created_by' => Auth::id(),
            ]);

            // Update stok jika tracking aktif
            if ($data['track_stock'] ?? true) {
                StoreInventory::updateOrCreate(
                    ['store_id' => $store->id, 'product_id' => $product->id],
                    [
                        'current_stock' => $data['current_stock'] ?? 0,
                        'min_stock'     => $data['min_stock'] ?? 0,
                    ]
                );
            }
        });

        return back()->with('success', "Produk {$product->name} berhasil diperbarui.");
    }

    public function destroyProduct(Store $store, Product $product): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($product->brand_id === $store->brand_id, 403);

        $hasOrders = \App\Models\OrderItem::where('product_id', $product->id)->exists();
        if ($hasOrders) {
            return back()->with('error', "Produk {$product->name} tidak dapat dihapus karena sudah ada dalam transaksi.");
        }

        $product->delete();

        return back()->with('success', "Produk {$product->name} berhasil dihapus.");
    }

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = \Str::slug($name);
        $count = 1;

        while (Product::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = \Str::slug($name) . '-' . $count++;
        }

        return $slug;
    }

    private function authorizeStore(Store $store): void
    {
        abort_unless($store->brand_id === Auth::user()->brand_id, 403);
    }
}
