<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useElementSize } from '@vueuse/core'
import { Link } from '@inertiajs/vue3'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { formatCurrency } from '@/lib/utils'
import { LayoutGrid, ZoomIn, ZoomOut, Maximize2, Receipt, Loader2, Power, Square, Clock, Gamepad2 } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'

const BASE_PIXELS_PER_METER = 80

interface TableOrder {
    id: number
    order_code: string
    status: string
    final_amount: number
    items_count: number
    created_at: string
    is_rental: boolean
    rental_started_at: string | null
    rental_end_at: string | null
    rental_duration_minutes: number | null
    items?: Array<{ name: string; qty: number; price: number; total: number }>
}

interface FloorTable {
    id: number
    name: string
    capacity: number
    x_meters: number
    y_meters: number
    width_meters: number
    length_meters: number
    rotation_deg: number
    shape: string
    active_orders: TableOrder[]
    has_orders: boolean
    tuya_device_id: string | null
    tuya_status: boolean
    rental_price_per_hour: number
}

interface FloorData {
    id: number
    name: string
    width_meters: number
    length_meters: number
    tables: FloorTable[]
}

const props = defineProps<{
    open: boolean
    store: { id: number; name: string; slug: string }
    floorPlan: FloorData[]
}>()

const emit = defineEmits<{ (e: 'update:open', v: boolean): void }>()

const selectedFloorIndex = ref(0)
const selectedTableId = ref<number | null>(null)
const selectedTable = computed(() =>
    currentFloor.value?.tables.find(t => t.id === selectedTableId.value) ?? null
)
const viewportRef = ref<HTMLElement | null>(null)
const { width: viewportW, height: viewportH } = useElementSize(viewportRef)

const userZoom = ref(1)
const ZOOM_MIN = 0.5
const ZOOM_MAX = 2
const ZOOM_STEP = 0.25

const currentFloor = computed(() => props.floorPlan[selectedFloorIndex.value] ?? null)

const floorWidthPx = computed(() => {
    const f = currentFloor.value
    if (!f) return 400
    return Math.max(400, f.width_meters * BASE_PIXELS_PER_METER)
})

const floorHeightPx = computed(() => {
    const f = currentFloor.value
    if (!f) return 300
    return Math.max(300, f.length_meters * BASE_PIXELS_PER_METER)
})

const fitScale = computed(() => {
    const w = viewportW.value || 400
    const h = viewportH.value || 300
    const fw = floorWidthPx.value
    const fh = floorHeightPx.value
    const sx = w / fw
    const sy = h / fh
    return Math.min(1, sx, sy)
})

const scale = computed(() => fitScale.value * userZoom.value)

const scaledWidthPx = computed(() => Math.round(floorWidthPx.value * scale.value))
const scaledHeightPx = computed(() => Math.round(floorHeightPx.value * scale.value))

function toPx(meters: number) {
    return Math.round(meters * BASE_PIXELS_PER_METER)
}

function zoomIn() {
    userZoom.value = Math.min(ZOOM_MAX, userZoom.value + ZOOM_STEP)
}
function zoomOut() {
    userZoom.value = Math.max(ZOOM_MIN, userZoom.value - ZOOM_STEP)
}
function zoomFit() {
    userZoom.value = 1
}

function selectTable(table: FloorTable) {
    selectedTableId.value = selectedTableId.value === table.id ? null : table.id
}

function toggleTuya(table: FloorTable) {
    if (!currentFloor.value) return
    router.post(`/admin/stores/${props.store.id}/floor-plan/${currentFloor.value.id}/tables/${table.id}/toggle-tuya`, {}, {
        preserveScroll: true,
        onSuccess: () => {
             // Status will be updated via props reload
        }
    })
}

function stopRental(table: FloorTable) {
    if (!currentFloor.value) return
    router.post(`/admin/stores/${props.store.id}/floor-plan/${currentFloor.value.id}/tables/${table.id}/stop-rental`, {}, {
        preserveScroll: true,
    })
}

function close() {
    emit('update:open', false)
    selectedTableId.value = null
    selectedOrderDetail.value = null
}

const now = ref(new Date())
let timerInterval: any = null
let statusPollInterval: any = null

onMounted(() => {
    timerInterval = setInterval(() => {
        now.value = new Date()
    }, 1000)
    // Poll status Tuya tiap 2 detik supaya kasir otomatis update ON/OFF
    statusPollInterval = setInterval(() => {
        router.reload({ only: ['floor_plan'] })
    }, 2000)
})

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval)
    if (statusPollInterval) clearInterval(statusPollInterval)
})

