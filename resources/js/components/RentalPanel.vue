<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useElementSize } from '@vueuse/core'
import { router } from '@inertiajs/vue3'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { formatCurrency } from '@/lib/utils'
import {
    Power, Play, Square, Clock, Gamepad2, Plus, Minus, Zap,
    ZoomIn, ZoomOut, Maximize2, Timer, ShoppingCart, Wifi, WifiOff, Info, CreditCard,
} from 'lucide-vue-next'

// ─── Types ─────────────────────────────────────────────────────────────────────
interface TableOrder {
    id: number
    order_code: string
    status: string
    payment_status: string
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

interface CartItem {
    id: string
    product_id: number
    name: string
    unit: string
    sell_price: number
    discount_percent: number
    quantity: number
    notes?: string
    modifiers: Array<{ option_id: number; name: string; price_extra: number }>
}

interface Product {
    id: number
    name: string
    sku?: string | null
    category_id?: number | null
    sell_price: number
    unit: string
    track_stock: boolean
    current_stock: number
    discount_percent: number
    category_name: string | null
    category_color: string | null
    image_url?: string | null
    is_rental_package: boolean
    rental_duration_minutes: number | null
    included_items_json?: any[]
    modifier_groups?: Array<{
        id: number; name: string; is_required: boolean; min_select: number; max_select: number
        options: Array<{ id: number; name: string; price_extra: number; is_active?: boolean; is_available?: boolean }>
    }>
}

// ─── Props & Emits ─────────────────────────────────────────────────────────────
const props = defineProps<{
    store: { id: number; name: string; slug: string }
    floorPlan: FloorData[]
    products: Product[]
    cart: CartItem[]
}>()

const emit = defineEmits<{
    (e: 'add-to-cart', item: { product: Product; tableId: number | null }): void
    (e: 'update:floor-plan', plan: FloorData[]): void
    (e: 'checkout'): void
    (e: 'pay-order', orderId: number): void
}>()

// ─── State ─────────────────────────────────────────────────────────────────────
const BASE_PX = 80
const selectedFloorIndex = ref(0)
const selectedTable = ref<FloorTable | null>(null)
const viewportRef = ref<HTMLElement | null>(null)
const { width: vpW, height: vpH } = useElementSize(viewportRef)
const userZoom = ref(1)
const ZOOM_MIN = 0.4
const ZOOM_MAX = 2.5
const ZOOM_STEP = 0.25
const now = ref(new Date())
let timerInterval: any = null
const toggling = ref<Record<number, boolean>>({})
const startingRental = ref<Record<number, boolean>>({})
const stoppingRental = ref<Record<number, boolean>>({})

// Dialogs
const showStartRentalDialog = ref(false)
const startRentalForm = ref({ duration: 60 })
const startMode = ref<'paket' | 'custom' | 'open'>('paket')
const selectedPackageId = ref<number | null>(null)
const showAddDurationDialog = ref(false)
const addDurationForm = ref({ minutes: 30 })
const showProductDialog = ref(false)
const showUnitDetail = ref(false)

// ─── Computed ──────────────────────────────────────────────────────────────────
const rentalPackages = computed(() =>
    props.products.filter(p => p.is_rental_package && p.rental_duration_minutes)
)
const selectedPackage = computed(() =>
    rentalPackages.value.find(p => p.id === selectedPackageId.value) ?? null
)
const currentFloor = computed(() => props.floorPlan[selectedFloorIndex.value] ?? null)

const floorW = computed(() => Math.max(400, (currentFloor.value?.width_meters ?? 5) * BASE_PX))
const floorH = computed(() => Math.max(300, (currentFloor.value?.length_meters ?? 5) * BASE_PX))

const fitScale = computed(() => {
    const sx = (vpW.value || 400) / floorW.value
    const sy = (vpH.value || 300) / floorH.value
    return Math.min(1, sx, sy)
})

const scale = computed(() => fitScale.value * userZoom.value)
const scaledW = computed(() => Math.round(floorW.value * scale.value))
const scaledH = computed(() => Math.round(floorH.value * scale.value))

function toPx(m: number) { return Math.round(m * BASE_PX) }
function zoomIn() { userZoom.value = Math.min(ZOOM_MAX, userZoom.value + ZOOM_STEP) }
function zoomOut() { userZoom.value = Math.max(ZOOM_MIN, userZoom.value - ZOOM_STEP) }
function zoomFit() { userZoom.value = 1 }

// ─── Timer ─────────────────────────────────────────────────────────────────────
onMounted(() => {
    timerInterval = setInterval(() => now.value = new Date(), 1000)
})
onBeforeUnmount(() => { if (timerInterval) clearInterval(timerInterval) })

function getRemainingMs(endStr: string): number {
    return new Date(endStr).getTime() - now.value.getTime()
}

function formatRemaining(order: TableOrder): string {
    if (!order.rental_end_at) {
        if (!order.rental_started_at) return '00:00:00'
        const ms = now.value.getTime() - new Date(order.rental_started_at).getTime()
        const totalSec = Math.floor(Math.max(0, ms) / 1000)
        const h = Math.floor(totalSec / 3600)
        const m = Math.floor((totalSec % 3600) / 60)
        const s = totalSec % 60
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
    }

    const ms = getRemainingMs(order.rental_end_at)
    if (ms <= 0) return 'Habis'
    const totalSec = Math.floor(ms / 1000)
    const h = Math.floor(totalSec / 3600)
    const m = Math.floor((totalSec % 3600) / 60)
    const s = totalSec % 60
    if (h > 0) return `${h}j ${String(m).padStart(2, '0')}m`
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

function getRemainingPercent(order: TableOrder): number {
    if (!order.rental_end_at || !order.rental_started_at || !order.rental_duration_minutes) return 0
    const total = order.rental_duration_minutes * 60 * 1000
    const remaining = getRemainingMs(order.rental_end_at)
    return Math.max(0, Math.min(100, (remaining / total) * 100))
}

function isExpiringSoon(order: TableOrder): boolean {
    if (!order.rental_end_at) return false
    return getRemainingMs(order.rental_end_at) < 5 * 60 * 1000 && getRemainingMs(order.rental_end_at) > 0
}

function isExpired(order: TableOrder): boolean {
    if (!order.rental_end_at) return false
    return getRemainingMs(order.rental_end_at) <= 0
}

function getRunningCost(order: TableOrder, pricePerHour: number): number {
    if (order.rental_end_at) return order.final_amount

    if (!order.rental_started_at) return order.final_amount
    const ms = now.value.getTime() - new Date(order.rental_started_at).getTime()
    const msSafe = Math.max(0, ms)
    
    // Calculate rental cost based on exact seconds running
    const totalSec = Math.floor(msSafe / 1000)
    const costPerSec = pricePerHour / 3600
    const rentalCost = Math.floor(costPerSec * totalSec)

    return (Number(order.final_amount) || 0) + rentalCost
}

function getRentalOnlyCost(order: TableOrder, pricePerHour: number): number {
    if (order.rental_end_at) {
        // For fixed duration, rental cost is (final_amount - items)
        const itemsTotal = order.items?.reduce((s, i) => s + i.total, 0) ?? 0
        return order.final_amount - itemsTotal
    }
    
    // For open billing
    if (!order.rental_started_at) return 0
    const ms = now.value.getTime() - new Date(order.rental_started_at).getTime()
    const totalSec = Math.floor(Math.max(0, ms) / 1000)
    return Math.floor((pricePerHour / 3600) * totalSec)
}

function getRunningDurationMinutes(order: TableOrder): number {
    if (order.rental_end_at) return order.rental_duration_minutes ?? 0
    if (!order.rental_started_at) return 0
    const ms = now.value.getTime() - new Date(order.rental_started_at).getTime()
    return Math.floor(Math.max(0, ms) / 60000)
}

// ─── Table actions ─────────────────────────────────────────────────────────────
function selectTable(t: FloorTable) {
    selectedTable.value = t
    showUnitDetail.value = true
}

function closeUnitDetail() {
    showUnitDetail.value = false
    setTimeout(() => { selectedTable.value = null }, 200)
}

function getStoreFloorPrefix(): string {
    return `/admin/stores/${props.store.id}/floor-plan`
}

function getFloorUrl(action: string, tableId?: number): string {
    const f = currentFloor.value
    if (!f) return '#'
    const base = `${getStoreFloorPrefix()}/${f.id}/tables`
    if (tableId !== undefined) return `${base}/${tableId}/${action}`
    return `${base}/${action}`
}

function syncSelectedTable() {
    if (!selectedTable.value) return
    const id = selectedTable.value.id
    for (const floor of props.floorPlan) {
        const found = floor.tables.find(t => t.id === id)
        if (found) { selectedTable.value = { ...found }; return }
    }
}

// Auto-sync selectedTable whenever Inertia refreshes floorPlan props
watch(() => props.floorPlan, () => { syncSelectedTable() }, { deep: true })

function toggleTuya(table: FloorTable) {
    if (toggling.value[table.id]) return
    toggling.value[table.id] = true
    router.post(getFloorUrl('toggle-tuya', table.id), {}, {
        preserveScroll: true,
        onSuccess: () => { syncSelectedTable() },
        onFinish: () => { toggling.value[table.id] = false },
    })
}

function openStartRental(table: FloorTable) {
    selectedTable.value = table
    startRentalForm.value.duration = 60
    startMode.value = rentalPackages.value.length > 0 ? 'paket' : 'custom'
    selectedPackageId.value = null
    showStartRentalDialog.value = true
    showUnitDetail.value = false
}

function selectPackage(pkg: Product) {
    selectedPackageId.value = pkg.id
    startRentalForm.value.duration = pkg.rental_duration_minutes ?? 60
}

function submitStartRental() {
    if (!selectedTable.value) return
    const tableId = selectedTable.value.id
    startingRental.value[tableId] = true
    showStartRentalDialog.value = false

    // If a package is selected, add it (+ included items) to cart first
    if (startMode.value === 'paket' && selectedPackage.value) {
        const pkg = selectedPackage.value
        emit('add-to-cart', { product: pkg, tableId })
    }

    let payload = {}
    if (startMode.value === 'paket') {
        payload = { duration_minutes: startRentalForm.value.duration }
    } else {
        payload = { duration_minutes: startMode.value === 'open' ? null : startRentalForm.value.duration }
    }

    router.post(getFloorUrl('start-rental', tableId), payload, {
        preserveScroll: true,
        onSuccess: () => { syncSelectedTable() },
        onFinish: () => { startingRental.value[tableId] = false },
    })
}

function stopRental(table: FloorTable) {
    if (stoppingRental.value[table.id]) return
    stoppingRental.value[table.id] = true
    router.post(getFloorUrl('stop-rental', table.id), {}, {
        preserveScroll: true,
        onSuccess: () => { syncSelectedTable() },
        onFinish: () => { stoppingRental.value[table.id] = false },
    })
}

/** Stop the expired session then immediately open start-rental dialog */
function stopThenNewRental(table: FloorTable) {
    if (stoppingRental.value[table.id]) return
    stoppingRental.value[table.id] = true
    router.post(getFloorUrl('stop-rental', table.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            // After stop, re-select the table and open start rental
            selectedTable.value = table
            startRentalForm.value.duration = 60
            showStartRentalDialog.value = true
            showUnitDetail.value = true
        },
        onFinish: () => { stoppingRental.value[table.id] = false },
    })
}

function openAddDuration(table: FloorTable) {
    selectedTable.value = table
    addDurationForm.value.minutes = 30
    showAddDurationDialog.value = true
}

function submitAddDuration() {
    if (!selectedTable.value) return
    router.post(getFloorUrl('add-duration', selectedTable.value.id), {
        minutes: addDurationForm.value.minutes,
    }, {
        preserveScroll: true,
        onSuccess: () => { showAddDurationDialog.value = false; syncSelectedTable() },
    })
}

function openProductForTable(table: FloorTable) {
    selectedTable.value = table
    showProductDialog.value = true
}

function addProductToCart(product: Product) {
    emit('add-to-cart', { product, tableId: selectedTable.value?.id ?? null })
}

// ─── Active rental order helper ─────────────────────────────────────────────────
function getActiveRental(table: FloorTable): TableOrder | null {
    if (!table.active_orders) return null
    const rentals = table.active_orders.filter(o => o.is_rental && o.status !== 'done' && o.status !== 'cancelled')
    if (rentals.length === 0) return null
    // If there are multiple unpaid sessions, prioritize the one that is currently running (not 'ready')
    const running = rentals.filter(o => o.status !== 'ready')
    if (running.length > 0) return running[running.length - 1] // Get most recent running
    return rentals[rentals.length - 1] // Or most recent 'ready' (expired but unpaid)
}

function getUnitState(table: FloorTable): 'idle' | 'active' | 'expiring' | 'expired' {
    const rental = getActiveRental(table)
    if (!rental) return 'idle'
    if (!rental.rental_end_at) return 'active' // Open billing logic
    if (isExpired(rental)) return 'expired'
    if (isExpiringSoon(rental)) return 'expiring'
    return 'active'
}
</script>

<template>
    <div class="flex h-full flex-col gap-3 overflow-hidden">
        <!-- Floor tabs & zoom toolbar -->
        <div class="flex shrink-0 items-center gap-2 rounded-lg border bg-card px-3 py-2">
            <div class="flex gap-1">
                <Button
                    v-for="(f, i) in floorPlan"
                    :key="f.id"
                    :variant="selectedFloorIndex === i ? 'default' : 'outline'"
                    size="sm"
                    class="h-8 px-3 text-xs"
                    @click="selectedFloorIndex = i; selectedTable = null"
                >
                    {{ f.name }}
                </Button>
            </div>
            <div class="ml-auto flex items-center gap-1 rounded-md border bg-muted/40 p-0.5">
                <button type="button" class="rounded p-1.5 hover:bg-accent disabled:opacity-40" :disabled="userZoom <= ZOOM_MIN" @click="zoomOut"><ZoomOut class="h-3.5 w-3.5" /></button>
                <span class="min-w-[3rem] text-center text-xs font-medium">{{ Math.round((fitScale > 0 ? scale / fitScale : 1) * 100) }}%</span>
                <button type="button" class="rounded p-1.5 hover:bg-accent disabled:opacity-40" :disabled="userZoom >= ZOOM_MAX" @click="zoomIn"><ZoomIn class="h-3.5 w-3.5" /></button>
                <button type="button" class="rounded p-1.5 hover:bg-accent" @click="zoomFit"><Maximize2 class="h-3.5 w-3.5" /></button>
            </div>
        </div>

