<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useElementSize } from '@vueuse/core'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import AlertDialog from '@/components/AlertDialog.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    ArrowLeft, Save, Plus, LayoutGrid, Box, ArrowDownToLine, CreditCard,
    DoorOpen, Square, MoreHorizontal, Trash2, QrCode, Copy, Pencil,
    ZoomIn, ZoomOut, Maximize2, RotateCw, RotateCcw, Printer,
} from 'lucide-vue-next'

const BASE_PIXELS_PER_METER = 80
const SNAP_GRID = 0.25
const SNAP_THRESHOLD = 0.15

interface Table {
    id: number
    name: string
    qr_code: string
    qr_url: string
    capacity: number
    status: string
    x_meters: number
    y_meters: number
    width_meters: number
    length_meters: number
    rotation_deg: number
    shape: string
}

interface Element {
    id: number
    type: string
    name: string
    x_meters: number
    y_meters: number
    width_meters: number
    length_meters: number
    rotation_deg: number
    meta: Record<string, unknown>
}

const props = defineProps<{
    store: { id: number; name: string; slug: string }
    floor: { id: number; name: string; width_meters: number; length_meters: number }
    tables: Table[]
    elements: Element[]
    element_types: Record<string, string>
    next_table_name?: string
}>()

const tables = ref<Table[]>([...props.tables])
const elements = ref<Element[]>([...props.elements])

watch(() => [props.tables, props.elements], ([newTables, newElements]) => {
    tables.value = [...(newTables || [])]
    elements.value = [...(newElements || [])]
}, { deep: true })

const floorW = props.floor?.width_meters ?? 10
const floorH = props.floor?.length_meters ?? 15
const floorWidthPx = Math.max(400, floorW * BASE_PIXELS_PER_METER)
const floorHeightPx = Math.max(300, floorH * BASE_PIXELS_PER_METER)

const viewportRef = ref<HTMLElement | null>(null)
const { width: viewportW, height: viewportH } = useElementSize(viewportRef)

const fitScale = computed(() => {
    const w = viewportW.value || 800
    const h = viewportH.value || 500
    const fitW = rotationDeg.value === 90 || rotationDeg.value === 270 ? floorHeightPx : floorWidthPx
    const fitH = rotationDeg.value === 90 || rotationDeg.value === 270 ? floorWidthPx : floorHeightPx
    const sx = w / fitW
    const sy = h / fitH
    return Math.min(1, sx, sy)
})

const userZoom = ref(1)
const ZOOM_MIN = 0.5
const ZOOM_MAX = 2
const ZOOM_STEP = 0.25

const scale = computed(() => fitScale.value * userZoom.value)

const scaledWidthPx = computed(() => Math.round(floorWidthPx * scale.value))
const scaledHeightPx = computed(() => Math.round(floorHeightPx * scale.value))

const rotationDeg = ref(0)

const wrapperWidthPx = computed(() => {
    if (rotationDeg.value === 0 || rotationDeg.value === 180) return scaledWidthPx.value
    return scaledHeightPx.value
})
const wrapperHeightPx = computed(() => {
    if (rotationDeg.value === 0 || rotationDeg.value === 180) return scaledHeightPx.value
    return scaledWidthPx.value
})
const innerFloorOffset = computed(() => {
    if (rotationDeg.value === 0) return { left: 0, top: 0 }
    return {
        left: (wrapperWidthPx.value - floorWidthPx) / 2,
        top: (wrapperHeightPx.value - floorHeightPx) / 2,
    }
})

