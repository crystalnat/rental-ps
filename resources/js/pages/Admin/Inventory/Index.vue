<script setup lang="ts">
import { ref, computed, toRef } from 'vue'
import { router } from '@inertiajs/vue3'
import { useTableFeatures } from '@/composables/useTableFeatures'
import AdminLayout from '@/layouts/AdminLayout.vue'
import FilterSelect from '@/components/FilterSelect.vue'
import { TableHeadSortable } from '@/components/ui/table'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import AlertDialog from '@/components/AlertDialog.vue'
import { formatCurrency } from '@/lib/utils'
import { Package, Search, Plus, Pencil, Loader2, Trash2, X } from 'lucide-vue-next'

interface Product {
    id: number
    name: string
    category_id: number | null
    category_name: string | null
    category_color: string | null
    unit: string
    track_stock: boolean
    current_stock: number
    image_url: string | null
}

interface Category {
    id: number
    name: string
    color?: string | null
}

interface Store {
    id: number
    name: string
    slug: string
}

interface RecentStockIn extends Record<string, unknown> {
    id: number
    product_name: string
    product_image: string | null
    quantity: number
    unit: string
    buy_price: number
    total_amount: number
    add_to_stock: boolean
    add_to_expense: boolean
    notes: string | null
    created_at: string
    created_at_iso: string
    creator_name: string | null
}

const props = defineProps<{
    store: Store
    products: Product[]
    categories: Category[]
    recent_stock_ins: RecentStockIn[]
    stores: Store[]
}>()

interface StockInItem {
    product_id: number | null
    product_name: string
    product_unit: string
    is_new: boolean
    name: string
    category_id: number | null
    unit: string
    sell_price: string
    quantity: string
    buy_price: string
    add_to_stock: boolean
    add_to_expense: boolean
    notes: string
}

function newItem(): StockInItem {
    return {
        product_id: null,
        product_name: '',
        product_unit: 'pcs',
        is_new: false,
        name: '',
        category_id: null,
        unit: 'pcs',
        sell_price: '',
        quantity: '',
        buy_price: '',
        add_to_stock: true,
        add_to_expense: true,
        notes: '',
    }
}

const items = ref<StockInItem[]>([newItem()])
const processing = ref(false)
const productSearch = ref('')
const pickerForIndex = ref<number | null>(null)

// Edit Barang Masuk
const showEditDialog = ref(false)
const editForm = ref({
    id: 0,
    product_name: '',
    quantity: '',
    buy_price: '',
    add_to_stock: true,
    add_to_expense: true,
    notes: '',
})
const alertState = ref<{ open: boolean; message: string; variant: 'info' | 'error' | 'warning' }>({
    open: false, message: '', variant: 'error',
})

function showAlert(message: string, variant: 'info' | 'error' | 'warning' = 'error') {
    alertState.value = { open: true, message, variant }
}

const editTotalAmount = computed(() => {
    const qty = Number(editForm.value.quantity) || 0
    const buy = Number(editForm.value.buy_price) || 0
    return qty * buy
})

function openEditDialog(s: RecentStockIn) {
    editForm.value = {
        id: s.id,
        product_name: s.product_name,
        quantity: String(s.quantity),
        buy_price: String(s.buy_price),
        add_to_stock: s.add_to_stock,
        add_to_expense: s.add_to_expense,
        notes: s.notes ?? '',
    }
    showEditDialog.value = true
}

function submitEdit() {
    const qty = Number(editForm.value.quantity)
    const buy = Number(editForm.value.buy_price)
    if (qty <= 0 || buy < 0) return
    processing.value = true
    showEditDialog.value = false
    router.put(`/admin/inventory/${editForm.value.id}`, {
        store: props.store.id,
        quantity: qty,
        buy_price: buy,
        add_to_stock: editForm.value.add_to_stock,
        add_to_expense: editForm.value.add_to_expense,
        notes: editForm.value.notes || null,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            showEditDialog.value = true
            showAlert('Gagal memperbarui: ' + Object.values(errors).flat().join(', '))
        },
        onFinish: () => { processing.value = false },
    })
}

