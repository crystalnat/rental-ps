<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $brandId = $user->brand_id;

        $query = User::with('store')
            ->where('brand_id', $brandId)
            ->where('role', '!=', 'owner');

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }
        if ($request->filled('store') && $request->store !== 'all') {
            $query->where('store_id', $request->store);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sql) use ($q) {
                $sql->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%')
                    ->orWhere('phone', 'like', '%' . $q . '%');
            });
        }

        $users = $query->orderBy('name')->get()->map(fn ($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'phone'      => $u->phone,
            'role'       => $u->role,
            'store_id'   => $u->store_id,
            'store_name' => $u->store?->name,
            'is_active'  => $u->is_active,
        ]);

        $stores = Store::where('brand_id', $brandId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        return Inertia::render('Admin/Users/Index', [
            'users'         => $users,
            'stores'        => $stores,
            'currentUserId' => $user->id,
            'filters'       => [
                'search' => $request->search ?? '',
                'role'   => $request->role ?? 'all',
                'store'  => $request->store ?? 'all',
                'status' => $request->status ?? 'all',
            ],
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $brandId = $user->brand_id;

        $allowedRoles = $user->role === 'owner' ? ['admin', 'cashier', 'staff'] : ['cashier', 'staff'];

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['required', 'string', 'min:6', 'confirmed'],
            'role'      => ['required', Rule::in($allowedRoles)],
            'store_id'  => ['nullable', Rule::exists('stores', 'id')->where('brand_id', $brandId)],
            'is_active' => ['boolean'],
        ]);

        User::create([
            'brand_id'  => $brandId,
            'store_id'  => $data['store_id'] ?? null,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make($data['password']),
            'role'      => $data['role'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "Karyawan \"{$data['name']}\" berhasil ditambahkan.");
    }

    public function edit(User $user): Response
    {
        $this->authorizeUser($user);

        return $this->form($user);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUser($user);
        $authUser = Auth::user();
        $brandId = $authUser->brand_id;

        $allowedRoles = $authUser->role === 'owner' ? ['admin', 'cashier', 'staff'] : ['cashier', 'staff'];

        $rules = [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'     => ['nullable', 'string', 'max:20'],
            'role'      => ['required', Rule::in($allowedRoles)],
            'store_id'  => ['nullable', Rule::exists('stores', 'id')->where('brand_id', $brandId)],
            'is_active' => ['boolean'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', 'string', 'min:6', 'confirmed'];
        }

        $data = $request->validate($rules);

        $user->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'role'      => $data['role'],
            'store_id'  => $data['store_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Karyawan \"{$user->name}\" berhasil diperbarui.");
    }

    public function show(User $user): Response
    {
        $this->authorizeUser($user);

        $activities = collect();

        // Transaksi kasir (Order)
        foreach ($user->processedOrders()->with('store')->latest('created_at')->limit(50)->get() as $o) {
            $activities->push([
                'type'        => 'order',
                'type_label'  => 'Transaksi Kasir',
                'date'        => $o->created_at->format('d M Y H:i'),
                'date_sort'   => $o->created_at->format('Y-m-d H:i:s'),
                'description' => $o->order_code,
                'detail'      => $o->store?->name . ' · ' . \number_format($o->final_amount, 0, ',', '.'),
                'link'        => route('admin.orders.show', $o->id),
            ]);
        }

        // Pengeluaran harian
        foreach ($user->dailyExpenses()->with('store')->latest('created_at')->limit(50)->get() as $e) {
            $activities->push([
                'type'        => 'expense',
                'type_label'  => 'Input Pengeluaran',
                'date'        => $e->created_at->format('d M Y H:i'),
                'date_sort'   => $e->created_at->format('Y-m-d H:i:s'),
                'description' => $e->description,
                'detail'      => $e->store?->name . ' · Rp ' . \number_format($e->amount, 0, ',', '.'),
                'link'        => null,
            ]);
        }

        // Mutasi stok (input stok, koreksi, dll)
        foreach ($user->stockMutations()->with(['store', 'product'])->latest('created_at')->limit(50)->get() as $m) {
            $typeLabels = ['in' => 'Input Stok', 'out' => 'Stok Keluar', 'adjustment' => 'Koreksi Stok', 'waste' => 'Stok Rusak'];
            $activities->push([
                'type'        => 'stock',
                'type_label'  => $typeLabels[$m->type] ?? $m->type,
                'date'        => $m->created_at->format('d M Y H:i'),
                'date_sort'   => $m->created_at->format('Y-m-d H:i:s'),
                'description' => $m->product?->name . ' ' . ($m->type === 'in' ? '+' : '-') . $m->quantity . ' ' . ($m->product?->unit ?? ''),
                'detail'      => $m->store?->name,
                'link'        => null,
            ]);
        }

        // Perubahan harga (PriceLog)
        foreach ($user->priceLogs()->with(['store', 'product'])->latest('started_at')->limit(50)->get() as $p) {
            $activities->push([
                'type'        => 'price',
                'type_label'  => 'Ubah Harga',
                'date'        => $p->started_at->format('d M Y H:i'),
                'date_sort'   => $p->started_at->format('Y-m-d H:i:s'),
                'description' => $p->product?->name,
                'detail'      => ($p->store?->name ?? '') . ' · Rp ' . \number_format($p->sell_price ?? 0, 0, ',', '.'),
                'link'        => null,
            ]);
        }

        $activities = $activities->sortByDesc('date_sort')->take(100)->values()->toArray();

        $userData = [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'store_id'   => $user->store_id,
            'store_name' => $user->store?->name,
            'is_active'  => $user->is_active,
        ];

        return Inertia::render('Admin/Users/Show', [
            'user'       => $userData,
            'activities' => $activities,
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeUser($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "Karyawan \"{$user->name}\" berhasil dihapus.");
    }

    private function form(?User $user): Response
    {
        $authUser = Auth::user();
        $brandId = $authUser->brand_id;

        $stores = Store::where('brand_id', $brandId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $roleOptions = $authUser->role === 'owner'
            ? [
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'cashier', 'label' => 'Kasir'],
                ['value' => 'staff', 'label' => 'Staff'],
            ]
            : [
                ['value' => 'cashier', 'label' => 'Kasir'],
                ['value' => 'staff', 'label' => 'Staff'],
            ];

        $userData = $user ? [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'role'      => $user->role,
            'store_id'  => $user->store_id,
            'is_active' => $user->is_active,
        ] : null;

        return Inertia::render('Admin/Users/Form', [
            'user'        => $userData,
            'stores'      => $stores,
            'role_options' => $roleOptions,
        ]);
    }

    private function authorizeUser(User $user): void
    {
        if ($user->role === 'owner') {
            abort(403, 'Tidak dapat mengubah akun owner.');
        }
        if ($user->brand_id !== Auth::user()->brand_id) {
            abort(403);
        }
    }
}