function rotateCw() {
    rotationDeg.value = (rotationDeg.value + 90) % 360
}
function rotateCcw() {
    rotationDeg.value = (rotationDeg.value - 90 + 360) % 360
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

function onWheel(e: WheelEvent) {
    if (!e.ctrlKey && !e.metaKey) return
    e.preventDefault()
    if (e.deltaY < 0) zoomIn()
    else zoomOut()
}

function onContextMenuClickOutside() {
    closeContextMenu()
}

onMounted(() => {
    const el = viewportRef.value
    if (el) el.addEventListener('wheel', onWheel, { passive: false })
    document.addEventListener('click', onContextMenuClickOutside)
    document.addEventListener('contextmenu', onContextMenuClickOutside)
})
onUnmounted(() => {
    const el = viewportRef.value
    if (el) el.removeEventListener('wheel', onWheel)
    document.removeEventListener('click', onContextMenuClickOutside)
    document.removeEventListener('contextmenu', onContextMenuClickOutside)
})

const autoRapi = ref(true)
const showAddTable = ref(false)
const showAddElement = ref(false)
const showEditTable = ref(false)
const showQrDialog = ref(false)
const selectedTable = ref<Table | null>(null)
const editTableTarget = ref<Table | null>(null)
const processing = ref(false)
const guideLines = ref<{ type: 'h' | 'v'; pos: number }[]>([])

const contextMenu = ref<{ type: 'table' | 'element'; item: Table | Element; x: number; y: number } | null>(null)

function openContextMenu(e: MouseEvent, type: 'table' | 'element', item: Table | Element) {
    e.preventDefault()
    e.stopPropagation()
    contextMenu.value = { type, item, x: e.clientX, y: e.clientY }
}

function closeContextMenu() {
    contextMenu.value = null
}

const confirmState = ref<{
    open: boolean
    title: string
    description: string
    variant: 'default' | 'destructive'
    confirmLabel?: string
    onConfirm: () => void
}>({ open: false, title: '', description: '', variant: 'destructive', onConfirm: () => {} })
const alertState = ref<{ open: boolean; message: string }>({ open: false, message: '' })

function showConfirm(config: typeof confirmState.value) {
    confirmState.value = { ...config, open: true }
}

function showAlert(message: string) {
    alertState.value = { open: true, message }
}

function removeTable(t: Table) {
    closeContextMenu()
    showConfirm({
        title: 'Hapus Meja',
        description: `Hapus meja "${t.name}" dari denah?`,
        variant: 'destructive',
        confirmLabel: 'Ya, Hapus',
        onConfirm: () => {
            void Promise.resolve(saveLayout()).then(() => {
                router.delete(`/admin/stores/${props.store.id}/floor-plan/${props.floor.id}/tables/${t.id}`, {
                    preserveScroll: true,
                    onSuccess: () => router.reload(),
                })
            })
        },
    })
}

function removeElement(el: Element) {
    closeContextMenu()
    showConfirm({
        title: 'Hapus Elemen',
        description: 'Hapus elemen ini dari denah?',
        variant: 'destructive',
        confirmLabel: 'Ya, Hapus',
        onConfirm: () => {
            void Promise.resolve(saveLayout()).then(() => {
                router.delete(`/admin/stores/${props.store.id}/floor-plan/${props.floor.id}/elements/${el.id}`, {
                    preserveScroll: true,
                    onSuccess: () => router.reload(),
                })
            })
        },
    })
}

const addTableForm = ref({ name: '', capacity: 4 })

watch(showAddTable, (open) => {
    if (open) {
        addTableForm.value = { name: props.next_table_name ?? '1', capacity: 4 }
    }
})
const addElementForm = ref({ type: 'pillar' as string, name: '', width_meters: 0.5, length_meters: 0.5 })
const editTableForm = ref({ width_meters: 0.8, length_meters: 1.2 })

const elementIcons: Record<string, unknown> = {
    pillar: Box,
    stairs: ArrowDownToLine,
    cashier: CreditCard,
    wall: Square,
    door: DoorOpen,
    counter: Square,
    other: MoreHorizontal,
}

let dragTarget: { type: 'table' | 'element'; id: number; startX: number; startY: number; origX: number; origY: number } | null = null

function toPx(meters: number) {
    return meters * BASE_PIXELS_PER_METER
}

function snapValue(v: number): number {
    if (!autoRapi.value) return v
    return Math.round(v / SNAP_GRID) * SNAP_GRID
}

function getSnapPositions(excludeId: number, excludeType: 'table' | 'element') {
    const positions: { x: number[]; y: number[] } = { x: [0, floorW], y: [0, floorH] }
    tables.value.forEach((t) => {
        if (excludeType === 'table' && t.id === excludeId) return
        positions.x.push(t.x_meters, t.x_meters + t.width_meters)
        positions.y.push(t.y_meters, t.y_meters + t.length_meters)
    })
    elements.value.forEach((e) => {
        if (excludeType === 'element' && e.id === excludeId) return
        positions.x.push(e.x_meters, e.x_meters + e.width_meters)
        positions.y.push(e.y_meters, e.y_meters + e.length_meters)
    })
    return positions
}

function applySnap(x: number, y: number, w: number, h: number, excludeId: number, excludeType: 'table' | 'element') {
    if (!autoRapi.value) return { x: Math.max(0, x), y: Math.max(0, y), guides: [] as { type: 'h' | 'v'; pos: number }[] }
    const positions = getSnapPositions(excludeId, excludeType)
    const guides: { type: 'h' | 'v'; pos: number }[] = []
    let outX = snapValue(x)
    let outY = snapValue(y)

    for (const px of positions.x) {
        if (Math.abs(x - px) < SNAP_THRESHOLD) {
            outX = px
            guides.push({ type: 'v', pos: px })
            break
        }
        if (Math.abs((x + w) - px) < SNAP_THRESHOLD) {
            outX = px - w
            guides.push({ type: 'v', pos: px })
            break
        }
    }
    for (const py of positions.y) {
        if (Math.abs(y - py) < SNAP_THRESHOLD) {
            outY = py
            guides.push({ type: 'h', pos: py })
            break
        }
        if (Math.abs((y + h) - py) < SNAP_THRESHOLD) {
            outY = py - h
            guides.push({ type: 'h', pos: py })
            break
        }
    }
    if (guides.length === 0) {
        outX = snapValue(x)
        outY = snapValue(y)
    }
    return {
        x: Math.max(0, Math.min(floorW - w, outX)),
        y: Math.max(0, Math.min(floorH - h, outY)),
        guides,
    }
}

function startDrag(type: 'table' | 'element', id: number, e: MouseEvent) {
    const item = type === 'table'
        ? tables.value.find((t) => t.id === id)
        : elements.value.find((el) => el.id === id)
    if (!item) return
    const x = type === 'table' ? (item as Table).x_meters : (item as Element).x_meters
    const y = type === 'table' ? (item as Table).y_meters : (item as Element).y_meters
    const w = type === 'table' ? (item as Table).width_meters : (item as Element).width_meters
    const h = type === 'table' ? (item as Table).length_meters : (item as Element).length_meters
    dragTarget = { type, id, startX: e.clientX, startY: e.clientY, origX: x, origY: y }
    document.addEventListener('mousemove', onDrag)
    document.addEventListener('mouseup', stopDrag)
}

function onDrag(e: MouseEvent) {
    if (!dragTarget) return
    const screenDx = e.clientX - dragTarget.startX
    const screenDy = e.clientY - dragTarget.startY
    const rad = (rotationDeg.value * Math.PI) / 180
    const cos = Math.cos(rad)
    const sin = Math.sin(rad)
    const floorDxPx = screenDx * cos + screenDy * sin
    const floorDyPx = -screenDx * sin + screenDy * cos
    const pxPerMeter = BASE_PIXELS_PER_METER * scale.value
    const dx = floorDxPx / pxPerMeter
    const dy = floorDyPx / pxPerMeter
    const rawX = dragTarget.origX + dx
    const rawY = dragTarget.origY + dy

    if (dragTarget.type === 'table') {
        const t = tables.value.find((x) => x.id === dragTarget!.id)
        if (t) {
            const { x, y, guides } = applySnap(rawX, rawY, t.width_meters, t.length_meters, t.id, 'table')
            t.x_meters = x
            t.y_meters = y
            guideLines.value = guides
        }
    } else {
        const el = elements.value.find((x) => x.id === dragTarget!.id)
        if (el) {
            const { x, y, guides } = applySnap(rawX, rawY, el.width_meters, el.length_meters, el.id, 'element')
            el.x_meters = x
            el.y_meters = y
            guideLines.value = guides
        }
    }
}

function stopDrag() {
    if (dragTarget) {
        saveLayout()
    }
    dragTarget = null
    guideLines.value = []
    document.removeEventListener('mousemove', onDrag)
    document.removeEventListener('mouseup', stopDrag)
}

function saveLayout() {
    processing.value = true
    return router.post(
        `/admin/stores/${props.store.id}/floor-plan/${props.floor.id}/save-layout`,
        {
            tables: tables.value.map((t) => ({
                id: t.id,
                x_meters: t.x_meters,
                y_meters: t.y_meters,
                width_meters: t.width_meters,
                length_meters: t.length_meters,
                rotation_deg: t.rotation_deg,
            })),
            elements: elements.value.map((e) => ({
                id: e.id,
                type: e.type,
                name: e.name,
                x_meters: e.x_meters,
                y_meters: e.y_meters,
                width_meters: e.width_meters,
                length_meters: e.length_meters,
                rotation_deg: e.rotation_deg,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => { processing.value = false },
            onError: () => { processing.value = false },
        },
    )
}

function submitSave() {
    saveLayout()
}

function submitAddTable() {
    router.post(
        `/admin/stores/${props.store.id}/floor-plan/${props.floor.id}/tables`,
        addTableForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showAddTable.value = false
                addTableForm.value = { name: '', capacity: 4 }
                router.reload()
            },
            onError: (errors) => showAlert('Gagal: ' + Object.values(errors).join(', ')),
        },
    )
}

function submitAddElement() {
    router.post(
        `/admin/stores/${props.store.id}/floor-plan/${props.floor.id}/elements`,
        addElementForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showAddElement.value = false
                addElementForm.value = { type: 'pillar', name: '', width_meters: 0.5, length_meters: 0.5 }
                router.reload()
            },
            onError: () => showAlert('Gagal menambah elemen'),
        },
    )
}

