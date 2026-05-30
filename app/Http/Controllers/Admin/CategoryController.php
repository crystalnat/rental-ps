<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Admin\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(): Response
    {
        $brandId = Auth::user()->brand_id;

        $categories = $this->categoryService->getAllForBrand($brandId)
            ->map(fn ($cat) => [
                'id'             => $cat->id,
                'name'           => $cat->name,
                'slug'           => $cat->slug,
                'icon'           => $cat->icon,
                'color'          => $cat->color,
                'sort_order'     => $cat->sort_order,
                'is_active'      => $cat->is_active,
                'products_count' => $cat->products_count,
            ]);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $brandId = Auth::user()->brand_id;

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'color'      => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
        ]);

        $this->categoryService->create($brandId, $data);

        return redirect()->route('admin.categories.index')
            ->with('success', "Kategori \"{$data['name']}\" berhasil ditambahkan.");
    }

    public function edit(Category $category): Response
    {
        $this->authorizeCategory($category);

        return Inertia::render('Admin/Categories/Form', [
            'category' => [
                'id'         => $category->id,
                'name'       => $category->name,
                'slug'       => $category->slug,
                'icon'       => $category->icon,
                'color'      => $category->color,
                'sort_order' => $category->sort_order,
                'is_active'  => $category->is_active,
            ],
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'color'      => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
        ]);

        $this->categoryService->update($category, $data);

        return redirect()->route('admin.categories.index')
            ->with('success', "Kategori \"{$category->name}\" berhasil diperbarui.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);

        $this->categoryService->delete($category);

        return redirect()->route('admin.categories.index')
            ->with('success', "Kategori \"{$category->name}\" berhasil dihapus.");
    }

    private function authorizeCategory(Category $category): void
    {
        abort_unless($category->brand_id === Auth::user()->brand_id, 403);
    }
}