        <!-- No floor plan -->
        <div v-if="!floorPlan || floorPlan.length === 0" class="flex flex-1 flex-col items-center justify-center gap-3 text-center text-muted-foreground">
            <Gamepad2 class="h-14 w-14 opacity-30" />
            <p class="font-semibold">Belum ada area rental PS</p>
            <p class="text-sm">Buat area di menu <strong>Denah Unit PS</strong> terlebih dahulu.</p>
        </div>

        <!-- Main content: canvas + detail panel -->
        <div v-else class="flex min-h-0 flex-1 gap-3 overflow-hidden">
            <!-- Floor Canvas -->
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border bg-card">
                <div
                    ref="viewportRef"
                    class="relative flex flex-1 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-slate-900 via-slate-800 to-teal-950"
                    style="min-height: 200px;"
                >
                    <!-- Grid lines -->
                    <div
                        v-if="currentFloor"
                        class="relative m-auto origin-top-left overflow-hidden rounded-lg"
                        :style="{ width: scaledW + 'px', height: scaledH + 'px', minWidth: scaledW + 'px', minHeight: scaledH + 'px' }"
                    >
                        <div
                            class="absolute left-0 top-0"
                            :style="{ width: floorW + 'px', height: floorH + 'px', transform: `scale(${scale})`, transformOrigin: 'top left' }"
                        >
                            <!-- Vertical grid -->
                            <template v-for="i in Math.floor(currentFloor.width_meters)" :key="'v-' + i">
                                <div v-if="i < currentFloor.width_meters" class="absolute top-0 bottom-0 w-px bg-white/10" :style="{ left: toPx(i) + 'px' }" />
                            </template>
                            <!-- Horizontal grid -->
                            <template v-for="i in Math.floor(currentFloor.length_meters)" :key="'h-' + i">
                                <div v-if="i < currentFloor.length_meters" class="absolute left-0 right-0 h-px bg-white/10" :style="{ top: toPx(i) + 'px' }" />
                            </template>

