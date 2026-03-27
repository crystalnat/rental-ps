<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Supplier::where('brand_id', $user->brand_id);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->latest()->paginate(10)->withQueryString();

        $stats = [
            'total' => Supplier::where('brand_id', $user->brand_id)->count(),
            'active' => Supplier::where('brand_id', $user->brand_id)->where('is_active', true)->count(),
        ];

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $suppliers,
            'stats' => $stats,
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean']
        ]);

        Supplier::create(array_merge($data, [
            'brand_id' => Auth::user()->brand_id,
        ]));

        return redirect()->back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        if ($supplier->brand_id !== Auth::user()->brand_id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean']
        ]);

        $supplier->update($data);

        return redirect()->back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->brand_id !== Auth::user()->brand_id) {
            abort(403);
        }

        $supplier->delete();

        return redirect()->back()->with('success', 'Supplier berhasil dihapus.');
    }
}