// Hapus Barang Masuk
const deleteTarget = ref<RecentStockIn | null>(null)

function handleDelete() {
    if (!deleteTarget.value) return
    const id = deleteTarget.value.id
    deleteTarget.value = null
    router.delete(`/admin/inventory/${id}`, {
        preserveScroll: true,
        onError: (errors) => {
            showAlert('Gagal menghapus: ' + Object.values(errors).flat().join(', '))
        },
    })
}

const pickerFilteredProducts = computed(() => {
    const q = productSearch.value.toLowerCase().trim()
    if (!q) return props.products
    return props.products.filter(
        (p) =>
            p.name.toLowerCase().includes(q) ||
            (p.category_name?.toLowerCase().includes(q) ?? false),
    )
})

const recentStockInsRef = toRef(props, 'recent_stock_ins')
const stockInTable = useTableFeatures(recentStockInsRef, {
    getSearchText: (s) =>
        [s.product_name, s.notes, s.creator_name, s.created_at].filter(Boolean).join(' '),
    sortableFields: ['product_name', 'quantity', 'buy_price', 'total_amount', 'created_at_iso'],
    filters: {
        stock: [
            { value: 'all', label: 'Semua stok' },
            { value: 'to_stock', label: 'Masuk stok' },
            { value: 'manual', label: 'Manual' },
        ],
        expense: [
            { value: 'all', label: 'Semua pengeluaran' },
            { value: 'yes', label: 'Ada pengeluaran' },
            { value: 'no', label: 'Tanpa pengeluaran' },
        ],
    },
    getFilterValue: (item, key) => {
        if (key === 'stock') return item.add_to_stock ? 'to_stock' : 'manual'
        if (key === 'expense') return item.add_to_expense ? 'yes' : 'no'
        return null
    },
})
const {
    searchQuery: searchStockIns,
    sortKey: sortStockSortKey,
    sortDir: sortStockDir,
    filterValues: filterStockValues,
    filteredAndSortedData: filteredStockIns,
    setSort: setStockSort,
    setFilter: setStockFilter,
    clearFilters: clearStockFilters,
} = stockInTable

sortStockSortKey.value = 'created_at_iso'
sortStockDir.value = 'desc'

function resetStockHistoryFilters() {
    searchStockIns.value = ''
    filterStockValues.value = { stock: 'all', expense: 'all' }
    sortStockSortKey.value = 'created_at_iso'
    sortStockDir.value = 'desc'
}

const hasActiveStockFilters = computed(() => {
    return (
        !!searchStockIns.value.trim()
        || filterStockValues.value.stock !== 'all'
        || filterStockValues.value.expense !== 'all'
        || sortStockSortKey.value !== 'created_at_iso'
        || sortStockDir.value !== 'desc'
    )
})

function changeStore(storeId: number) {
    router.get('/admin/inventory', { store: storeId })
}

function addItem() {
    items.value.push(newItem())
}

function removeItem(index: number) {
    items.value.splice(index, 1)
    if (items.value.length === 0) items.value.push(newItem())
}

function openProductPicker(index: number) {
    pickerForIndex.value = index
    productSearch.value = ''
}

function selectProductForItem(product: Product) {
    const idx = pickerForIndex.value
    if (idx === null || idx < 0 || idx >= items.value.length) return
    items.value[idx] = {
        ...items.value[idx],
        product_id: product.id,
        product_name: product.name,
        product_unit: product.unit,
        is_new: false,
        unit: product.unit,
    }
    pickerForIndex.value = null
}

function setItemAsNew(index: number) {
    items.value[index] = {
        ...items.value[index],
        product_id: null,
        product_name: '',
        product_unit: 'pcs',
        is_new: true,
        name: '',
        category_id: null,
        unit: 'pcs',
        sell_price: '',
    }
    pickerForIndex.value = null
}