function openEditTable(t: Table) {
    editTableTarget.value = t
    editTableForm.value = { width_meters: t.width_meters, length_meters: t.length_meters }
    showEditTable.value = true
}

function submitEditTable() {
    const t = editTableTarget.value
    if (!t) return
    const idx = tables.value.findIndex((x) => x.id === t.id)
    if (idx >= 0) {
        tables.value[idx].width_meters = editTableForm.value.width_meters
        tables.value[idx].length_meters = editTableForm.value.length_meters
        saveLayout()
    }
    showEditTable.value = false
    editTableTarget.value = null
}

function copyTable(t: Table) {
    void Promise.resolve(saveLayout()).then(() => {
        router.post(`/admin/stores/${props.store.id}/floor-plan/${props.floor.id}/tables/${t.id}/copy`, {}, {
            preserveScroll: true,
            onSuccess: () => router.reload(),
        })
    })
}

function copyElement(el: Element) {
    void Promise.resolve(saveLayout()).then(() => {
        router.post(`/admin/stores/${props.store.id}/floor-plan/${props.floor.id}/elements/${el.id}/copy`, {}, {
            preserveScroll: true,
            onSuccess: () => router.reload(),
        })
    })
}

function openQr(table: Table) {
    selectedTable.value = table
    showQrDialog.value = true
}

