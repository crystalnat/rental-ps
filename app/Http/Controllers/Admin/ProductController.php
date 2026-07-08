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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $brandId = Auth::user()->brand_id;

        $categories = Category::where('brand_id', $brandId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $allProducts = Product::where('brand_id', $brandId)
            ->where('is_active', true)
            ->where('is_rental_package', false) // only non-rental products can be included
            ->orderBy('name')
            ->get(['id', 'name', 'unit'])
            ->toArray();

        return Inertia::render('Admin/Products/Form', [
            'product'     => null,
            'categories'  => $categories,
            'allProducts' => $allProducts,
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
            'modifiers'    => ['nullable', 'array'],
            'modifiers.*.name' => ['required', 'string', 'max:100'],
            'modifiers.*.is_required' => ['boolean'],
            'modifiers.*.min_select' => ['required', 'integer', 'min:0'],
            'modifiers.*.max_select' => ['required', 'integer', 'min:1'],
            'modifiers.*.options' => ['required', 'array', 'min:1'],
            'modifiers.*.options.*.name' => ['required', 'string', 'max:100'],
            'modifiers.*.options.*.price_extra' => ['required', 'numeric', 'min:0'],
            'is_rental_package' => ['boolean'],
            'rental_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'included_items_json' => ['nullable', 'array'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = DB::transaction(function () use ($data, $brandId, $imagePath) {
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
                'is_rental_package' => $data['is_rental_package'] ?? false,
                'rental_duration_minutes' => $data['rental_duration_minutes'] ?? null,
                'included_items_json' => $data['included_items_json'] ?? [],
                'image' => $imagePath,
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

            if (!empty($data['modifiers'])) {
                foreach ($data['modifiers'] as $order => $groupData) {
                    $group = $product->modifierGroups()->create([
                        'name' => $groupData['name'],
                        'is_required' => $groupData['is_required'] ?? false,
                        'min_select' => $groupData['min_select'],
                        'max_select' => $groupData['max_select'],
                        'sort_order' => $order,
                    ]);

                    foreach ($groupData['options'] as $optOrder => $optData) {
                        $group->options()->create([
                            'name' => $optData['name'],
                            'price_extra' => $optData['price_extra'],
                            'sort_order' => $optOrder,
                            'is_active' => $optData['is_active'] ?? true,
                            'is_available' => $optData['is_available'] ?? true,
                        ]);
                    }
                }
            }

            return $product;
        });

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

        $allProducts = Product::where('brand_id', Auth::user()->brand_id)
            ->where('is_active', true)
            ->where('is_rental_package', false)
            ->where('id', '!=', $product->id) // exclude self
            ->orderBy('name')
            ->get(['id', 'name', 'unit'])
            ->toArray();

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
                'is_rental_package' => (bool) $product->is_rental_package,
                'rental_duration_minutes' => $product->rental_duration_minutes,
                'included_items_json' => $product->included_items_json ?? [],
                'modifiers'    => $product->modifierGroups->map(function($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'is_required' => $group->is_required,
                        'min_select' => $group->min_select,
                        'max_select' => $group->max_select,
                        'options' => $group->options->map(function($opt) {
                            return [
                                'id' => $opt->id,
                                'name' => $opt->name,
                                'price_extra' => (float) $opt->price_extra,
                            ];
                        })
                    ];
                }),
            ],
            'categories'  => $categories,
            'allProducts' => $allProducts,
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
            'modifiers'    => ['nullable', 'array'],
            'modifiers.*.id'   => ['nullable'],
            'modifiers.*.name' => ['required', 'string', 'max:100'],
            'modifiers.*.is_required' => ['boolean'],
            'modifiers.*.min_select' => ['required', 'integer', 'min:0'],
            'modifiers.*.max_select' => ['required', 'integer', 'min:1'],
            'modifiers.*.options' => ['required', 'array', 'min:1'],
            'modifiers.*.options.*.id'   => ['nullable'],
            'modifiers.*.options.*.name' => ['required', 'string', 'max:100'],
            'modifiers.*.options.*.price_extra' => ['required', 'numeric', 'min:0'],
            'is_rental_package' => ['boolean'],
            'rental_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'included_items_json' => ['nullable', 'array'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('products', 'public');
        }

        DB::transaction(function () use ($data, $product, $imagePath) {
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
                'is_rental_package' => $data['is_rental_package'] ?? false,
                'rental_duration_minutes' => $data['rental_duration_minutes'] ?? null,
                'included_items_json' => $data['included_items_json'] ?? [],
                'image' => $imagePath,
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

            // MODIFIERS UPDATE LOGIC
            // Simple approach: Delete all and recreate for now, or sync by ID
            // Let's do a basic sync by ID to preserve histories if possible, 
            // but for MVP, delete/recreate is easier. Let's do a refined update.
            
            $existingGroupIds = $product->modifierGroups()->pluck('id')->toArray();
            $newGroupIds = [];

            if (!empty($data['modifiers'])) {
                foreach ($data['modifiers'] as $order => $groupData) {
                    $group = $product->modifierGroups()->updateOrCreate(
                        ['id' => $groupData['id'] ?? null],
                        [
                            'name' => $groupData['name'],
                            'is_required' => $groupData['is_required'] ?? false,
                            'min_select' => $groupData['min_select'],
                            'max_select' => $groupData['max_select'],
                            'sort_order' => $order,
                        ]
                    );
                    $newGroupIds[] = $group->id;

                    $existingOptionIds = $group->options()->pluck('id')->toArray();
                    $newOptionIds = [];

                    foreach ($groupData['options'] as $optOrder => $optData) {
                        $option = $group->options()->updateOrCreate(
                            ['id' => $optData['id'] ?? null],
                            [
                                'name' => $optData['name'],
                                'price_extra' => $optData['price_extra'],
                                'sort_order' => $optOrder,
                                'is_active' => $optData['is_active'] ?? true,
                                'is_available' => $optData['is_available'] ?? true,
                            ]
                        );
                        $newOptionIds[] = $option->id;
                    }

                    // Delete removed options
                    $group->options()->whereNotIn('id', $newOptionIds)->delete();
                }
            }

            // Delete removed groups
            $product->modifierGroups()->whereNotIn('id', $newGroupIds)->delete();
        });

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

        // withTrashed: unique index (brand_id, slug) tetap menghitung baris soft-deleted, jadi slug harus unik termasuk yang sudah dihapus.
        while (
            Product::withTrashed()->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = Str::slug($name) . '-' . $count++;
        }

        return $slug;
    }
}