function getItemTotal(item: StockInItem) {
    const qty = Number(item.quantity) || 0
    const buy = Number(item.buy_price) || 0
    return qty * buy
}

const totalAll = computed(() => {
    return items.value.reduce((sum, it) => sum + getItemTotal(it), 0)
})

const validItems = computed(() => {
    return items.value.filter((it) => {
        if (it.is_new) {
            return !!it.name?.trim() && !!it.unit && Number(it.quantity) > 0 && Number(it.buy_price) >= 0
        }
        return it.product_id != null && Number(it.quantity) > 0 && Number(it.buy_price) >= 0
    })
})

const canSubmit = computed(() => validItems.value.length > 0)

function submitForm() {
    const payloadItems = validItems.value.map((it) => ({
        product_id: it.is_new ? null : it.product_id,
        is_new_product: it.is_new,
        name: it.is_new ? it.name : null,
        category_id: it.is_new ? it.category_id : null,
        unit: it.unit,
        sell_price: it.is_new && it.sell_price ? it.sell_price : null,
        quantity: it.quantity,
        buy_price: it.buy_price,
        add_to_stock: it.add_to_stock,
        add_to_expense: it.add_to_expense,
        notes: it.notes || null,
    }))
    processing.value = true
    router.post('/admin/inventory', {
        store: props.store.id,
        items: payloadItems,
    }, {
        preserveScroll: true,
        onFinish: () => { processing.value = false },
        onSuccess: () => {
            items.value = [newItem()]
        },
    })
}
</script>

