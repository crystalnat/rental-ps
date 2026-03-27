<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\StoreInventory;
use App\Models\DailyExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $stores = Store::where('brand_id', $user->brand_id)
            ->whereNull('deleted_at')
            ->get(['id', 'name']);
            
        $query = PurchaseOrder::with(['store', 'supplier', 'creator'])
            ->whereHas('store', function($q) use ($user) {
                $q->where('brand_id', $user->brand_id);
            });

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('store', function($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($user->role === 'cashier' && $user->store_id) {
            $query->where('store_id', $user->store_id);
        }

        $purchaseOrders = $query->latest()->paginate(10)->withQueryString();

        $allOrders = PurchaseOrder::whereHas('store', function($q) use ($user) {
                $q->where('brand_id', $user->brand_id);
            });
            
        if ($user->role === 'cashier' && $user->store_id) {
            $allOrders->where('store_id', $user->store_id);
        }
        
        $stats = [
            'total' => $allOrders->count(),
            'pending' => $allOrders->where('status', 'pending')->count(),
            'total_amount' => $allOrders->where('status', 'received')->sum('total_amount'),
        ];

        return Inertia::render('Admin/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'stores' => $stores,
            'stats' => $stats,
            'filters' => $request->only(['search'])
        ]);
    }

    public function create(): Response
    {
        $brandId = Auth::user()->brand_id;
        $stores = Store::where('brand_id', $brandId)->get(['id', 'name']);
        $suppliers = Supplier::where('brand_id', $brandId)->get(['id', 'name']);
        
        $products = Product::where('brand_id', $brandId)
            ->with(['category:id,name', 'inventories:product_id,current_stock'])
            ->get(['id', 'name', 'sku', 'unit', 'category_id'])
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit,
                'category' => $p->category?->name,
                'total_stock' => $p->inventories->sum('current_stock')
            ]);
        
        return Inertia::render('Admin/PurchaseOrders/Create', [
            'stores' => $stores,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.buy_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            $store = Store::findOrFail($data['store_id']);
            $totalAmount = 0;
            
            // Generate PO Number (Globally unique prefix)
            $prefix = 'PO-' . now()->format('Ymd') . '-';
            $lastPo = PurchaseOrder::where('po_number', 'like', $prefix . '%')
                ->latest('id')
                ->first();
            
            $seq = 1;
            if ($lastPo) {
                $seq = (int) substr($lastPo->po_number, -4) + 1;
            }
            $poNumber = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            $po = PurchaseOrder::create([
                'store_id' => $store->id,
                'supplier_id' => $data['supplier_id'],
                'created_by' => Auth::id(),
                'po_number' => $poNumber,
                'status' => 'pending',
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_amount' => 0 // calculated below
            ]);

            foreach ($data['items'] as $item) {
                $subtotal = $item['quantity'] * $item['buy_price'];
                $totalAmount += $subtotal;
                
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'buy_price' => $item['buy_price'],
                    'subtotal' => $subtotal,
                ]);
            }

            $po->update(['total_amount' => $totalAmount]);
        });

        return redirect()->route('admin.purchase-orders.index')->with('success', 'Purchase Order berhasil dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->store->brand_id !== Auth::user()->brand_id) {
            abort(403);
        }

        $purchaseOrder->load(['store', 'supplier', 'creator', 'items.product']);

        return Inertia::render('Admin/PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->store->brand_id !== Auth::user()->brand_id) {
            abort(403);
        }

        if ($purchaseOrder->status !== 'pending') {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'Hanya PO berstatus pending yang dapat diubah.');
        }

        $brandId = Auth::user()->brand_id;
        
        $stores = Store::where('brand_id', $brandId)
            ->whereNull('deleted_at')
            ->get(['id', 'name']);
            
        $suppliers = Supplier::where('brand_id', $brandId)
            ->where('is_active', true)
            ->get(['id', 'name']);
            
        $purchaseOrder->load(['items.product']);

        $products = Product::where('brand_id', $brandId)
            ->with(['category:id,name', 'inventories:product_id,current_stock'])
            ->get(['id', 'name', 'sku', 'unit', 'category_id'])
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit,
                'category' => $p->category?->name,
                'total_stock' => $p->inventories->sum('current_stock')
            ]);

        return Inertia::render('Admin/PurchaseOrders/Create', [
            'purchaseOrder' => $purchaseOrder,
            'stores' => $stores,
            'suppliers' => $suppliers,
            'products' => $products,
            'isEditing' => true,
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->store->brand_id !== Auth::user()->brand_id) {
            abort(403);
        }

        if ($purchaseOrder->status !== 'pending') {
            return back()->with('error', 'Hanya PO berstatus pending yang dapat diubah.');
        }

        $data = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.buy_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $purchaseOrder) {
            $purchaseOrder->update([
                'store_id' => $data['store_id'],
                'supplier_id' => $data['supplier_id'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Clear old items
            $purchaseOrder->items()->delete();

            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $subtotal = $item['quantity'] * $item['buy_price'];
                $totalAmount += $subtotal;
                
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'buy_price' => $item['buy_price'],
                    'subtotal' => $subtotal,
                ]);
            }

            $purchaseOrder->update(['total_amount' => $totalAmount]);
        });

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder->id)
            ->with('success', 'Purchase Order berhasil diperbarui.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->store->brand_id !== Auth::user()->brand_id) {
            abort(403);
        }

        if ($purchaseOrder->status !== 'pending') {
            return back()->with('error', 'Purchase order ini sudah tidak pending.');
        }

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->update([
                'status' => 'received',
                'received_at' => now(),
            ]);

            foreach ($purchaseOrder->items as $item) {
                if ($item->product->track_stock) {
                    $inventory = StoreInventory::firstOrCreate(
                        [
                            'store_id' => $purchaseOrder->store_id,
                            'product_id' => $item->product_id,
                        ],
                        ['current_stock' => 0]
                    );
                    
                    $inventory->increment('current_stock', $item->quantity);
                }
            }

            // Otorisasi & Catat ke Pengeluaran (DailyExpense)
            DailyExpense::create([
                'store_id'     => $purchaseOrder->store_id,
                'created_by'   => Auth::id(),
                'category'     => 'purchase',
                'description'  => "Pembelian Stok (PO: {$purchaseOrder->po_number}) dari Supplier: {$purchaseOrder->supplier->name}",
                'amount'       => $purchaseOrder->total_amount,
                'expense_date' => now()->toDateString(),
            ]);
        });

        return back()->with('success', 'Purchase Order berhasil diterima dan stok telah ditambahkan.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->store->brand_id !== Auth::user()->brand_id) {
            abort(403);
        }

        if ($purchaseOrder->status !== 'pending') {
            return back()->with('error', 'Purchase order ini sudah tidak pending.');
        }

        $purchaseOrder->update([
            'status' => 'cancelled',
        ]);

        return back()->with('success', 'Purchase Order berhasil dibatalkan.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->store->brand_id !== Auth::user()->brand_id) {
            abort(403);
        }

        if ($purchaseOrder->status !== 'pending') {
            return back()->with('error', 'Hanya Purchase order pending yang dapat dihapus.');
        }

        $purchaseOrder->delete();

        return back()->with('success', 'Purchase Order berhasil dihapus.');
    }
}