function getRemainingTime(order: TableOrder) {
    if (!order.rental_end_at) {
        if (!order.rental_started_at) return '00:00:00'
        const ms = now.value.getTime() - new Date(order.rental_started_at).getTime()
        const totalSec = Math.floor(Math.max(0, ms) / 1000)
        const h = Math.floor(totalSec / 3600)
        const m = Math.floor((totalSec % 3600) / 60)
        const s = totalSec % 60
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
    }

    const end = new Date(order.rental_end_at)
    const diff = end.getTime() - now.value.getTime()
    if (diff <= 0) return 'Habis'
    
    const minutes = Math.floor(diff / 60000)
    const seconds = Math.floor((diff % 60000) / 1000)
    
    if (minutes > 60) {
        const hours = Math.floor(minutes / 60)
        const minsRemaining = minutes % 60
        return `${hours}j ${minsRemaining}m`
    }
    
    return `${minutes}m ${seconds}s`
}

function getRunningCost(order: TableOrder, pricePerHour: number): number {
    if (order.rental_end_at) return order.final_amount

    if (!order.rental_started_at) return 0
    const ms = now.value.getTime() - new Date(order.rental_started_at).getTime()
    const msSafe = Math.max(0, ms)
    
    const totalSec = Math.floor(msSafe / 1000)
    const costPerSec = pricePerHour / 3600
    return Math.floor(costPerSec * totalSec)
}

interface OrderDetailItem {
    product_name: string
    quantity: number
    unit: string
    unit_price: number
    subtotal: number
}

interface OrderDetail {
    id: number
    order_code: string
    type: string
    status: string
    payment_status: string
    subtotal: number
    discount_amount: number
    final_amount: number
    notes: string | null
    table_name: string | null
    cashier_name: string | null
    customer_name: string | null
    customer_phone: string | null
    customer_email: string | null
    created_at: string
    items: OrderDetailItem[]
}

const selectedOrderDetail = ref<OrderDetail | null>(null)
const orderDetailLoadingId = ref<number | null>(null)

