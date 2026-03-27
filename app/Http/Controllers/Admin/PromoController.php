<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class PromoController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $brandId = $user->brand_id;

        $promos = Promo::where('brand_id', $brandId)
            ->when($request->search, function ($query, $search) {
                $query->where('code', 'LIKE', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Promos/Index', [
            'promos' => $promos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Promos/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('promos', 'code')],
            'type' => ['required', 'in:percentage,nominal'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean']
        ]);

        $data['brand_id'] = $user->brand_id;
        // ensure default zeroes
        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        Promo::create($data);

        return redirect()->route('admin.promos.index')->with('success', 'Promo berhasil dibuat.');
    }

    public function edit(Promo $promo): Response
    {
        $user = auth()->user();
        if ($promo->brand_id !== $user->brand_id) {
            abort(403);
        }

        return Inertia::render('Admin/Promos/Edit', [
            'promo' => $promo
        ]);
    }

    public function update(Request $request, Promo $promo): RedirectResponse
    {
        $user = auth()->user();
        if ($promo->brand_id !== $user->brand_id) {
            abort(403);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('promos', 'code')->ignore($promo->id)],
            'type' => ['required', 'in:percentage,nominal'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean']
        ]);

        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        $promo->update($data);

        return redirect()->route('admin.promos.index')->with('success', 'Promo berhasil diperbarui.');
    }

    public function destroy(Promo $promo): RedirectResponse
    {
        $user = auth()->user();
        if ($promo->brand_id !== $user->brand_id) {
            abort(403);
        }

        $promo->delete();

        return redirect()->route('admin.promos.index')->with('success', 'Promo berhasil dihapus.');
    }
}
