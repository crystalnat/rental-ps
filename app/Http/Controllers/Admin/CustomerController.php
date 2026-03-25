<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $brandId = $user->brand_id;

        $query = Customer::where('brand_id', $brandId)
            ->orderBy('name');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sql) use ($q) {
                $sql->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%')
                    ->orWhere('phone', 'like', '%' . $q . '%');
            });
        }

        $customers = $query->get()->map(fn ($c) => [
            'id'           => $c->id,
            'name'         => $c->name,
            'email'        => $c->email,
            'phone'        => $c->phone,
            'total_orders' => $c->total_orders,
            'total_spent'  => (float) $c->total_spent,
        ]);

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters'    => [
                'search' => $request->search ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $brandId = $user->brand_id;

        $request->merge([
            'email' => $request->filled('email') ? trim((string) $request->email) : null,
            'phone' => $request->filled('phone') ? trim((string) $request->phone) : null,
        ]);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->where(fn ($q) => $q->where('brand_id', $brandId)),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        Customer::create([
            'brand_id'     => $brandId,
            'user_id'      => null,
            'name'         => $validated['name'],
            'email'        => $validated['email'] !== null && $validated['email'] !== '' ? $validated['email'] : null,
            'phone'        => $validated['phone'] !== null && $validated['phone'] !== '' ? $validated['phone'] : null,
            'total_orders' => 0,
            'total_spent'  => 0,
        ]);

        return back()->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeBrand($customer);

        $user = Auth::user();
        $brandId = $user->brand_id;

        $request->merge([
            'email' => $request->filled('email') ? trim((string) $request->email) : null,
            'phone' => $request->filled('phone') ? trim((string) $request->phone) : null,
        ]);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where(fn ($q) => $q->where('brand_id', $brandId))
                    ->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $customer->update([
            'name'  => $validated['name'],
            'email' => $validated['email'] !== null && $validated['email'] !== '' ? $validated['email'] : null,
            'phone' => $validated['phone'] !== null && $validated['phone'] !== '' ? $validated['phone'] : null,
        ]);

        return back()->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorizeBrand($customer);
        $customer->delete();

        return back()->with('success', 'Pelanggan berhasil dihapus.');
    }

    public function history(Customer $customer): JsonResponse
    {
        $this->authorizeBrand($customer);

        return response()->json($this->customerWithOrdersPayload($customer));
    }

    public function show(Customer $customer): Response
    {
        $this->authorizeBrand($customer);

        $payload = $this->customerWithOrdersPayload($customer);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $payload['customer'],
            'orders'   => $payload['orders'],
        ]);
    }

    /** @return array{customer: array<string, mixed>, orders: array<int, array<string, mixed>>} */
    private function customerWithOrdersPayload(Customer $customer): array
    {
        $customer->load(['orders' => fn ($q) => $q->with(['store', 'cashier', 'table'])
            ->orderByDesc('created_at')
            ->limit(100)]);

        $orders = $customer->orders->map(fn ($o) => [
            'id'             => $o->id,
            'order_code'     => $o->order_code,
            'type'           => $o->type,
            'status'         => $o->status,
            'payment_status' => $o->payment_status,
            'final_amount'   => (float) $o->final_amount,
            'store_name'     => $o->store?->name,
            'cashier_name'   => $o->cashier?->name,
            'table_name'     => $o->table?->name,
            'created_at'     => $o->created_at->format('d M Y H:i'),
        ])->values()->all();

        return [
            'customer' => [
                'id'           => $customer->id,
                'name'         => $customer->name,
                'email'        => $customer->email,
                'phone'        => $customer->phone,
                'total_orders' => $customer->total_orders,
                'total_spent'  => (float) $customer->total_spent,
            ],
            'orders' => $orders,
        ];
    }

    private function authorizeBrand(Customer $customer): void
    {
        $uid = Auth::user()->brand_id;
        if ($customer->brand_id === null || $uid === null || (int) $customer->brand_id !== (int) $uid) {
            abort(403);
        }
    }
}