function qrImageUrl(url: string) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}`
}
</script>

<template>
    <AdminLayout :title="`Denah ${floor?.name ?? 'Lantai'} — ${store?.name ?? ''}`">
        <!-- Context menu: klik kanan pada meja/elemen -->
        <Teleport to="body">
            <div
                v-if="contextMenu"
                class="fixed z-[100] min-w-[10rem] rounded-lg border bg-popover py-1 shadow-lg"
                :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
                @click.stop
            >
                <template v-if="contextMenu.type === 'table'">
                    <button
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                        @click="openEditTable(contextMenu.item as Table); closeContextMenu()"
                    >
                        <Pencil class="h-4 w-4" />
                        Edit ukuran
                    </button>
                    <button
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                        @click="openQr(contextMenu.item as Table); closeContextMenu()"
                    >
                        <QrCode class="h-4 w-4" />
                        QR Code
                    </button>
                    <button
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                        @click="copyTable(contextMenu.item as Table); closeContextMenu()"
                    >
                        <Copy class="h-4 w-4" />
                        Salin
                    </button>
                    <button
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-destructive hover:bg-accent"
                        @click="removeTable(contextMenu.item as Table)"
                    >
                        <Trash2 class="h-4 w-4" />
                        Hapus
                    </button>
                </template>
                <template v-else>
                    <button
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                        @click="copyElement(contextMenu.item as Element); closeContextMenu()"
                    >
                        <Copy class="h-4 w-4" />
                        Salin
                    </button>
                    <button
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-destructive hover:bg-accent"
                        @click="removeElement(contextMenu.item as Element)"
                    >
                        <Trash2 class="h-4 w-4" />
                        Hapus
                    </button>
                </template>
            </div>
        </Teleport>

        <template #headerActions>
            <Link :href="`/admin/stores/${store.id}/floor-plan`">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
            </Link>
        </template>

        <!-- Toolbar -->
        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border bg-card p-3">
            <!-- Zoom -->
            <div class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                <button
                    type="button"
                    class="rounded p-1.5 transition-colors hover:bg-accent disabled:opacity-40"
                    :disabled="userZoom <= ZOOM_MIN"
                    @click="zoomOut"
                    title="Zoom out"
                >
                    <ZoomOut class="h-4 w-4" />
                </button>
                <span class="min-w-[4rem] text-center text-sm font-medium">{{ Math.round(scale * 100) }}%</span>
                <button
                    type="button"
                    class="rounded p-1.5 transition-colors hover:bg-accent disabled:opacity-40"
                    :disabled="userZoom >= ZOOM_MAX"
                    @click="zoomIn"
                    title="Zoom in"
                >
                    <ZoomIn class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="rounded p-1.5 transition-colors hover:bg-accent"
                    @click="zoomFit"
                    title="Fit ke layar"
                >
                    <Maximize2 class="h-4 w-4" />
                </button>
            </div>
            <div class="mx-2 h-4 w-px bg-border" />
            <!-- Rotate -->
            <div class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                <button
                    type="button"
                    class="rounded p-1.5 transition-colors hover:bg-accent"
                    @click="rotateCcw"
                    title="Putar kiri"
                >
                    <RotateCcw class="h-4 w-4" />
                </button>
                <span class="min-w-[3rem] text-center text-xs text-muted-foreground">{{ rotationDeg }}°</span>
                <button
                    type="button"
                    class="rounded p-1.5 transition-colors hover:bg-accent"
                    @click="rotateCw"
                    title="Putar kanan"
                >
                    <RotateCw class="h-4 w-4" />
                </button>
            </div>
            <div class="mx-2 h-4 w-px bg-border" />
            <label class="flex cursor-pointer items-center gap-2">
                <Checkbox v-model:checked="autoRapi" />
                <span class="text-sm font-medium">Auto Rapi</span>
            </label>
            <span class="text-xs text-muted-foreground">(snap ke grid & align)</span>
            <div class="mx-2 h-4 w-px bg-border" />
            <Button variant="outline" size="sm" @click="showAddTable = true">
                <LayoutGrid class="h-4 w-4" />
                Tambah Meja
            </Button>
            <Button variant="outline" size="sm" @click="showAddElement = true">
                <Plus class="h-4 w-4" />
                Tambah Elemen
            </Button>
            <a
                v-if="tables.length > 0"
                :href="`/admin/stores/${store.id}/floor-plan/${floor.id}/print-qr`"
                target="_blank"
                rel="noopener"
            >
                <Button variant="outline" size="sm">
                    <Printer class="h-4 w-4" />
                    Cetak QR Meja
                </Button>
            </a>
            <Button class="ml-auto" :disabled="processing" @click="submitSave">
                <Save class="h-4 w-4" />
                Simpan Denah
            </Button>
        </div>

        <!-- Floor Plan Canvas (Ctrl+scroll untuk zoom) -->
        <div
            ref="viewportRef"
            class="flex items-center justify-center rounded-2xl border-2 border-dashed border-muted-foreground/30 bg-white dark:bg-zinc-950"
            :class="scale > fitScale ? 'overflow-auto' : 'overflow-hidden'"
            style="height: calc(100vh - 220px); min-height: 400px;"
        >
            <!-- Wrapper: putih, hanya inner floor (grid) yang gelap -->
            <div
                class="relative m-auto shrink-0 bg-white dark:bg-zinc-950 shadow-inner"
                :style="{
                    width: wrapperWidthPx + 'px',
                    height: wrapperHeightPx + 'px',
                    minWidth: wrapperWidthPx + 'px',
                    minHeight: wrapperHeightPx + 'px',
                }"
            >
                <!-- Inner floor: hijau papan arsitektur -->
                <div
                    class="absolute rounded-lg bg-teal-950/90"
                    :style="{
                        left: innerFloorOffset.left + 'px',
                        top: innerFloorOffset.top + 'px',
                        width: floorWidthPx + 'px',
                        height: floorHeightPx + 'px',
                        transform: `scale(${scale})${rotationDeg ? ` rotate(${rotationDeg}deg)` : ''}`,
                        transformOrigin: rotationDeg ? 'center center' : 'top left',
                    }"
                >
                <!-- Alignment guides -->
                <template v-for="g in guideLines" :key="`${g.type}-${g.pos}`">
                    <div
                        v-if="g.type === 'v'"
                        class="pointer-events-none absolute top-0 bottom-0 w-0.5 bg-primary z-50"
                        :style="{ left: toPx(g.pos) + 'px' }"
                    />
                    <div
                        v-else
                        class="pointer-events-none absolute left-0 right-0 h-0.5 bg-primary z-50"
                        :style="{ top: toPx(g.pos) + 'px' }"
                    />
                </template>

                <!-- Empty state -->
                <div
                    v-if="tables.length === 0 && elements.length === 0"
                    class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center text-muted-foreground"
                    :style="{
                        transform: rotationDeg ? `rotate(${-rotationDeg}deg)` : undefined,
                        transformOrigin: 'center center',
                    }"
                >
                    <LayoutGrid class="h-12 w-12 opacity-30" />
                    <p class="text-sm font-medium">Denah kosong</p>
                    <p class="text-xs">Klik <strong>Tambah Meja</strong> atau <strong>Tambah Elemen</strong> untuk mulai</p>
                </div>

                <!-- Grid lines: putih saja -->
                <template v-for="i in Math.floor(floorW)" :key="'v-' + i">
                    <div
                        v-if="i < floorW"
                        class="absolute top-0 bottom-0 w-px bg-white"
                        :style="{ left: toPx(i) + 'px' }"
                    />
                </template>
                <template v-for="i in Math.floor(floorH)" :key="'h-' + i">
                    <div
                        v-if="i < floorH"
                        class="absolute left-0 right-0 h-px bg-white"
                        :style="{ top: toPx(i) + 'px' }"
                    />
                </template>

                <!-- Elements -->
                <div
                    v-for="el in elements"
                    :key="'el-' + el.id"
                    class="group absolute cursor-move select-none rounded-md border-2 border-amber-400 bg-amber-50 text-xs font-medium text-amber-900"
                    :style="{
                        left: toPx(el.x_meters) + 'px',
                        top: toPx(el.y_meters) + 'px',
                        width: toPx(el.width_meters) + 'px',
                        height: toPx(el.length_meters) + 'px',
                    }"
                    @mousedown="startDrag('element', el.id, $event)"
                    @contextmenu.prevent="openContextMenu($event, 'element', el)"
                >
                    <div
                        class="flex h-full w-full items-center justify-center gap-1 px-1 py-0.5"
                        :style="{
                            transform: rotationDeg ? `rotate(${-rotationDeg}deg)` : undefined,
                            transformOrigin: 'center center',
                        }"
                    >
                        <component :is="elementIcons[el.type] || MoreHorizontal" class="h-3 w-3 shrink-0" />
                        <span>{{ element_types[el.type] || el.type }}</span>
                        <button
                            class="ml-0.5 shrink-0 rounded p-0.5 opacity-0 group-hover:opacity-100 hover:bg-amber-200 text-amber-900"
                            title="Copy"
                            @click.stop="copyElement(el)"
                        >
                            <Copy class="h-3 w-3" />
                        </button>
                    </div>
                </div>

                <!-- Tables -->
                <div
                    v-for="t in tables"
                    :key="'t-' + t.id"
                    class="group absolute cursor-move select-none rounded-lg border-2 border-amber-400 bg-amber-50 text-center shadow-sm"
                    :style="{
                        left: toPx(t.x_meters) + 'px',
                        top: toPx(t.y_meters) + 'px',
                        width: toPx(t.width_meters) + 'px',
                        height: toPx(t.length_meters) + 'px',
                    }"
                    @mousedown="startDrag('table', t.id, $event)"
                    @contextmenu.prevent="openContextMenu($event, 'table', t)"
                >
                    <div
                        class="flex h-full w-full flex-col items-center justify-center gap-0.5 px-2 py-1.5"
                        :style="{
                            transform: rotationDeg ? `rotate(${-rotationDeg}deg)` : undefined,
                            transformOrigin: 'center center',
                        }"
                    >
                        <span class="font-semibold text-amber-900">{{ t.name }}</span>
                        <span class="text-xs text-amber-700">{{ t.capacity }} kursi</span>
                        <div class="mt-1 flex gap-0.5">
                            <button
                                class="rounded bg-amber-200/80 p-1 text-amber-900 hover:bg-amber-300"
                                title="Edit ukuran"
                                @click.stop="openEditTable(t)"
                            >
                                <Pencil class="h-3 w-3" />
                            </button>
                            <button
                                class="rounded bg-amber-200/80 p-1 text-amber-900 hover:bg-amber-300"
                                title="Lihat QR"
                                @click.stop="openQr(t)"
                            >
                                <QrCode class="h-3 w-3" />
                            </button>
                            <button
                                class="rounded bg-amber-200/80 p-1 text-amber-900 hover:bg-amber-300"
                                title="Copy"
                                @click.stop="copyTable(t)"
                            >
                                <Copy class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-4 flex flex-wrap items-center gap-6 rounded-lg border bg-muted/20 p-4 text-sm">
            <div class="flex items-center gap-2">
                <div class="h-6 w-6 rounded border-2 border-amber-400 bg-amber-50" />
                <span>Meja: drag pindah, klik kanan → Edit, QR, Salin, Hapus</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-6 w-6 rounded border-2 border-amber-400 bg-amber-50" />
                <span>Elemen: drag pindah, klik kanan → Salin, Hapus</span>
            </div>
            <div class="ml-auto text-muted-foreground">
                {{ floor?.width_meters ?? 0 }}m × {{ floor?.length_meters ?? 0 }}m
            </div>
        </div>

        <!-- Add Table Dialog -->
        <Dialog :open="showAddTable" @update:open="showAddTable = $event">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Tambah Meja</DialogTitle>
                    <DialogDescription>Meja punya QR untuk order. Ukuran bisa diatur setelah dibuat.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label>Nama Meja</Label>
                        <Input v-model="addTableForm.name" placeholder="1" class="mt-1.5" />
                    </div>
                    <div>
                        <Label>Kapasitas (kursi)</Label>
                        <Input v-model.number="addTableForm.capacity" type="number" min="1" max="50" class="mt-1.5" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddTable = false">Batal</Button>
                    <Button @click="submitAddTable" :disabled="!addTableForm.name">Tambah</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Table Size Dialog -->
        <Dialog :open="showEditTable" @update:open="showEditTable = $event">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Edit Ukuran Meja — {{ editTableTarget?.name }}</DialogTitle>
                    <DialogDescription>Atur lebar dan panjang meja dalam meter.</DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Lebar (m)</Label>
                        <Input v-model.number="editTableForm.width_meters" type="number" min="0.3" step="0.1" class="mt-1.5" />
                    </div>
                    <div>
                        <Label>Panjang (m)</Label>
                        <Input v-model.number="editTableForm.length_meters" type="number" min="0.3" step="0.1" class="mt-1.5" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showEditTable = false">Batal</Button>
                    <Button @click="submitEditTable">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Element Dialog -->
        <Dialog :open="showAddElement" @update:open="showAddElement = $event">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Tambah Elemen Denah</DialogTitle>
                    <DialogDescription>Tiang, tangga, kasir, dll. Ukuran dalam meter.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label>Jenis</Label>
                        <select v-model="addElementForm.type" class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm">
                            <option v-for="(label, key) in element_types" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <Label>Nama (opsional)</Label>
                        <Input v-model="addElementForm.name" :placeholder="element_types[addElementForm.type]" class="mt-1.5" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Lebar (m)</Label>
                            <Input v-model.number="addElementForm.width_meters" type="number" min="0.1" step="0.1" class="mt-1.5" />
                        </div>
                        <div>
                            <Label>Panjang (m)</Label>
                            <Input v-model.number="addElementForm.length_meters" type="number" min="0.1" step="0.1" class="mt-1.5" />
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddElement = false">Batal</Button>
                    <Button @click="submitAddElement">Tambah</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- QR Dialog -->
        <Dialog :open="showQrDialog" @update:open="showQrDialog = $event">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>QR Order — {{ selectedTable?.name }}</DialogTitle>
                    <DialogDescription>Scan untuk order. Cetak dan letakkan di meja.</DialogDescription>
                </DialogHeader>
                <div v-if="selectedTable" class="flex flex-col items-center gap-4 py-4">
                    <img :src="qrImageUrl(selectedTable.qr_url)" :alt="`QR ${selectedTable.name}`" class="rounded-lg border bg-white p-2" />
                    <p class="break-all text-center text-xs text-muted-foreground">{{ selectedTable.qr_url }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showQrDialog = false">Tutup</Button>
                    <a v-if="selectedTable" :href="qrImageUrl(selectedTable.qr_url)" target="_blank" rel="noopener">
                        <Button>Buka / Cetak QR</Button>
                    </a>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="confirmState.open"
            :title="confirmState.title"
            :description="confirmState.description"
            :variant="confirmState.variant"
            :confirm-label="confirmState.confirmLabel"
            @update:open="(v) => { if (!v) confirmState.open = false }"
            @confirm="confirmState.onConfirm(); confirmState.open = false"
        />
        <AlertDialog
            :open="alertState.open"
            :message="alertState.message"
            variant="error"
            @update:open="(v) => { alertState.open = v }"
        />
    </AdminLayout>
</template>
