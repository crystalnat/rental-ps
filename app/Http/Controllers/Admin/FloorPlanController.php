<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Floor;
use App\Models\FloorElement;
use App\Models\Store;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FloorPlanController extends Controller
{
    private function authorizeStore(Store $store): void
    {
        $user = auth()->user();
        if ($user->role !== 'owner' && $user->store_id !== $store->id) {
            abort(403);
        }
    }

    public function index(Store $store): Response
    {
        $this->authorizeStore($store);

        $floors = Floor::where('store_id', $store->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($f) => [
                'id'            => $f->id,
                'name'          => $f->name,
                'width_meters'  => (float) $f->width_meters,
                'length_meters' => (float) $f->length_meters,
                'sort_order'    => $f->sort_order,
                'is_active'     => $f->is_active,
                'tables_count'  => $f->diningTables()->count(),
                'elements_count'=> $f->elements()->count(),
            ]);

        return Inertia::render('Admin/FloorPlan/Index', [
            'store'  => $store->only('id', 'name', 'slug'),
            'floors' => $floors,
        ]);
    }

    public function edit(Store $store, Floor $floor): Response
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id, 403);

        $tables = $floor->diningTables()
            ->with('store')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id'            => $t->id,
                'name'          => $t->name,
                'qr_code'       => $t->qr_code,
                'qr_url'        => $t->qrUrl(),
                'capacity'      => $t->capacity,
                'status'        => $t->status,
                'x_meters'      => (float) ($t->x_meters ?? 0),
                'y_meters'      => (float) ($t->y_meters ?? 0),
                'width_meters'  => (float) ($t->width_meters ?? 0.8),
                'length_meters' => (float) ($t->length_meters ?? 1.2),
                'rotation_deg'  => (int) ($t->rotation_deg ?? 0),
                'shape'         => $t->shape ?? 'rectangle',
                'tuya_device_id'=> $t->tuya_device_id,
                'rental_price_per_hour' => (float) ($t->rental_price_per_hour ?? 0),
            ]);

        $elements = $floor->elements()
            ->get()
            ->map(fn ($e) => [
                'id'            => $e->id,
                'type'         => $e->type,
                'name'         => $e->name,
                'x_meters'     => (float) $e->x_meters,
                'y_meters'     => (float) $e->y_meters,
                'width_meters' => (float) $e->width_meters,
                'length_meters'=> (float) $e->length_meters,
                'rotation_deg' => (int) $e->rotation_deg,
                'meta'         => $e->meta ?? [],
            ]);

        $nextTableName = $this->nextTableName($store);

        return Inertia::render('Admin/FloorPlan/Edit', [
            'store'   => $store->only('id', 'name', 'slug'),
            'floor'   => [
                'id'            => $floor->id,
                'name'          => $floor->name,
                'width_meters'  => (float) $floor->width_meters,
                'length_meters' => (float) $floor->length_meters,
            ],
            'tables'  => $tables,
            'elements'=> $elements,
            'element_types' => FloorElement::typeLabels(),
            'next_table_name' => $nextTableName,
        ]);
    }

    private function nextTableName(Store $store): string
    {
        $names = DiningTable::where('store_id', $store->id)->pluck('name');
        $used = [];
        foreach ($names as $n) {
            if (preg_match('/^(\d+)$/', trim($n), $m)) {
                $used[(int) $m[1]] = true;
            } elseif (preg_match('/(\d+)/', $n, $m)) {
                $used[(int) $m[1]] = true;
            }
        }
        for ($i = 1; $i <= 9999; $i++) {
            if (empty($used[$i])) {
                return (string) $i;
            }
        }
        return '1';
    }

    public function printQr(Store $store, Floor $floor): Response
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id, 403);

        $tables = $floor->diningTables()
            ->with('store')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id'      => $t->id,
                'name'    => $t->name,
                'qr_url'  => $t->qrUrl(),
            ]);

        return Inertia::render('Admin/FloorPlan/PrintQr', [
            'store'  => $store->only('id', 'name', 'slug'),
            'floor'  => ['id' => $floor->id, 'name' => $floor->name],
            'tables' => $tables,
        ]);
    }

    public function storeFloor(Store $store, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:50'],
            'width_meters'  => ['required', 'numeric', 'min:1', 'max:100'],
            'length_meters' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        Floor::create([
            'store_id'      => $store->id,
            'name'          => $data['name'],
            'width_meters'  => $data['width_meters'],
            'length_meters' => $data['length_meters'],
            'sort_order'    => Floor::where('store_id', $store->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', "Lantai {$data['name']} berhasil ditambahkan.");
    }

    public function updateFloor(Store $store, Floor $floor, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id, 403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:50'],
            'width_meters'  => ['required', 'numeric', 'min:1', 'max:100'],
            'length_meters' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $floor->update($data);

        return back()->with('success', "Lantai {$floor->name} berhasil diperbarui.");
    }

    public function destroyFloor(Store $store, Floor $floor): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id, 403);

        $floor->diningTables()->update(['floor_id' => null]);
        $floor->elements()->delete();
        $floor->delete();

        return back()->with('success', "Lantai {$floor->name} berhasil dihapus.");
    }

    public function saveLayout(Store $store, Floor $floor, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id, 403);

        $data = $request->validate([
            'tables'   => ['array'],
            'tables.*' => ['array'],
            'tables.*.id' => ['required', 'exists:dining_tables,id'],
            'tables.*.x_meters' => ['nullable', 'numeric', 'min:0'],
            'tables.*.y_meters' => ['nullable', 'numeric', 'min:0'],
            'tables.*.width_meters' => ['nullable', 'numeric', 'min:0.3'],
            'tables.*.length_meters' => ['nullable', 'numeric', 'min:0.3'],
            'tables.*.rotation_deg' => ['nullable', 'integer', 'min:0', 'max:360'],
            'elements' => ['array'],
            'elements.*' => ['array'],
            'elements.*.id' => ['nullable', 'exists:floor_elements,id'],
            'elements.*.type' => ['required', 'string', 'in:pillar,stairs,cashier,wall,door,counter,other'],
            'elements.*.name' => ['nullable', 'string', 'max:50'],
            'elements.*.x_meters' => ['nullable', 'numeric', 'min:0'],
            'elements.*.y_meters' => ['nullable', 'numeric', 'min:0'],
            'elements.*.width_meters' => ['nullable', 'numeric', 'min:0.1'],
            'elements.*.length_meters' => ['nullable', 'numeric', 'min:0.1'],
            'elements.*.rotation_deg' => ['nullable', 'integer', 'min:0', 'max:360'],
        ]);

        foreach ($data['tables'] ?? [] as $t) {
            DiningTable::where('id', $t['id'])
                ->where('floor_id', $floor->id)
                ->update([
                    'x_meters'      => $t['x_meters'] ?? 0,
                    'y_meters'      => $t['y_meters'] ?? 0,
                    'width_meters'  => $t['width_meters'] ?? 0.8,
                    'length_meters' => $t['length_meters'] ?? 1.2,
                    'rotation_deg'  => $t['rotation_deg'] ?? 0,
                ]);
        }

        foreach ($data['elements'] ?? [] as $e) {
            if (!empty($e['id'])) {
                FloorElement::where('id', $e['id'])
                    ->where('floor_id', $floor->id)
                    ->update([
                        'type'         => $e['type'],
                        'name'         => $e['name'] ?? null,
                        'x_meters'     => $e['x_meters'] ?? 0,
                        'y_meters'     => $e['y_meters'] ?? 0,
                        'width_meters' => $e['width_meters'] ?? 0.5,
                        'length_meters'=> $e['length_meters'] ?? 0.5,
                        'rotation_deg' => $e['rotation_deg'] ?? 0,
                    ]);
            } else {
                FloorElement::create([
                    'floor_id'      => $floor->id,
                    'type'         => $e['type'],
                    'name'         => $e['name'] ?? null,
                    'x_meters'     => $e['x_meters'] ?? 0,
                    'y_meters'     => $e['y_meters'] ?? 0,
                    'width_meters' => $e['width_meters'] ?? 0.5,
                    'length_meters'=> $e['length_meters'] ?? 0.5,
                    'rotation_deg' => $e['rotation_deg'] ?? 0,
                ]);
            }
        }

        return back();
    }

    public function addTable(Store $store, Floor $floor, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id, 403);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'x_meters' => ['nullable', 'numeric', 'min:0'],
            'y_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $maxName = DiningTable::where('store_id', $store->id)->where('name', $data['name'])->exists();
        if ($maxName) {
            return back()->withErrors(['name' => 'Nama meja sudah digunakan di toko ini.']);
        }

        DiningTable::create([
            'store_id'      => $store->id,
            'floor_id'     => $floor->id,
            'name'         => $data['name'],
            'capacity'     => $data['capacity'] ?? 4,
            'x_meters'     => $data['x_meters'] ?? 0,
            'y_meters'     => $data['y_meters'] ?? 0,
            'floor'        => $floor->name,
        ]);

        return back()->with('success', "Meja {$data['name']} berhasil ditambahkan.");
    }

    public function addElement(Store $store, Floor $floor, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id, 403);

        $data = $request->validate([
            'type'         => ['required', 'string', 'in:pillar,stairs,cashier,wall,door,counter,other'],
            'name'         => ['nullable', 'string', 'max:50'],
            'x_meters'     => ['nullable', 'numeric', 'min:0'],
            'y_meters'     => ['nullable', 'numeric', 'min:0'],
            'width_meters' => ['nullable', 'numeric', 'min:0.1'],
            'length_meters'=> ['nullable', 'numeric', 'min:0.1'],
        ]);

        $label = FloorElement::typeLabels()[$data['type']] ?? $data['type'];

        FloorElement::create([
            'floor_id'      => $floor->id,
            'type'         => $data['type'],
            'name'         => $data['name'] ?? $label,
            'x_meters'     => $data['x_meters'] ?? 0,
            'y_meters'     => $data['y_meters'] ?? 0,
            'width_meters' => $data['width_meters'] ?? 0.5,
            'length_meters'=> $data['length_meters'] ?? 0.5,
        ]);

        return back()->with('success', "{$label} berhasil ditambahkan.");
    }

    public function removeTable(Store $store, Floor $floor, DiningTable $table): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id && $table->floor_id === $floor->id, 403);

        $table->update(['floor_id' => null]);
        return back()->with('success', "Meja {$table->name} dipindahkan dari denah.");
    }

    public function removeElement(Store $store, Floor $floor, FloorElement $element): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id && $element->floor_id === $floor->id, 403);

        $element->delete();
        return back()->with('success', 'Elemen berhasil dihapus.');
    }

    public function copyTable(Store $store, Floor $floor, DiningTable $table): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id && $table->floor_id === $floor->id, 403);

        $copyName = $this->nextTableName($store);

        DiningTable::create([
            'store_id'      => $store->id,
            'floor_id'     => $floor->id,
            'name'         => $copyName,
            'capacity'     => $table->capacity,
            'x_meters'     => max(0, min($table->x_meters + 0.5, $floor->width_meters - $table->width_meters)),
            'y_meters'     => max(0, min($table->y_meters + 0.5, $floor->length_meters - $table->length_meters)),
            'width_meters' => $table->width_meters,
            'length_meters'=> $table->length_meters,
            'floor'        => $floor->name,
        ]);

        return back()->with('success', "Meja {$copyName} berhasil dibuat.");
    }

    public function copyElement(Store $store, Floor $floor, FloorElement $element): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id && $element->floor_id === $floor->id, 403);

        FloorElement::create([
            'floor_id'      => $floor->id,
            'type'         => $element->type,
            'name'         => ($element->name ?: FloorElement::typeLabels()[$element->type] ?? $element->type) . ' (copy)',
            'x_meters'     => max(0, min($element->x_meters + 0.5, $floor->width_meters - $element->width_meters)),
            'y_meters'     => max(0, min($element->y_meters + 0.5, $floor->length_meters - $element->length_meters)),
            'width_meters' => $element->width_meters,
            'length_meters'=> $element->length_meters,
            'rotation_deg' => $element->rotation_deg,
        ]);

        return back()->with('success', 'Elemen berhasil diduplikasi.');
    }

    public function updateMeta(Store $store, Floor $floor, DiningTable $table, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id && $table->floor_id === $floor->id, 403);

        $data = $request->validate([
            'name'                  => ['nullable', 'string', 'max:50'],
            'tuya_device_id'        => ['nullable', 'string', 'max:100'],
            'rental_price_per_hour' => ['nullable', 'numeric', 'min:0'],
        ]);

        $table->update($data);

        return back()->with('success', "Data unit PS {$table->name} berhasil diperbarui.");
    }

    public function toggleTuya(Store $store, Floor $floor, DiningTable $table): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id && $table->floor_id === $floor->id, 403);

        $newStatus = ! $table->tuya_status;
        $table->update(['tuya_status' => $newStatus]);

        if ($table->tuya_device_id) {
            app(\App\Services\TuyaService::class)->sendCommand($table->tuya_device_id, 'switch_1', $newStatus);
        }

        $msg = $newStatus ? "Unit PS {$table->name} dinyalakan." : "Unit PS {$table->name} dimatikan.";
        return back()->with('success', $msg);
    }

    public function startRental(Store $store, Floor $floor, DiningTable $table, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id && $table->floor_id === $floor->id, 403);

        $data = $request->validate([
            'duration_minutes' => ['required', 'integer', 'min:1'],
        ]);

        $orderId = DB::transaction(function () use ($store, $table, $data) {
            // Logic for auto-starting a rental: Create a special 'rental' order
            // or modify an existing one. For now, creating a new one as 'pending/active'
            
            $prefix = 'RNL-' . now()->format('Ymd') . '-';
            $last = Order::where('store_id', $store->id)->where('order_code', 'like', $prefix . '%')->latest('id')->first();
            $seq = ($last ? (int) substr($last->order_code, -4) + 1 : 1);
            $code = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            $order = Order::create([
                'store_id'      => $store->id,
                'table_id'      => $table->id,
                'order_code'    => $code,
                'type'          => 'dine_in',
                'is_rental'     => true,
                'status'        => 'confirmed', // Assuming it's active immediately
                'rental_started_at' => now(),
                'rental_duration_minutes' => $data['duration_minutes'],
                'rental_end_at' => now()->addMinutes($data['duration_minutes']),
                'subtotal'      => round(($table->rental_price_per_hour / 60) * $data['duration_minutes']),
                'final_amount'  => round(($table->rental_price_per_hour / 60) * $data['duration_minutes']),
                'payment_status'=> 'pending',
                'cashier_id'    => auth()->id(),
            ]);

            return $order->id;
        });

        return back()->with('success', "Rental PS {$table->name} dimulai.");
    }

    public function stopRental(Store $store, Floor $floor, DiningTable $table): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id && $table->floor_id === $floor->id, 403);

        // Find active rental order and finish it
        $order = Order::where('table_id', $table->id)
            ->where('is_rental', true)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->first();

        if ($order) {
            $order->update([
                'status' => 'done',
                'rental_end_at' => now(),
            ]);
        }

        // Turn off PS via Tuya
        $table->update(['tuya_status' => false]);
        if ($table->tuya_device_id) {
            app(\App\Services\TuyaService::class)->sendCommand($table->tuya_device_id, 'switch_1', false);
        }

        return back()->with('success', "Rental PS {$table->name} dihentikan.");
    }

    public function addDuration(Store $store, Floor $floor, DiningTable $table, Request $request): RedirectResponse
    {
        $this->authorizeStore($store);
        abort_unless($floor->store_id === $store->id && $table->floor_id === $floor->id, 403);

        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:480'],
        ]);

        $order = Order::where('table_id', $table->id)
            ->where('is_rental', true)
            ->whereNotIn('status', ['done', 'cancelled', 'ready'])
            ->first();

        if ($order) {
            $currentEnd = $order->rental_end_at ?? now();
            $newEnd = \Carbon\Carbon::parse($currentEnd)->addMinutes($data['minutes']);
            $newDuration = ($order->rental_duration_minutes ?? 0) + $data['minutes'];
            $extraCost = round(($table->rental_price_per_hour / 60) * $data['minutes']);

            $order->update([
                'rental_end_at' => $newEnd,
                'rental_duration_minutes' => $newDuration,
                'final_amount' => $order->final_amount + $extraCost,
                'subtotal' => $order->subtotal + $extraCost,
            ]);
        }

        return back()->with('success', "Durasi rental {$table->name} ditambah {$data['minutes']} menit.");
    }
}