async function openOrderDetail(orderId: number) {
    selectedOrderDetail.value = null
    orderDetailLoadingId.value = orderId
    try {
        const res = await fetch((window as any).route('admin.orders.detail', orderId), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        if (res.ok) {
            const data = await res.json()
            selectedOrderDetail.value = data
        }
    } finally {
        orderDetailLoadingId.value = null
    }
}

const statusLabels: Record<string, string> = {
    pending: 'Pending',
    confirmed: 'Dikonfirmasi',
    processing: 'Diproses',
    ready: 'Siap',
    done: 'Selesai',
    cancelled: 'Batal',
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex max-h-[90vh] max-w-4xl flex-col overflow-hidden">
            <DialogHeader class="shrink-0">
                <DialogTitle class="flex items-center gap-2">
                    <Gamepad2 class="h-5 w-5" />
                    Panel Kontrol PS — {{ store.name }}
                </DialogTitle>
                <DialogDescription>
                    Klik unit PS untuk menyalakan/mematikan atau memulai rental.
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 overflow-y-auto">
            <div v-if="!floorPlan || floorPlan.length === 0" class="flex flex-col items-center justify-center py-16 text-center text-muted-foreground">
                <Gamepad2 class="mb-3 h-12 w-12 opacity-50" />
                <p class="font-medium">Belum ada area rental PS</p>
                <p class="mt-1 text-sm">Atur area rental PS di menu Rental PS untuk melihat posisi unit PS.</p>
                <Link :href="`/admin/stores/${store.id}/floor-plan`" class="mt-4">
                    <Button variant="outline" size="sm">
                        Ke Pengaturan Rental PS
                    </Button>
                </Link>
            </div>

            <template v-else>
                <!-- Toolbar -->
                <div class="flex flex-wrap items-center gap-2 border-b pb-3">
                    <div class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                        <button
                            type="button"
                            class="rounded p-1.5 transition-colors hover:bg-accent disabled:opacity-40"
                            :disabled="userZoom <= ZOOM_MIN"
                            @click="zoomOut"
                        >
                            <ZoomOut class="h-4 w-4" />
                        </button>
                        <span class="min-w-[4rem] text-center text-sm font-medium">{{ Math.round((fitScale > 0 ? scale / fitScale : 1) * 100) }}%</span>
                        <button
                            type="button"
                            class="rounded p-1.5 transition-colors hover:bg-accent disabled:opacity-40"
                            :disabled="userZoom >= ZOOM_MAX"
                            @click="zoomIn"
                        >
                            <ZoomIn class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded p-1.5 transition-colors hover:bg-accent"
                            @click="zoomFit"
                        >
                            <Maximize2 class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex gap-1">
                        <Button
                            v-for="(f, i) in floorPlan"
                            :key="f.id"
                            :variant="selectedFloorIndex === i ? 'default' : 'outline'"
                            size="sm"
                            @click="selectedFloorIndex = i; selectedTableId = null"
                        >
                            {{ f.name }}
                        </Button>
                    </div>
                </div>

                <!-- Floor canvas -->
                <div
                    ref="viewportRef"
                    class="relative flex shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-muted-foreground/30 bg-teal-950/90"
                    :class="scale > fitScale ? 'overflow-auto' : 'overflow-hidden'"
                    style="min-height: 260px; height: 42vh; max-height: 400px;"
                >
                    <div
                        v-if="currentFloor"
                        class="relative m-auto shrink-0 overflow-hidden origin-top-left rounded-lg"
                        :style="{
                            width: scaledWidthPx + 'px',
                            height: scaledHeightPx + 'px',
                            minWidth: scaledWidthPx + 'px',
                            minHeight: scaledHeightPx + 'px',
                        }"
                    >
                        <div
                            class="absolute left-0 top-0 rounded-lg"
                            :style="{
                                width: floorWidthPx + 'px',
                                height: floorHeightPx + 'px',
                                transform: `scale(${scale})`,
                                transformOrigin: 'top left',
                            }"
                        >
                            <!-- Grid -->
                        <template v-for="i in Math.floor(currentFloor.width_meters)" :key="'v-' + i">
                            <div
                                v-if="i < currentFloor.width_meters"
                                class="absolute top-0 bottom-0 w-px bg-white/30"
                                :style="{ left: toPx(i) + 'px' }"
                            />
                        </template>
                        <template v-for="i in Math.floor(currentFloor.length_meters)" :key="'h-' + i">
                            <div
                                v-if="i < currentFloor.length_meters"
                                class="absolute left-0 right-0 h-px bg-white/30"
                                :style="{ top: toPx(i) + 'px' }"
                            />
                        </template>

                        <!-- Tables -->
                        <div
                            v-for="t in currentFloor.tables"
                            :key="t.id"
                            class="absolute cursor-pointer select-none rounded-lg border-2 text-center shadow-sm transition-all hover:scale-105"
                            :class="t.has_orders
                                ? 'border-amber-400 bg-amber-400/90 text-amber-950'
                                : (t.tuya_status ? 'border-green-500 bg-green-500/20 text-green-900 dark:text-green-100' : 'border-amber-400/60 bg-amber-50/80 text-amber-900')"
                            :style="{
                                left: toPx(t.x_meters) + 'px',
                                top: toPx(t.y_meters) + 'px',
                                width: toPx(t.width_meters) + 'px',
                                height: toPx(t.length_meters) + 'px',
                            }"
                            @click="selectTable(t)"
                        >
                            <div class="flex h-full w-full flex-col items-center justify-center gap-0.5 px-1 py-1">
                                <span class="font-semibold text-xs">{{ t.name }}</span>
                                <div v-if="t.tuya_device_id" class="flex gap-1">
                                    <div :class="['h-2 w-2 rounded-full', t.tuya_status ? 'bg-green-500 animate-pulse' : 'bg-red-500']"></div>
                                </div>
                                <span v-if="t.has_orders" class="rounded bg-amber-200/80 px-1 py-0.5 text-[8px] font-bold">
                                    RENTAL AKTIF
                                </span>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Order detail panel -->
                <div
                    v-if="selectedTable"
                    class="mt-3 overflow-hidden rounded-lg border bg-muted/30"
                >
                    <div class="flex items-center justify-between border-b bg-muted/50 px-4 py-2">
                        <p class="font-medium">Unit PS {{ selectedTable.name }}</p>
                        <div class="flex items-center gap-2">
                            <span v-if="selectedTable.tuya_device_id" :class="['text-[10px] font-medium uppercase', selectedTable.tuya_status ? 'text-green-600' : 'text-red-500']">
                                {{ selectedTable.tuya_status ? 'Nyala' : 'Mati' }}
                            </span>
                            <Button
                                v-if="selectedTable.tuya_device_id"
                                @click="toggleTuya(selectedTable)"
                            >
                                <Power class="h-3.5 w-3.5" :class="selectedTable.tuya_status ? 'text-green-600' : 'text-red-500'" />
                            </Button>
                        </div>
                    </div>

                    <div class="p-4">
                        <div v-if="!selectedTable.has_orders" class="flex flex-col items-center gap-3 py-2 text-center">
                            <p class="text-sm text-muted-foreground">Tidak ada rental aktif pada unit ini.</p>
                            <p class="text-xs text-muted-foreground">Mulai rental dengan memilih paket dari panel Rental PS.</p>
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="ord in selectedTable.active_orders"
                                :key="ord.id"
                                class="rounded-lg border bg-background p-3"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-mono text-sm font-semibold">{{ ord.order_code }}</p>
                                            <span v-if="ord.is_rental" class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700">RENTAL</span>
                                        </div>
                                        <div class="mt-1 flex items-center gap-2">
                                            <p class="text-xs text-muted-foreground">
                                                {{ ord.items_count }} item · <span class="font-mono font-bold text-primary">{{ formatCurrency(selectedTable ? getRunningCost(ord, selectedTable.rental_price_per_hour) : ord.final_amount) }}</span>
                                            </p>
                                            <span :class="['rounded px-1.5 py-0.5 text-[10px] font-bold uppercase', 
                                                ord.status === 'ready' ? 'bg-green-100 text-green-700' : 'bg-muted text-muted-foreground']">
                                                {{ (statusLabels ?? {})[ord.status] ?? ord.status }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :disabled="orderDetailLoadingId === ord.id"
                                            @click="openOrderDetail(ord.id)"
                                        >
                                            <Receipt class="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                            v-if="ord.is_rental && ord.status !== 'ready'"
                                            variant="destructive"
                                            size="sm"
                                            @click="stopRental(selectedTable)"
                                        >
                                            <Square class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                                
                                <div v-if="ord.is_rental" class="mt-3 flex items-center gap-2 rounded-md bg-blue-50 p-2 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">
                                    <Clock class="h-4 w-4" />
                                    <div class="flex-1 text-xs">
                                        <div class="flex items-center justify-between">
                                            <p class="font-medium">Sisa Waktu: <span class="font-bold text-blue-600 dark:text-blue-400">{{ getRemainingTime(ord) }}</span></p>
                                            <p class="opacity-60">{{ ord.rental_duration_minutes }}m</p>
                                        </div>
                                        <p v-if="ord.rental_end_at" class="opacity-80">Berakhir pada: {{ new Date(ord.rental_end_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="mt-3 flex flex-wrap items-center gap-4 border-t pt-3 text-sm">
                    <div class="flex items-center gap-2">
                        <div class="h-5 w-5 rounded border-2 border-amber-400 bg-amber-400/90" />
                        <span>Rental Aktif</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-5 w-5 rounded border-2 border-green-500 bg-green-500/20" />
                        <span>PS Nyala (Kosong)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-5 w-5 rounded border-2 border-amber-400/60 bg-amber-50/80" />
                        <span>PS Mati</span>
                    </div>
                </div>
            </template>
            </div>

            <div class="shrink-0 flex justify-end border-t pt-3">
                <Button variant="outline" @click="close">
                    Tutup
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Modal Detail Pesanan (nested) -->
    <Dialog :open="!!selectedOrderDetail" @update:open="(v) => { if (!v) selectedOrderDetail = null }">
        <DialogContent class="max-w-md max-h-[85vh] flex flex-col overflow-hidden" v-if="selectedOrderDetail">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Receipt class="h-5 w-5" />
                    {{ selectedOrderDetail.order_code }}
                </DialogTitle>
                <DialogDescription>
                    {{ selectedOrderDetail.table_name ? `Unit PS ${selectedOrderDetail.table_name}` : '—' }} · {{ selectedOrderDetail.created_at }}
                </DialogDescription>
            </DialogHeader>
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto">
                <div class="grid gap-2 text-sm sm:grid-cols-2">
                    <p v-if="selectedOrderDetail.customer_name"><span class="text-muted-foreground">Pelanggan:</span> {{ selectedOrderDetail.customer_name }}</p>
                    <p v-if="selectedOrderDetail.customer_phone"><span class="text-muted-foreground">Telp:</span> {{ selectedOrderDetail.customer_phone }}</p>
                    <p v-if="selectedOrderDetail.customer_email"><span class="text-muted-foreground">Email:</span> {{ selectedOrderDetail.customer_email }}</p>
                    <p><span class="text-muted-foreground">Status:</span> {{ (statusLabels ?? {})[selectedOrderDetail.status] ?? selectedOrderDetail.status }}</p>
                </div>
                <div>
                    <p class="mb-2 font-medium">Detail Pesanan</p>
                    <div class="rounded-lg border text-sm">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-3 py-2 font-medium">Produk</th>
                                    <th class="px-3 py-2 text-center font-medium">Qty</th>
                                    <th class="px-3 py-2 text-right font-medium">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, i) in selectedOrderDetail.items" :key="i" class="border-b last:border-0">
                                    <td class="px-3 py-2">{{ item.product_name }}</td>
                                    <td class="px-3 py-2 text-center">{{ item.quantity }} {{ item.unit }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(item.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="rounded-lg border p-3">
                    <div class="flex justify-between font-semibold">
                        <span>Total</span>
                        <span>{{ formatCurrency(selectedOrderDetail.final_amount) }}</span>
                    </div>
                </div>
                <p v-if="selectedOrderDetail.notes" class="text-sm text-muted-foreground">
                    <span class="font-medium">Catatan:</span> {{ selectedOrderDetail.notes }}
                </p>
            </div>
            <div class="shrink-0 flex justify-end border-t pt-3">
                <Button variant="outline" @click="selectedOrderDetail = null">Tutup</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