                            <!-- PS Unit cards -->
                            <div
                                v-for="t in currentFloor.tables"
                                :key="t.id"
                                class="absolute cursor-pointer select-none rounded-xl border-2 transition-all duration-200"
                                :class="[
                                    selectedTable?.id === t.id ? 'ring-2 ring-white ring-offset-1 ring-offset-transparent scale-105' : 'hover:scale-102 hover:brightness-110',
                                    getUnitState(t) === 'active' ? 'border-emerald-400 bg-emerald-900/60' :
                                    getUnitState(t) === 'expiring' ? 'border-amber-400 bg-amber-900/60 animate-pulse' :
                                    getUnitState(t) === 'expired' ? 'border-red-500 bg-red-900/60' :
                                    'border-slate-500/60 bg-slate-800/60'
                                ]"
                                :style="{
                                    left: toPx(t.x_meters) + 'px',
                                    top: toPx(t.y_meters) + 'px',
                                    width: toPx(t.width_meters) + 'px',
                                    height: toPx(t.length_meters) + 'px',
                                }"
                                @click="selectTable(t)"
                            >
                                <div class="flex h-full w-full flex-col items-center justify-center gap-0.5 p-1">
                                    <Gamepad2 class="h-5 w-5 shrink-0" :class="getUnitState(t) === 'active' ? 'text-emerald-300' : getUnitState(t) === 'expiring' ? 'text-amber-300' : 'text-slate-400'" />
                                    <span class="text-center text-[10px] font-bold leading-none text-white">{{ t.name }}</span>

                                    <!-- Timer on the card -->
                                    <div v-if="getActiveRental(t)" class="mt-0.5 flex items-center gap-0.5">
                                        <Timer class="h-2.5 w-2.5" :class="getUnitState(t) === 'expiring' ? 'text-amber-300' : 'text-emerald-300'" />
                                        <span class="font-mono text-[9px] font-bold" :class="getUnitState(t) === 'expiring' ? 'text-amber-200' : 'text-emerald-200'">
                                            {{ formatRemaining(getActiveRental(t)!) }}
                                        </span>
                                    </div>

                                    <!-- Tuya status dot -->
                                    <div v-if="t.tuya_device_id" class="mt-0.5 flex items-center gap-0.5">
                                        <div :class="['h-1.5 w-1.5 rounded-full', t.tuya_status ? 'bg-green-400 animate-pulse' : 'bg-slate-500']" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="flex shrink-0 flex-wrap items-center gap-3 border-t bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
                    <div class="flex items-center gap-1.5"><div class="h-3 w-3 rounded border-2 border-emerald-400 bg-emerald-900/60" /><span>Rental Aktif</span></div>
                    <div class="flex items-center gap-1.5"><div class="h-3 w-3 rounded border-2 border-amber-400 bg-amber-900/60" /><span>Mau Habis (&lt;5m)</span></div>
                    <div class="flex items-center gap-1.5"><div class="h-3 w-3 rounded border-2 border-red-500 bg-red-900/60" /><span>Waktu Habis</span></div>
                    <div class="flex items-center gap-1.5"><div class="h-3 w-3 rounded border-2 border-slate-500 bg-slate-800/60" /><span>Unit Kosong</span></div>
                    <span class="ml-auto">{{ currentFloor?.width_meters ?? 0 }}m × {{ currentFloor?.length_meters ?? 0 }}m</span>
                </div>
            </div>

            <!-- Right panel: Unit detail (inline, not modal) -->
            <Transition name="slide-right">
                <div
                    v-if="showUnitDetail && selectedTable"
                    class="flex w-72 shrink-0 flex-col overflow-hidden rounded-xl border bg-card"
                >
                    <!-- Header -->
                    <div class="shrink-0 border-b bg-gradient-to-r from-primary/10 to-transparent p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div :class="['flex h-9 w-9 items-center justify-center rounded-lg border-2',
                                    getUnitState(selectedTable) === 'active' ? 'border-emerald-400 bg-emerald-500/20' :
                                    getUnitState(selectedTable) === 'expiring' ? 'border-amber-400 bg-amber-500/20 animate-pulse' :
                                    getUnitState(selectedTable) === 'expired' ? 'border-red-500 bg-red-500/20' :
                                    'border-muted bg-muted/50']">
                                    <Gamepad2 class="h-4 w-4" :class="getUnitState(selectedTable) === 'active' ? 'text-emerald-500' : getUnitState(selectedTable) === 'expiring' ? 'text-amber-500' : 'text-muted-foreground'" />
                                </div>
                                <div>
                                    <p class="font-bold">{{ selectedTable.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ formatCurrency(selectedTable.rental_price_per_hour) }}/jam</p>
                                </div>
                            </div>
                            <button type="button" class="rounded-lg p-1.5 hover:bg-muted" @click="closeUnitDetail">
                                <svg class="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <!-- Tuya power toggle -->
                        <div v-if="selectedTable.tuya_device_id" class="mt-3 flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <component :is="selectedTable.tuya_status ? Wifi : WifiOff" :class="['h-3.5 w-3.5', selectedTable.tuya_status ? 'text-emerald-500' : 'text-muted-foreground']" />
                                <span class="text-xs font-medium">{{ selectedTable.tuya_status ? 'Nyala' : 'Mati' }}</span>
                            </div>
                            <button
                                type="button"
                                :disabled="toggling[selectedTable.id]"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border-2 transition-all disabled:opacity-50"
                                :class="selectedTable.tuya_status ? 'border-emerald-400 bg-emerald-500/20 hover:bg-emerald-500/30' : 'border-slate-400 bg-muted hover:border-slate-300'"
                                @click="toggleTuya(selectedTable)"
                            >
                                <Power :class="['h-4 w-4 transition-colors', selectedTable.tuya_status ? 'text-emerald-500' : 'text-muted-foreground', toggling[selectedTable.id] ? 'animate-spin' : '']" />
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        <!-- IDLE state -->
                        <div v-if="getUnitState(selectedTable) === 'idle'" class="flex flex-col items-center gap-3 py-4 text-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-muted/50">
                                <Gamepad2 class="h-7 w-7 text-muted-foreground/50" />
                            </div>
                            <p class="text-sm font-medium">Unit Kosong</p>
                            <p class="text-xs text-muted-foreground">Belum ada sesi rental aktif</p>
                            <Button class="w-full gap-2" @click="openStartRental(selectedTable)">
                                <Play class="h-4 w-4" />
                                Mulai Rental
                            </Button>
                        </div>

                        <!-- Active / Expiring / Expired rental -->
                        <div v-else>
                            <div v-for="ord in [getActiveRental(selectedTable)].filter(Boolean) as TableOrder[]" :key="ord.id" class="space-y-3">
                                <!-- Timer display -->
                                <div :class="['rounded-xl p-4 text-center',
                                    getUnitState(selectedTable) === 'expiring' ? 'bg-amber-500/10 border border-amber-400/30' :
                                    getUnitState(selectedTable) === 'expired' ? 'bg-red-500/10 border border-red-500/30' :
                                    'bg-emerald-500/10 border border-emerald-400/30']">
                                    <p class="text-xs font-medium uppercase tracking-wider" :class="getUnitState(selectedTable) === 'expiring' ? 'text-amber-600' : getUnitState(selectedTable) === 'expired' ? 'text-red-500' : 'text-emerald-600'">
                                        <template v-if="getUnitState(selectedTable) === 'expired'">Waktu Habis</template>
                                        <template v-else-if="getUnitState(selectedTable) === 'expiring'">Segera Habis</template>
                                        <template v-else-if="ord.rental_end_at">Sisa Waktu</template>
                                        <template v-else>Waktu Berjalan</template>
                                    </p>
                                    <p class="mt-1 font-mono text-3xl font-black tracking-tight" :class="getUnitState(selectedTable) === 'expiring' ? 'text-amber-500' : getUnitState(selectedTable) === 'expired' ? 'text-red-500' : 'text-emerald-500'">
                                        {{ formatRemaining(ord) }}
                                    </p>
                                    <!-- Progress bar -->
                                    <div v-if="ord.rental_end_at" class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted/50">
                                        <div
                                            class="h-full rounded-full transition-all duration-1000"
                                            :class="getUnitState(selectedTable) === 'expiring' ? 'bg-amber-400' : getUnitState(selectedTable) === 'expired' ? 'bg-red-500' : 'bg-emerald-400'"
                                            :style="{ width: getRemainingPercent(ord) + '%' }"
                                        />
                                    </div>
                                    <p v-if="ord.rental_end_at" class="mt-1.5 text-[10px] text-muted-foreground">
                                        Berakhir: {{ new Date(ord.rental_end_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </p>
                                    <p v-else class="mt-1.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                        Billing Terbuka (Open Rent)
                                    </p>
                                </div>

                                <!-- Order info -->
                                <div class="rounded-lg border bg-muted/30 p-3 text-xs space-y-1">
                                    <div class="flex justify-between"><span class="text-muted-foreground">Kode Pesanan:</span><span class="font-mono font-bold">{{ ord.order_code }}</span></div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Durasi:</span>
                                        <span class="font-medium" v-if="ord.rental_end_at">{{ ord.rental_duration_minutes }}m</span>
                                        <span class="font-medium text-emerald-600" v-else>{{ getRunningDurationMinutes(ord) }}m (Berjalan)</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-muted-foreground">Total:</span>
                                        <span class="font-bold text-primary text-sm font-mono tracking-tighter">{{ formatCurrency(getRunningCost(ord, selectedTable.rental_price_per_hour)) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center"><span class="text-muted-foreground">Status:</span>
                                        <span :class="['rounded px-1.5 py-0.5 text-[10px] font-bold uppercase', ord.status === 'ready' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700']">
                                            {{ ord.status === 'ready' ? 'Selesai' : 'Aktif' }}
                                        </span>
                                    </div>

                                    <!-- Detail Billing breakdown -->
                                    <div v-if="(ord.items && ord.items.length > 0) || getRentalOnlyCost(ord, selectedTable.rental_price_per_hour) > 0" class="mt-3 pt-2 border-t border-dashed border-muted-foreground/20">
                                        <p class="text-[9px] font-black text-muted-foreground/60 uppercase mb-1.5 tracking-widest text-center">Rincian Tagihan</p>
                                        <div class="space-y-1 max-h-[120px] overflow-y-auto pr-1 scrollbar-thin">
                                            <div class="flex justify-between text-[11px] leading-tight">
                                                <span class="text-muted-foreground">Sewa PS ({{ ord.rental_end_at ? ord.rental_duration_minutes : getRunningDurationMinutes(ord) }}m)</span>
                                                <span class="font-medium font-mono">{{ formatCurrency(getRentalOnlyCost(ord, selectedTable.rental_price_per_hour)) }}</span>
                                            </div>
                                            <div v-for="(item, idx) in ord.items" :key="idx" class="flex justify-between text-[11px] leading-tight pt-0.5">
                                                <span class="text-muted-foreground truncate mr-2">{{ item.name }} <span class="font-bold">x{{ item.qty }}</span></span>
                                                <span class="font-medium font-mono shrink-0">{{ formatCurrency(item.total) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="space-y-2">
                                    <!-- When time is expired: show prominent new-session flow -->
                                    <div v-if="isExpired(ord) && ord.status !== 'ready'" class="rounded-xl border-2 border-red-400/40 bg-red-500/5 p-3 space-y-2">
                                        <p class="text-center text-xs font-semibold text-red-600">
                                            ⏰ Waktu habis — Selesaikan sesi ini
                                        </p>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            class="w-full gap-2"
                                            :disabled="stoppingRental[selectedTable.id]"
                                            @click="stopRental(selectedTable)"
                                        >
                                            <Square class="h-3.5 w-3.5" />
                                            {{ stoppingRental[selectedTable.id] ? 'Menghentikan...' : 'Stop & Selesaikan Sesi' }}
                                        </Button>
                                        <Button
                                            class="w-full gap-2 bg-emerald-600 hover:bg-emerald-700"
                                            size="sm"
                                            :disabled="stoppingRental[selectedTable.id]"
                                            @click="stopThenNewRental(selectedTable)"
                                        >
                                            <Play class="h-3.5 w-3.5" />
                                            Stop & Mulai Rental Baru
                                        </Button>
                                    </div>

                                    <!-- Normal active state actions -->
                                    <template v-else-if="ord.status !== 'ready'">
                                        <div class="grid grid-cols-2 gap-2">
                                            <Button
                                                v-if="ord.rental_end_at"
                                                variant="outline"
                                                size="sm"
                                                class="gap-1.5 border-blue-300 text-blue-700 hover:bg-blue-50"
                                                @click="openAddDuration(selectedTable)"
                                            >
                                                <Plus class="h-3.5 w-3.5" />
                                                Tambah Waktu
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="gap-1.5 border-violet-300 text-violet-700 hover:bg-violet-50"
                                                @click="openProductForTable(selectedTable)"
                                            >
                                                <ShoppingCart class="h-3.5 w-3.5" />
                                                Pesan Produk
                                            </Button>
                                        </div>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            class="w-full gap-2"
                                            :disabled="stoppingRental[selectedTable.id]"
                                            @click="stopRental(selectedTable)"
                                        >
                                            <Square class="h-3.5 w-3.5" />
                                            {{ stoppingRental[selectedTable.id] ? 'Menghentikan...' : 'Stop Rental' }}
                                        </Button>
                                    </template>

                                    <!-- Already stopped / ready -->
                                    <div v-if="ord.status === 'ready'" class="rounded-xl border p-3 space-y-2"
                                        :class="ord.payment_status === 'paid' ? 'border-emerald-400/30 bg-emerald-500/5' : 'border-amber-400/40 bg-amber-500/5'"
                                    >
                                        <!-- Belum dibayar -->
                                        <template v-if="ord.payment_status !== 'paid'">
                                            <p class="text-center text-xs font-medium text-amber-700">
                                                Sesi selesai — Menunggu pembayaran
                                            </p>
                                            <p class="text-center text-sm font-black text-amber-800">
                                                {{ formatCurrency(ord.final_amount) }}
                                            </p>
                                            <Button
                                                class="w-full gap-2"
                                                size="sm"
                                                @click="emit('pay-order', ord.id)"
                                            >
                                                <CreditCard class="h-3.5 w-3.5" />
                                                Bayar Sekarang
                                            </Button>
                                        </template>
                                        <!-- Sudah dibayar -->
                                        <template v-else>
                                            <p class="text-center text-xs font-medium text-emerald-700">
                                                    Sesi selesai — Unit siap digunakan
                                            </p>
                                            <Button
                                                class="w-full gap-2 bg-emerald-600 hover:bg-emerald-700"
                                                size="sm"
                                                @click="openStartRental(selectedTable)"
                                            >
                                                <Play class="h-3.5 w-3.5" />
                                                Mulai Rental Baru
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="w-full gap-1.5"
                                                @click="openProductForTable(selectedTable)"
                                            >
                                                <ShoppingCart class="h-3.5 w-3.5" />
                                                Pesan Produk
                                            </Button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer for idle: quick actions -->
                    <div v-if="getUnitState(selectedTable) === 'idle'" class="shrink-0 border-t p-3">
                        <Button variant="outline" size="sm" class="w-full gap-1.5" @click="openProductForTable(selectedTable)">
                            <ShoppingCart class="h-3.5 w-3.5" />
                            Pesan Produk (Tanpa Rental)
                        </Button>
                    </div>
                </div>
            </Transition>
        </div>
    </div>

    <!-- ─── Start Rental Dialog ──────────────────────────────────── -->
    <Dialog v-model:open="showStartRentalDialog">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Play class="h-4 w-4 text-primary" />
                    Mulai Rental — {{ selectedTable?.name }}
                </DialogTitle>
                <DialogDescription>Pilih paket atau atur durasi manual.</DialogDescription>
            </DialogHeader>

            <!-- Mode tabs -->
            <div class="flex gap-1 rounded-lg border bg-muted/30 p-1">
                <button
                    v-if="rentalPackages.length > 0"
                    type="button"
                    class="flex-1 rounded-md py-1.5 text-sm font-semibold transition-all"
                    :class="startMode === 'paket' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="startMode = 'paket'; selectedPackageId = null"
                >
                    Pilih Paket
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-md py-1.5 text-sm font-semibold transition-all"
                    :class="startMode === 'custom' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="startMode = 'custom'; selectedPackageId = null; startRentalForm.duration = 60"
                >
                    Durasi Custom
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-md py-1.5 text-sm font-semibold transition-all"
                    :class="startMode === 'open' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="startMode = 'open'; selectedPackageId = null; startRentalForm.duration = 0"
                >
                    Start Rent
                </button>
            </div>

            <!-- TAB: PAKET -->
            <div v-if="startMode === 'paket'" class="max-h-[55vh] overflow-y-auto space-y-2 pr-1">
                <div v-if="rentalPackages.length === 0" class="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground">
                    <Gamepad2 class="h-10 w-10 opacity-30" />
                    <p class="text-sm">Belum ada paket rental.</p>
                    <p class="text-xs">Tambahkan produk bertipe <strong>Paket Rental</strong> di menu Produk.</p>
                </div>
                <button
                    v-for="pkg in rentalPackages"
                    :key="pkg.id"
                    type="button"
                    class="w-full rounded-xl border-2 p-4 text-left transition-all"
                    :class="selectedPackageId === pkg.id
                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                        : 'border-input hover:border-primary/40 hover:bg-accent'"
                    @click="selectPackage(pkg)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-sm">{{ pkg.name }}</p>
                                <span v-if="pkg.discount_percent > 0" class="rounded bg-destructive/10 px-1.5 py-0.5 text-[10px] font-bold text-destructive">-{{ pkg.discount_percent }}%</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                ⏱ {{ pkg.rental_duration_minutes! >= 60
                                    ? Math.floor(pkg.rental_duration_minutes! / 60) + ' Jam' + (pkg.rental_duration_minutes! % 60 > 0 ? ' ' + pkg.rental_duration_minutes! % 60 + ' Menit' : '')
                                    : pkg.rental_duration_minutes + ' Menit' }}
                            </p>
                            <!-- Included items -->
                            <div v-if="pkg.included_items_json && pkg.included_items_json.length > 0" class="mt-2 flex flex-wrap gap-1.5">
                                <span class="text-[10px] text-muted-foreground font-medium">Termasuk:</span>
                                <template v-for="item in pkg.included_items_json" :key="item.product_id">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700">
                                        {{ item.qty > 1 ? item.qty + 'x ' : '' }}{{ item.product_name }}
                                    </span>
                                </template>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <p v-if="pkg.discount_percent > 0" class="text-xs text-muted-foreground line-through">{{ formatCurrency(pkg.sell_price) }}</p>
                            <p class="text-base font-black text-primary">
                                {{ formatCurrency(pkg.sell_price - Math.round(pkg.sell_price * (pkg.discount_percent / 100))) }}
                            </p>
                        </div>
                    </div>
                    <!-- Selected checkmark -->
                    <div v-if="selectedPackageId === pkg.id" class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-primary">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        Paket ini dipilih — Durasi {{ startRentalForm.duration }} menit
                    </div>
                </button>
            </div>

            <!-- TAB: CUSTOM -->
            <div v-if="startMode === 'custom'" class="space-y-4 py-1">
                <div>
                    <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pilih Durasi</Label>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <button
                            v-for="d in [60, 120, 180, 300, 480]"
                            :key="d"
                            type="button"
                            class="rounded-lg border py-2.5 text-sm font-semibold transition-all"
                            :class="startRentalForm.duration === d ? 'border-primary bg-primary text-primary-foreground' : 'border-input hover:border-primary/50 hover:bg-accent'"
                            @click="startRentalForm.duration = d"
                        >
                            {{ d >= 60 ? Math.floor(d / 60) + 'j' + (d % 60 > 0 ? ' ' + d % 60 + 'm' : '') : d + 'm' }}
                        </button>
                    </div>
                </div>
                <div v-if="selectedTable" class="rounded-xl border bg-muted/50 p-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Harga per Jam</span>
                        <span class="font-semibold">{{ formatCurrency(selectedTable.rental_price_per_hour) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Durasi</span>
                        <span class="font-semibold">{{ startRentalForm.duration }} menit</span>
                    </div>
                    <div class="flex justify-between border-t pt-2 text-base font-bold text-primary">
                        <span>Estimasi Biaya</span>
                        <span>{{ formatCurrency((selectedTable.rental_price_per_hour / 60) * startRentalForm.duration) }}</span>
                    </div>
                </div>
            </div>

            <!-- TAB: OPEN -->
            <div v-if="startMode === 'open'" class="flex flex-col items-center py-6 text-center">
                <Timer class="h-12 w-12 text-primary mb-3" />
                <h3 class="text-lg font-bold">Start Rent (Billing Berjalan)</h3>
                <p class="text-sm text-muted-foreground mt-2 px-6">
                    Waktu rental akan dimulai dari 00:00:00, dan biaya dihitung <strong>{{ formatCurrency(selectedTable?.rental_price_per_hour ?? 0) }}/jam</strong> saat Anda menekan Stop Rental.
                </p>
                <div class="mt-4 rounded border bg-emerald-500/10 px-4 py-2 flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                    <Play class="h-4 w-4" /> Siap untuk memulai sesi.
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showStartRentalDialog = false">Batal</Button>
                <Button
                    class="gap-2"
                    :disabled="startingRental[selectedTable?.id ?? 0] || (startMode === 'paket' && !selectedPackageId)"
                    @click="submitStartRental"
                >
                    <Play class="h-4 w-4" />
                    {{ startingRental[selectedTable?.id ?? 0] ? 'Memulai...' : 'Mulai Sekarang' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ─── Add Duration Dialog ──────────────────────────────────── -->
    <Dialog v-model:open="showAddDurationDialog">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Timer class="h-4 w-4 text-blue-500" />
                    Tambah Durasi — {{ selectedTable?.name }}
                </DialogTitle>
                <DialogDescription>Perpanjang waktu rental unit ini.</DialogDescription>
            </DialogHeader>
            <div class="space-y-4 py-2">
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="d in [15, 30, 45, 60, 90, 120]"
                        :key="d"
                        type="button"
                        class="rounded-lg border py-2.5 text-sm font-semibold transition-all"
                        :class="addDurationForm.minutes === d ? 'border-blue-500 bg-blue-500 text-white' : 'border-input hover:border-blue-300 hover:bg-blue-50'"
                        @click="addDurationForm.minutes = d"
                    >
                        {{ d >= 60 ? Math.floor(d / 60) + 'j' + (d % 60 > 0 ? ' ' + d % 60 + 'm' : '') : d + 'm' }}
                    </button>
                </div>
                <div v-if="selectedTable" class="rounded-xl border bg-muted/50 p-3 text-sm">
                    <div class="flex justify-between font-bold text-blue-600">
                        <span>Biaya Tambahan</span>
                        <span>{{ formatCurrency((selectedTable.rental_price_per_hour / 60) * addDurationForm.minutes) }}</span>
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showAddDurationDialog = false">Batal</Button>
                <Button class="gap-2 bg-blue-600 hover:bg-blue-700" @click="submitAddDuration">
                    <Plus class="h-4 w-4" />
                    Tambah Durasi
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ─── Quick Product Order Dialog ──────────────────────────── -->
    <Dialog v-model:open="showProductDialog">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ShoppingCart class="h-4 w-4" />
                    Pesan Produk — {{ selectedTable?.name }}
                </DialogTitle>
                <DialogDescription>Pilih produk untuk ditambah ke keranjang unit ini.</DialogDescription>
            </DialogHeader>
            <div class="max-h-[60vh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-2 p-1">
                    <button
                        v-for="p in products.filter(p => !p.is_rental_package)"
                        :key="p.id"
                        type="button"
                        class="flex flex-col gap-1 rounded-xl border p-3 text-left transition-all hover:border-primary/50 hover:bg-accent disabled:opacity-40"
                        :disabled="p.track_stock && p.current_stock <= 0"
                        @click="addProductToCart(p)"
                    >
                        <div class="flex items-start justify-between gap-1">
                            <p class="text-sm font-semibold leading-tight">{{ p.name }}</p>
                            <span v-if="p.category_name" class="shrink-0 rounded px-1 py-0.5 text-[8px] font-bold uppercase" :style="{ backgroundColor: (p.category_color || '#e2e8f0') + '30', color: p.category_color || 'currentColor' }">{{ p.category_name }}</span>
                        </div>
                        <p class="text-xs font-bold text-primary">{{ formatCurrency(p.sell_price) }}</p>
                        <p v-if="p.track_stock" class="text-[10px] text-muted-foreground">Stok: {{ p.current_stock }}</p>
                    </button>
                </div>
            </div>
            <DialogFooter class="flex-col gap-2 sm:flex-row">
                <Button variant="outline" @click="showProductDialog = false">Tutup</Button>
                <Button
                    v-if="props.cart.length > 0"
                    class="gap-2"
                    @click="showProductDialog = false; emit('checkout')"
                >
                    <ShoppingCart class="h-4 w-4" />
                    Bayar ({{ props.cart.length }} item)
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.slide-right-enter-active,
.slide-right-leave-active {
    transition: all 0.2s ease;
}
.slide-right-enter-from,
.slide-right-leave-to {
    opacity: 0;
    transform: translateX(20px);
}

.hover\:scale-102:hover {
    transform: scale(1.02);
}
</style>
