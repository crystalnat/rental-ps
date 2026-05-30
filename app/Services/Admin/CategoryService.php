<?php

namespace App\Services\Admin;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * Get all categories for the current brand with product count.
     */
    public function getAllForBrand(int $brandId): Collection
    {
        return Category::where('brand_id', $brandId)
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new category.
     */
    public function create(int $brandId, array $data): Category
    {
        $slug = $this->generateUniqueSlug($brandId, $data['name']);

        return Category::create([
            'brand_id'   => $brandId,
            'name'       => $data['name'],
            'slug'       => $slug,
            'color'      => ! empty($data['color']) ? $data['color'] : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing category.
     */
    public function update(Category $category, array $data): bool
    {
        if ($data['name'] !== $category->name) {
            $data['slug'] = $this->generateUniqueSlug($category->brand_id, $data['name'], $category->id);
        }

        return $category->update([
            'name'       => $data['name'],
            'slug'       => $data['slug'] ?? $category->slug,
            'color'      => ! empty($data['color']) ? $data['color'] : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Delete a category and unlink its products.
     */
    public function delete(Category $category): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($category) {
            // Nullify category_id in products table to prevent dangling references
            // and fulfill the UI promise: "Produk akan menjadi tanpa kategori."
            $category->products()->update(['category_id' => null]);

            return (bool) $category->delete();
        });
    }

    /**
     * Generate a unique slug for a category within a brand, including soft-deleted records.
     */
    public function generateUniqueSlug(int $brandId, string $name, ?int $excludeId = null): string
    {
        $slug  = Str::slug($name);
        $count = 1;

        // Use withTrashed() to avoid duplicate entry errors with the unique DB index
        while (
            Category::withTrashed()
                ->where('brand_id', $brandId)
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = Str::slug($name) . '-' . $count++;
        }

        return $slug;
    }
}
