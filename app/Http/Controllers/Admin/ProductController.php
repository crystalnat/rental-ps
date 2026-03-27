<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\PriceLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        $brandId = Auth::user()->brand_id;

        $products = Product::with('category')
            ->where('brand_id', $brandId)
            ->withCount('inventories')
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                $globalPrice = PriceLog::where('product_id', $product->id)
                    ->whereNull('store_id')
                    ->whereNull('ended_at')
                    ->latest('started_at')
                    ->first();

                return [
                    'id'                => $product->id,
                    'name'              => $product->name,
                    'slug'              => $product->slug,
                    'sku'               => $product->sku,
                    'category'          => $product->category?->name,
                    'category_id'       => $product->category_id,
                    'unit'              => $product->unit,
                    'is_available'      => $product->is_available,
                    'track_stock'       => $product->track_stock,
                    'discount_percent'  => (float) $product->discount_percent,
                    'is_active'         => $product->is_active,
                    'inventories_count' => $product->inventories_count,
                    'sell_price'        => $globalPrice?->sell_price ?? 0,
                    'buy_price'         => $globalPrice?->buy_price ?? 0,
                    'image'             => $product->image,
                ];
            });

        $categories = Category::where('brand_id', $brandId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'color']);

        return Inertia::render('Admin/Products/Index', [
            'products'   => $products,
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        $categories = Category::where('brand_id', Auth::user()->brand_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Products/Form', [
            'product'    => null,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $brandId = Auth::user()->brand_id;

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:200'],
            'category_id'  => ['nullable', 'exists:categories,id'],
            'sku'          => ['nullable', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'unit'         => ['required', 'string', 'max:30'],
            'track_stock'  => ['boolean'],
            'is_available' => ['boolean'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'buy_price'    => ['required', 'numeric', 'min:0'],
            'sell_price'   => ['required', 'numeric', 'min:0'],
        ]);

        $slug = $this->generateUniqueSlug($data['name']);

        $product = Product::create([
            'brand_id'     => $brandId,
            'category_id'  => $data['category_id'] ?? null,
            'name'         => $data['name'],
            'slug'         => $slug,
            'sku'          => $data['sku'] ?? null,
            'description'  => $data['description'] ?? null,
            'unit'         => $data['unit'],
            'track_stock'  => $data['track_stock'] ?? true,
            'is_available' => $data['is_available'] ?? true,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'is_active'    => true,
        ]);

        PriceLog::create([
            'product_id' => $product->id,
            'store_id'   => null,
            'buy_price'  => $data['buy_price'],
            'sell_price' => $data['sell_price'],
            'started_at' => now(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.products.index')
            ->with('success', "Produk \"{$product->name}\" berhasil ditambahkan.");
    }

    public function edit(Product $product): Response
    {
        $this->authorizeProduct($product);

        $categories = Category::where('brand_id', Auth::user()->brand_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $globalPrice = PriceLog::where('product_id', $product->id)
            ->whereNull('store_id')
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        return Inertia::render('Admin/Products/Form', [
            'product' => [
                'id'           => $product->id,
                'name'         => $product->name,
                'slug'         => $product->slug,
                'category_id'  => $product->category_id,
                'sku'          => $product->sku,
                'description'  => $product->description,
                'unit'         => $product->unit,
                'track_stock'  => $product->track_stock,
                'is_available' => $product->is_available,
                'discount_percent' => (float) $product->discount_percent,
                'is_active'    => $product->is_active,
                'buy_price'    => $globalPrice?->buy_price ?? 0,
                'sell_price'   => $globalPrice?->sell_price ?? 0,
            ],
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($product);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:200'],
            'category_id'  => ['nullable', 'exists:categories,id'],
            'sku'          => ['nullable', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'unit'         => ['required', 'string', 'max:30'],
            'track_stock'  => ['boolean'],
            'is_available' => ['boolean'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'buy_price'    => ['required', 'numeric', 'min:0'],
            'sell_price'   => ['required', 'numeric', 'min:0'],
        ]);

        if ($data['name'] !== $product->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $product->id);
        }

        $product->update([
            'category_id'  => $data['category_id'] ?? null,
            'name'         => $data['name'],
            'slug'         => $data['slug'] ?? $product->slug,
            'sku'          => $data['sku'] ?? null,
            'description'  => $data['description'] ?? null,
            'unit'         => $data['unit'],
            'track_stock'  => $data['track_stock'] ?? true,
            'is_available' => $data['is_available'] ?? true,
            'discount_percent' => $data['discount_percent'] ?? 0,
        ]);

        $currentPrice = PriceLog::where('product_id', $product->id)
            ->whereNull('store_id')
            ->whereNull('ended_at')
            ->first();

        if (
            ! $currentPrice ||
            $currentPrice->buy_price != $data['buy_price'] ||
            $currentPrice->sell_price != $data['sell_price']
        ) {
            if ($currentPrice) {
                $currentPrice->update(['ended_at' => now()]);
            }

            PriceLog::create([
                'product_id' => $product->id,
                'store_id'   => null,
                'buy_price'  => $data['buy_price'],
                'sell_price' => $data['sell_price'],
                'started_at' => now(),
                'created_by' => Auth::id(),
            ]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', "Produk \"{$product->name}\" berhasil diperbarui.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorizeProduct($product);

        $hasOrders = $product->orderItems()->exists();

        if ($hasOrders) {
            return back()->with('error', 'Tidak bisa menghapus produk yang sudah pernah dipesan.');
        }

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', "Produk \"{$product->name}\" berhasil dihapus.");
    }

    private function authorizeProduct(Product $product): void
    {
        abort_unless($product->brand_id === Auth::user()->brand_id, 403);
    }

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug  = Str::slug($name);
        $count = 1;

        while (
            Product::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = Str::slug($name) . '-' . $count++;
        }

        return $slug;
    }
}