<template>
    <AdminLayout :title="`Stok Barang - ${store.name}`">
        <!-- Form Barang Masuk - Full width -->
        <Card>
            <CardHeader class="p-4 md:p-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-1">
                        <CardTitle class="flex items-center gap-2 text-lg md:text-xl">
                            <Package class="h-5 w-5 shrink-0 text-primary" />
                            Barang Masuk
                        </CardTitle>
                        <CardDescription class="text-xs md:text-sm">
                            Tambah banyak produk sekaligus. Klik "Pilih Produk" untuk produk yang sudah ada.
                        </CardDescription>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div v-if="stores.length > 1" class="w-full sm:w-auto">
                            <select
                                :value="store.id"
                                class="filter-select h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm sm:min-w-[160px] sm:w-auto font-medium"
                                @change="changeStore(Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <Button type="button" variant="outline" size="sm" class="h-9 w-full sm:w-auto shadow-sm" @click="addItem">
                            <Plus class="mr-1.5 h-4 w-4 shrink-0" />
                            Tambah Baris
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="overflow-x-hidden">
                <form @submit.prevent="submitForm" class="min-w-0 space-y-6">
                    <div
                        v-for="(item, idx) in items"
                        :key="idx"
                        class="overflow-hidden rounded-2xl border border-border/80 bg-card"
                    >
                        <div class="flex items-start justify-between border-b border-border/50 px-4 py-3 sm:px-5 md:px-6">
                            <span class="text-sm font-medium text-muted-foreground">Item {{ idx + 1 }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                :disabled="items.length <= 1"
                                @click="removeItem(idx)"
                                title="Hapus item"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <div class="flex flex-col gap-5 p-4 sm:gap-6 sm:p-5 md:p-6">
                            <section class="space-y-3">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Produk</h3>
                                <div v-if="!item.is_new && item.product_id" class="flex flex-col gap-3 rounded-xl bg-muted/40 p-3 sm:flex-row sm:items-center sm:gap-4 sm:p-4">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-muted sm:h-14 sm:w-14">
                                                <img
                                                    v-if="products.find(p => p.id === item.product_id)?.image_url"
                                                    :src="products.find(p => p.id === item.product_id)!.image_url!"
                                                    :alt="item.product_name"
                                                    class="h-full w-full object-cover"
                                                />
                                                <div v-else class="flex h-full w-full items-center justify-center text-xl text-muted-foreground sm:text-2xl">
                                                    {{ item.product_name.charAt(0) }}
                                                </div>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-semibold">{{ item.product_name }}</p>
                                                <p class="text-xs text-muted-foreground sm:text-sm">{{ item.product_unit }} · Stok: {{ products.find(p => p.id === item.product_id)?.current_stock ?? 0 }}</p>
                                            </div>
                                        </div>
                                        <div class="flex shrink-0 gap-2">
                                            <Button type="button" variant="outline" size="sm" class="flex-1 sm:flex-initial" @click="openProductPicker(idx)">
                                                Ganti
                                            </Button>
                                            <button
                                                type="button"
                                                class="flex-1 rounded-md border border-dashed border-muted-foreground/40 px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:border-primary/50 hover:text-primary sm:flex-initial"
                                                @click="setItemAsNew(idx)"
                                            >
                                                + Baru
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Belum pilih produk -->
                                    <div v-else-if="!item.is_new" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                        <Button type="button" variant="outline" class="w-full sm:w-auto" @click="openProductPicker(idx)">
                                            <Package class="mr-2 h-4 w-4 shrink-0" />
                                            Pilih Produk
                                        </Button>
                                        <span class="hidden text-center text-muted-foreground sm:inline">atau</span>
                                        <button
                                            type="button"
                                            class="w-full rounded-lg border-2 border-dashed border-muted-foreground/30 px-4 py-3 text-sm font-medium text-muted-foreground transition-colors hover:border-primary/50 hover:bg-primary/5 hover:text-primary sm:w-auto"
                                            @click="setItemAsNew(idx)"
                                        >
                                            + Produk Baru
                                        </button>
                                    </div>
                                    <!-- Form Produk Baru -->
                                    <div v-else class="rounded-xl border border-primary/30 bg-primary/[0.03] p-4 sm:p-5">
                                        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <span class="text-sm font-semibold text-primary">Data Produk Baru</span>
                                            <button
                                                type="button"
                                                class="self-start text-xs text-muted-foreground hover:text-foreground sm:self-auto"
                                                @click="openProductPicker(idx)"
                                            >
                                                ← Pilih produk yang ada
                                            </button>
                                        </div>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div class="sm:col-span-2">
                                                <Label :for="'name-' + idx">Nama Produk</Label>
                                                <Input
                                                    :id="'name-' + idx"
                                                    v-model="item.name"
                                                    placeholder="Contoh: Kopi Bubuk 1kg"
                                                    class="mt-1.5 h-10"
                                                />
                                            </div>
                                            <div>
                                                <Label :for="'cat-' + idx">Kategori</Label>
                                                <select
                                                    :id="'cat-' + idx"
                                                    v-model="item.category_id"
                                                    class="filter-select mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                                >
                                                    <option :value="null">— Opsional —</option>
                                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <Label :for="'unit-' + idx">Satuan</Label>
                                                <Input
                                                    :id="'unit-' + idx"
                                                    v-model="item.unit"
                                                    placeholder="pcs"
                                                    class="mt-1.5 h-10"
                                                />
                                            </div>
                                            <div class="sm:col-span-2">
                                                <Label :for="'sell-' + idx">Harga Jual (Rp) — opsional</Label>
                                                <Input
                                                    :id="'sell-' + idx"
                                                    v-model="item.sell_price"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="Kosongkan = pakai harga beli"
                                                    class="mt-1.5 h-10"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <!-- Jumlah & Harga -->
                                <section class="space-y-3">
                                    <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Jumlah & Harga</h3>
                                    <div class="grid gap-4 grid-cols-1 sm:grid-cols-3">
                                        <div>
                                            <Label :for="'qty-' + idx">Jumlah</Label>
                                            <Input
                                                :id="'qty-' + idx"
                                                v-model="item.quantity"
                                                type="number"
                                                min="0.001"
                                                step="0.001"
                                                placeholder="0"
                                                class="mt-1.5 h-10"
                                            />
                                        </div>
                                        <div>
                                            <Label :for="'buy-' + idx">Harga Beli/unit (Rp)</Label>
                                            <Input
                                                :id="'buy-' + idx"
                                                v-model="item.buy_price"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="0"
                                                class="mt-1.5 h-10"
                                            />
                                        </div>
                                        <div>
                                            <Label class="text-muted-foreground">Total</Label>
                                            <div class="mt-1.5 flex min-h-10 items-center overflow-hidden rounded-md border border-input bg-muted/30 px-3">
                                                <p class="truncate text-base font-semibold tabular-nums sm:text-lg md:text-xl">{{ formatCurrency(getItemTotal(item)) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <!-- Opsi -->
                                <section class="space-y-3">
                                    <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Opsi</h3>
                                    <div class="grid gap-4 rounded-xl border border-border/60 bg-muted/20 p-3 sm:grid-cols-3 sm:p-4">
                                        <div class="space-y-2">
                                            <Label :for="'stock-' + idx" class="block text-sm">Tambah ke stok</Label>
                                            <Switch :id="'stock-' + idx" :checked="item.add_to_stock" @update:checked="item.add_to_stock = $event" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label :for="'exp-' + idx" class="block text-sm">Catat pengeluaran</Label>
                                            <Switch :id="'exp-' + idx" :checked="item.add_to_expense" @update:checked="item.add_to_expense = $event" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label :for="'notes-' + idx" class="block text-sm">Catatan</Label>
                                            <Input :id="'notes-' + idx" v-model="item.notes" placeholder="Faktur, supplier..." class="h-9" />
                                        </div>
                                    </div>
                                </section>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 border-t pt-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:pt-6">
                        <div v-if="totalAll > 0" class="rounded-lg bg-muted px-4 py-2 text-center sm:text-left">
                            <span class="text-sm text-muted-foreground">Total pengeluaran: </span>
                            <span class="font-semibold">{{ formatCurrency(totalAll) }}</span>
                        </div>
                        <Button type="submit" class="w-full sm:w-auto" :disabled="processing || !canSubmit">
                            <Loader2 v-if="processing" class="h-4 w-4 shrink-0 animate-spin" />
                            {{ processing ? 'Menyimpan...' : 'Simpan Barang Masuk' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Modal Pilih Produk -->
        <Dialog :open="pickerForIndex !== null" @update:open="(v) => { if (!v) pickerForIndex = null }">
            <DialogContent class="w-[95vw] max-w-lg">
                <DialogHeader>
                    <DialogTitle>Pilih Produk</DialogTitle>
                    <DialogDescription>Klik produk untuk memilih</DialogDescription>
                    <div class="relative mt-2">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="productSearch"
                            placeholder="Cari produk..."
                            class="pl-9"
                        />
                    </div>
                </DialogHeader>
                <div class="grid max-h-[280px] grid-cols-2 gap-2 overflow-y-auto sm:max-h-[300px] sm:grid-cols-3 md:grid-cols-4">
                    <button
                        v-for="p in pickerFilteredProducts"
                        :key="p.id"
                        type="button"
                        class="flex cursor-pointer flex-col items-stretch rounded-lg border p-2 text-left transition-colors hover:bg-accent"
                        @click="selectProductForItem(p)"
                    >
                        <div class="aspect-square w-full overflow-hidden rounded-md bg-muted">
                            <img
                                v-if="p.image_url"
                                :src="p.image_url"
                                :alt="p.name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center text-xl text-muted-foreground">
                                {{ p.name.charAt(0) }}
                            </div>
                        </div>
                        <p class="mt-1 truncate text-xs">{{ p.name }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ p.unit }} · {{ p.current_stock }}</p>
                    </button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Riwayat Barang Masuk — tabel + sort/filter/search -->
        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Riwayat Barang Masuk</CardTitle>
                <CardDescription>Catatan barang masuk (maks. 500 entri terbaru)</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 p-4 sm:p-6">
                <div v-if="recent_stock_ins.length === 0" class="rounded-xl border border-dashed py-12 text-center text-muted-foreground">
                    Belum ada catatan barang masuk.
                </div>
                <template v-else>
                    <div class="mb-4 flex flex-col md:flex-row md:items-center gap-3 rounded-xl border bg-muted/20 p-3">
                        <div class="relative flex-1 min-w-0">
                            <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="searchStockIns" placeholder="Cari..." class="h-9 pl-8" />
                        </div>
                        <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                            <FilterSelect
                                :model-value="filterStockValues.stock"
                                :options="stockInTable.filters.stock"
                                @update:model-value="setStockFilter('stock', $event)"
                            />
                            <FilterSelect
                                :model-value="filterStockValues.expense"
                                :options="stockInTable.filters.expense"
                                @update:model-value="setStockFilter('expense', $event)"
                            />
                            <Button
                                v-if="hasActiveStockFilters"
                                variant="ghost"
                                size="sm"
                                class="h-9 shrink-0 text-muted-foreground hover:text-destructive px-2"
                                @click="resetStockHistoryFilters"
                            >
                                <X class="mr-1 h-3.5 w-3.5" /> Reset
                            </Button>
                        </div>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Menampilkan <strong class="text-foreground">{{ filteredStockIns.length }}</strong> dari {{ recent_stock_ins.length }} catatan
                    </p>
                    <!-- Daftar kartu: dipakai di HP supaya tabel lebar tidak perlu digeser horizontal -->
                    <div class="space-y-2 md:hidden">
                        <div
                            v-for="s in filteredStockIns"
                            :key="s.id"
                            class="rounded-lg border p-3"
                            @click="openEditDialog(s as RecentStockIn)"
                        >
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg border bg-muted">
                                    <img
                                        v-if="s.product_image"
                                        :src="s.product_image as string"
                                        :alt="s.product_name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full items-center justify-center text-sm font-semibold text-muted-foreground">
                                        {{ s.product_name.charAt(0) }}
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="break-words font-medium leading-tight">{{ s.product_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ s.created_at }}</p>
                                    <p v-if="s.creator_name" class="text-xs text-muted-foreground">{{ s.creator_name }}</p>
                                </div>
                                <div class="flex shrink-0 gap-1" @click.stop>
                                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="openEditDialog(s as RecentStockIn)" title="Edit">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0 text-destructive hover:bg-destructive/10"
                                        @click="deleteTarget = s as RecentStockIn"
                                        title="Hapus"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 border-t pt-2 text-xs">
                                <div class="min-w-0">
                                    <p class="text-[10px] uppercase text-muted-foreground">Jumlah</p>
                                    <p class="tabular-nums font-medium">{{ s.quantity }} <span class="text-[10px] uppercase text-muted-foreground">{{ s.unit }}</span></p>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] uppercase text-muted-foreground">Harga/unit</p>
                                    <p class="whitespace-nowrap tabular-nums">{{ formatCurrency(s.buy_price) }}</p>
                                </div>
                                <div class="min-w-0 text-right">
                                    <p class="text-[10px] uppercase text-muted-foreground">Total</p>
                                    <p class="whitespace-nowrap font-bold tabular-nums text-primary">{{ formatCurrency(s.total_amount) }}</p>
                                </div>
                            </div>
                            <div v-if="!s.add_to_stock || !s.add_to_expense" class="mt-2 flex flex-wrap gap-1">
                                <Badge v-if="!s.add_to_stock" variant="secondary" class="text-[10px]">Manual</Badge>
                                <Badge v-if="!s.add_to_expense" variant="outline" class="text-[10px]">Tanpa pengeluaran</Badge>
                            </div>
                        </div>
                        <div v-if="filteredStockIns.length === 0" class="rounded-lg border py-8 text-center text-sm text-muted-foreground">
                            Tidak ada data yang cocok dengan filter / pencarian.
                        </div>
                    </div>

                    <div class="hidden overflow-hidden rounded-2xl border bg-card shadow-sm md:block">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/20 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        <TableHeadSortable
                                            label="Produk"
                                            sort-key="product_name"
                                            :current-sort-key="sortStockSortKey"
                                            :sort-dir="sortStockDir"
                                            @sort="setStockSort"
                                        />
                                        <TableHeadSortable
                                            label="Tanggal"
                                            sort-key="created_at_iso"
                                            :current-sort-key="sortStockSortKey"
                                            :sort-dir="sortStockDir"
                                            class-names="hidden lg:table-cell"
                                            @sort="setStockSort"
                                        />
                                        <TableHeadSortable
                                            label="Jumlah"
                                            sort-key="quantity"
                                            :current-sort-key="sortStockSortKey"
                                            :sort-dir="sortStockDir"
                                            align="right"
                                            @sort="setStockSort"
                                        />
                                        <TableHeadSortable
                                            label="Harga/unit"
                                            sort-key="buy_price"
                                            :current-sort-key="sortStockSortKey"
                                            :sort-dir="sortStockDir"
                                            align="right"
                                            @sort="setStockSort"
                                        />
                                        <TableHeadSortable
                                            label="Total"
                                            sort-key="total_amount"
                                            :current-sort-key="sortStockSortKey"
                                            :sort-dir="sortStockDir"
                                            align="right"
                                            @sort="setStockSort"
                                        />
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Opsi</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="s in filteredStockIns"
                                        :key="s.id"
                                        class="cursor-pointer transition-colors hover:bg-muted/40"
                                        @click="openEditDialog(s as RecentStockIn)"
                                    >
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg border bg-muted">
                                                    <img
                                                        v-if="s.product_image"
                                                        :src="s.product_image as string"
                                                        :alt="s.product_name"
                                                        class="h-full w-full object-cover"
                                                    />
                                                    <div v-else class="flex h-full w-full items-center justify-center text-sm font-semibold text-muted-foreground">
                                                        {{ s.product_name.charAt(0) }}
                                                    </div>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold leading-tight">{{ s.product_name }}</p>
                                                    <p v-if="s.creator_name" class="text-xs text-muted-foreground">{{ s.creator_name }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3.5 text-muted-foreground hidden lg:table-cell">{{ s.created_at }}</td>
                                        <td class="px-4 py-3.5 text-right tabular-nums font-medium">{{ s.quantity }} <span class="text-[10px] uppercase text-muted-foreground">{{ s.unit }}</span></td>
                                        <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums">{{ formatCurrency(s.buy_price) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3.5 text-right font-bold tabular-nums text-primary">{{ formatCurrency(s.total_amount) }}</td>
                                        <td class="px-4 py-3.5">
                                            <div class="flex flex-wrap gap-1">
                                                <Badge v-if="!s.add_to_stock" variant="secondary" class="text-[10px]">Manual</Badge>
                                                <Badge v-if="!s.add_to_expense" variant="outline" class="text-[10px]">Tanpa pengeluaran</Badge>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5 text-right">
                                            <div class="flex justify-end gap-1">
                                                <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click.stop="openEditDialog(s as RecentStockIn)" title="Edit">
                                                    <Pencil class="h-3.5 w-3.5" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 w-8 p-0 text-destructive hover:bg-destructive/10"
                                                    @click.stop="deleteTarget = s as RecentStockIn"
                                                    title="Hapus"
                                                >
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="filteredStockIns.length === 0" class="border-t py-8 text-center text-sm text-muted-foreground">
                            Tidak ada data yang cocok dengan filter / pencarian.
                        </div>
                    </div>
                </template>
            </CardContent>
        </Card>

        <!-- Hapus Konfirmasi -->
        <Dialog :open="!!deleteTarget" @update:open="(v) => { if (!v) deleteTarget = null }">
            <DialogContent class="w-[95vw] max-w-sm">
                <DialogHeader>
                    <DialogTitle>Hapus Barang Masuk</DialogTitle>
                    <DialogDescription>
                        Hapus catatan "{{ deleteTarget?.product_name }}" ({{ deleteTarget?.quantity }} {{ deleteTarget?.unit }})?
                        Stok dan pengeluaran akan dikembalikan/dihapus otomatis.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="deleteTarget = null">Batal</Button>
                    <Button variant="destructive" class="w-full sm:w-auto" @click="handleDelete">Ya, Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Barang Masuk Modal -->
        <Dialog :open="showEditDialog" @update:open="showEditDialog = $event">
            <DialogContent class="max-h-[90vh] w-[95vw] max-w-md overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Edit Barang Masuk</DialogTitle>
                    <DialogDescription>
                        {{ editForm.product_name }} — ubah jumlah, harga, atau opsi. Stok & pengeluaran akan disesuaikan otomatis.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div class="rounded-lg border bg-muted/30 p-3">
                        <p class="font-medium">{{ editForm.product_name }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="edit_quantity">Jumlah</Label>
                            <Input
                                id="edit_quantity"
                                v-model="editForm.quantity"
                                type="number"
                                min="0.001"
                                step="0.001"
                                placeholder="0"
                                class="mt-1"
                                :disabled="processing"
                            />
                        </div>
                        <div>
                            <Label for="edit_buy_price">Harga Beli (per unit)</Label>
                            <Input
                                id="edit_buy_price"
                                v-model="editForm.buy_price"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0"
                                class="mt-1"
                                :disabled="processing"
                            />
                        </div>
                    </div>
                    <div v-if="editTotalAmount > 0" class="rounded-lg bg-muted p-3">
                        <p class="text-sm text-muted-foreground">Total pengeluaran</p>
                        <p class="text-lg font-semibold">{{ formatCurrency(editTotalAmount) }}</p>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <Label for="edit_add_to_stock">Tambahkan ke stok</Label>
                            <p class="text-xs text-muted-foreground">Stok otomatis disesuaikan</p>
                        </div>
                        <Switch
                            id="edit_add_to_stock"
                            :checked="editForm.add_to_stock"
                            @update:checked="editForm.add_to_stock = $event"
                        />
                    </div>
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <Label for="edit_add_to_expense">Masuk pengeluaran</Label>
                            <p class="text-xs text-muted-foreground">Nilai pengeluaran disesuaikan</p>
                        </div>
                        <Switch
                            id="edit_add_to_expense"
                            :checked="editForm.add_to_expense"
                            @update:checked="editForm.add_to_expense = $event"
                        />
                    </div>
                    <div>
                        <Label for="edit_notes">Catatan (opsional)</Label>
                        <Input id="edit_notes" v-model="editForm.notes" placeholder="No. faktur, supplier, dll" class="mt-1" :disabled="processing" />
                    </div>
                </div>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="showEditDialog = false" :disabled="processing">Batal</Button>
                    <Button class="w-full sm:w-auto" @click="submitEdit" :disabled="processing || Number(editForm.quantity) <= 0 || Number(editForm.buy_price) < 0">
                        <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                        Simpan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog
            :open="alertState.open"
            :message="alertState.message"
            :variant="alertState.variant"
            @update:open="alertState.open = $event"
        />
    </AdminLayout>
</template>

<style scoped>
.filter-select {
    appearance: none;
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.25rem 1.25rem;
    /* Panah digambar sebagai background, tanpa ruang ini teks panjang tertutup panah */
    padding-right: 2rem;
}
:global(.theme-dark) .filter-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
}
</style>
