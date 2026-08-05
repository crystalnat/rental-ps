<script setup lang="ts">
import { ref, computed, toRef, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useTableFeatures } from '@/composables/useTableFeatures'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import MoneyInput from '@/components/ui/input/MoneyInput.vue'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import AlertDialog from '@/components/AlertDialog.vue'
import TableToolbar from '@/components/TableToolbar.vue'
import FilterSelect from '@/components/FilterSelect.vue'
import { TableHeadSortable } from '@/components/ui/table'
import { formatCurrency } from '@/lib/utils'
import {
    ArrowLeft, Copy, Package, Plus, Search, Check, X, Store as StoreIcon, DollarSign, Pencil, Trash2,
    LayoutGrid, List,
} from 'lucide-vue-next'

interface Product {
    id: number
    name: string
    sku: string | null
    category: string | null
    category_id: number | null
    category_color: string | null
    unit: string
    is_available: boolean
    has_inventory: boolean
    track_stock: boolean
    current_stock: number
    min_stock: number
    sell_price: number
    buy_price: number
    description: string | null
    image_url: string | null
}

interface Store {
    id: number
    name: string
    slug: string
    city: string | null
}

interface OtherStore {
    id: number
    name: string
    city: string | null
    is_active: boolean
}

interface Category {
    id: number
    name: string
    color: string | null
}

const props = defineProps<{
    store: Store
    products: Product[]
    other_stores: OtherStore[]
    categories: Category[]
}>()

const layout = ref<'table' | 'grid'>('table')

const tableFeatures = useTableFeatures(toRef(props, 'products'), {
    getSearchText: (p) => [p.name, p.sku, p.category].filter(Boolean).join(' '),
    sortableFields: ['name', 'category', 'current_stock', 'buy_price', 'sell_price', 'is_available'],
    filters: {
        category_id: [
            { value: 'all', label: 'Semua Kategori' },
            { value: 'none', label: 'Tanpa Kategori' },
            ...props.categories.map((c) => ({ value: String(c.id), label: c.name })),
        ],
        status: [
            { value: 'all', label: 'Semua Status' },
            { value: 'available', label: 'Tersedia' },
            { value: 'inactive', label: 'Nonaktif' },
        ],
    },
    getFilterValue: (item, key) =>
        key === 'status'
            ? (item.is_available ? 'available' : 'inactive')
            : key === 'category_id'
                ? (item.category_id != null ? String(item.category_id) : 'none')
                : null,
})

const { searchQuery, sortKey, sortDir, filterValues, filteredAndSortedData, setSort, setFilter, clearFilters } = tableFeatures
const filteredProducts = filteredAndSortedData
const selectedProducts = ref<number[]>([])
const showCopyDialog = ref(false)
const selectedTargetStores = ref<number[]>([])
const copyPrices = ref(true)
const copyStock = ref(true)
const processing = ref(false)

// Confirm & Alert dialogs
const confirmState = ref<{
    open: boolean
    title: string
    description: string
    variant: 'default' | 'destructive'
    confirmLabel?: string
    onConfirm: () => void
}>({
    open: false,
    title: '',
    description: '',
    variant: 'default',
    onConfirm: () => {},
})
const alertState = ref<{ open: boolean; message: string; variant: 'info' | 'error' | 'warning' }>({
    open: false,
    message: '',
    variant: 'error',
})

function showAlert(message: string, variant: 'info' | 'error' | 'warning' = 'error') {
    alertState.value = { open: true, message, variant }
}

function showConfirm(config: Omit<typeof confirmState.value, 'open'>) {
    confirmState.value = { ...config, open: true }
}

function closeConfirm() {
    confirmState.value.open = false
}

function runConfirm() {
    confirmState.value.onConfirm()
    closeConfirm()
}

// Edit Stock Dialog
const showStockDialog = ref(false)
const stockForm = ref({
    product_id: 0,
    product_name: '',
    current_stock: 0,
    min_stock: 0,
})

// Edit Price Dialog
const showPriceDialog = ref(false)
const priceForm = ref({
    product_id: 0,
    product_name: '',
    buy_price: 0,
    sell_price: 0,
    has_custom_price: false,
})

// Add Product Dialog
const showAddDialog = ref(false)
const addImageFile = ref<File | null>(null)
const addImagePreview = ref<string | null>(null)
const addForm = ref({
    category_id: null as number | null,
    name: '',
    sku: '',
    description: '',
    unit: 'pcs',
    buy_price: 0,
    sell_price: 0,
    track_stock: true,
    is_available: true,
    current_stock: 0,
    min_stock: 0,
    is_rental_package: false,
    rental_duration_value: null as number | null,
    rental_duration_unit: 'jam' as 'menit' | 'jam',
})

// Mode rental: satuan & HPP tidak relevan, isi otomatis biar form ringkas.
watch(() => addForm.value.is_rental_package, (isRental) => {
    if (isRental) {
        addForm.value.unit = 'paket'
        addForm.value.buy_price = 0
    } else if (addForm.value.unit === 'paket') {
        addForm.value.unit = 'pcs'
    }
})

// Durasi disimpan ke DB dalam menit; input boleh menit atau jam.
const addRentalMinutes = computed(() => {
    const v = addForm.value.rental_duration_value
    if (!v || v <= 0) return null
    return addForm.value.rental_duration_unit === 'jam' ? Math.round(v * 60) : Math.round(v)
})


const allSelected = computed({
    get: () => selectedProducts.value.length === filteredProducts.value.length && filteredProducts.value.length > 0,
    set: (val) => {
        selectedProducts.value = val ? filteredProducts.value.map((p) => p.id) : []
    },
})

function toggleProduct(id: number) {
    const idx = selectedProducts.value.indexOf(id)
    if (idx > -1) {
        selectedProducts.value.splice(idx, 1)
    } else {
        selectedProducts.value.push(id)
    }
}

function openCopyDialog() {
    if (selectedProducts.value.length === 0) {
        showAlert('Pilih produk terlebih dahulu', 'warning')
        return
    }
    showCopyDialog.value = true
}

function toggleTargetStore(id: number) {
    const idx = selectedTargetStores.value.indexOf(id)
    if (idx > -1) {
        selectedTargetStores.value.splice(idx, 1)
    } else {
        selectedTargetStores.value.push(id)
    }
}

function submitCopy() {
    if (selectedTargetStores.value.length === 0) {
        showAlert('Pilih minimal 1 toko tujuan', 'warning')
        return
    }

    processing.value = true
    router.post(
        `/admin/stores/${props.store.id}/products/copy`,
        {
            product_ids:      selectedProducts.value,
            target_store_ids: selectedTargetStores.value,
            copy_prices:      copyPrices.value,
            copy_stock:       copyStock.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCopyDialog.value = false
                selectedProducts.value = []
                selectedTargetStores.value = []
            },
            onError: (errors) => {
                showAlert('Gagal copy produk: ' + Object.values(errors).join(', '))
            },
            onFinish: () => {
                processing.value = false
            },
        },
    )
}

function openStockDialog(product: Product) {
    stockForm.value = {
        product_id: product.id,
        product_name: product.name,
        current_stock: product.current_stock,
        min_stock: product.min_stock,
    }
    showStockDialog.value = true
}

function submitStock() {
    processing.value = true
    const payload = {
        product_id:    stockForm.value.product_id,
        current_stock: Number(stockForm.value.current_stock),
        min_stock:     Number(stockForm.value.min_stock),
    }
    router.post(
        `/admin/stores/${props.store.id}/products/stock`,
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                showStockDialog.value = false
            },
            onError: (errors) => {
                showAlert('Gagal menyimpan stok: ' + Object.values(errors).join(', '))
            },
            onFinish: () => {
                processing.value = false
            },
        },
    )
}

function openPriceDialog(product: Product) {
    priceForm.value = {
        product_id: product.id,
        product_name: product.name,
        buy_price: product.buy_price,
        sell_price: product.sell_price,
        has_custom_price: product.has_inventory,
    }
    showPriceDialog.value = true
}

function submitPrice() {
    processing.value = true
    router.post(
        `/admin/stores/${props.store.id}/products/price`,
        {
            product_id: priceForm.value.product_id,
            buy_price:  Number(priceForm.value.buy_price),
            sell_price: Number(priceForm.value.sell_price),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showPriceDialog.value = false
            },
            onError: (errors) => {
                showAlert('Gagal menyimpan harga: ' + Object.values(errors).join(', '))
            },
            onFinish: () => {
                processing.value = false
            },
        },
    )
}

function resetPrice(productId: number) {
    showConfirm({
        title: 'Reset Harga',
        description: 'Reset harga ke harga default brand?',
        variant: 'default',
        confirmLabel: 'Ya, Reset',
        onConfirm: () => {
            processing.value = true
            router.post(
                `/admin/stores/${props.store.id}/products/${productId}/reset-price`,
                {},
                {
                    preserveScroll: true,
                    onFinish: () => {
                        processing.value = false
                    },
                },
            )
        },
    })
}

function onAddImageChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    addImageFile.value = file
    addImagePreview.value = URL.createObjectURL(file)
}

function removeAddImage() {
    addImageFile.value = null
    addImagePreview.value = null
}

function resetAddForm() {
    addForm.value = {
        category_id: null,
        name: '',
        sku: '',
        description: '',
        unit: 'pcs',
        buy_price: 0,
        sell_price: 0,
        track_stock: true,
        is_available: true,
        current_stock: 0,
        min_stock: 0,
        is_rental_package: false,
        rental_duration_value: null,
        rental_duration_unit: 'jam',
    }
    addImageFile.value = null
    addImagePreview.value = null
}

function openAddDialog() {
    resetAddForm()
    showAddDialog.value = true
}

function submitAdd() {
    processing.value = true
    showAddDialog.value = false // Tutup modal dulu, responsif
    router.post(
        `/admin/stores/${props.store.id}/products/add`,
        {
            category_id:   addForm.value.category_id,
            name:          addForm.value.name,
            sku:           addForm.value.sku || null,
            description:   addForm.value.description || null,
            unit:          addForm.value.unit,
            buy_price:     Number(addForm.value.buy_price),
            sell_price:    Number(addForm.value.sell_price),
            track_stock:   addForm.value.track_stock,
            is_available:  addForm.value.is_available,
            current_stock: Number(addForm.value.current_stock),
            min_stock:     Number(addForm.value.min_stock),
            is_rental_package:       addForm.value.is_rental_package,
            rental_duration_minutes: addForm.value.is_rental_package ? addRentalMinutes.value : null,
            image:         addImageFile.value ?? undefined,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                resetAddForm()
            },
            onError: (errors) => {
                showAddDialog.value = true // Buka lagi kalau ada error
                showAlert('Gagal menambah produk: ' + Object.values(errors).flat().join(', '))
            },
            onFinish: () => {
                processing.value = false
            },
        },
    )
}

const addMarginPercentage = computed(() => {
    if (!addForm.value.buy_price || !addForm.value.sell_price) return 0
    return ((addForm.value.sell_price - addForm.value.buy_price) / addForm.value.buy_price * 100).toFixed(1)
})

// Edit Product Dialog
const showEditDialog = ref(false)
const editImageFile = ref<File | null>(null)
const editImagePreview = ref<string | null>(null)
const editCurrentImageUrl = ref<string | null>(null)
const editForm = ref({
    id: 0,
    category_id: null as number | null,
    name: '',
    sku: '',
    description: '',
    unit: 'pcs',
    track_stock: true,
    is_available: true,
    buy_price: 0,
    sell_price: 0,
    current_stock: 0,
    min_stock: 0,
})

function onEditImageChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    editImageFile.value = file
    editImagePreview.value = URL.createObjectURL(file)
}

function removeEditImage() {
    editImageFile.value = null
    editImagePreview.value = null
}

function openEditDialog(product: Product) {
    editForm.value = {
        id:            product.id,
        category_id:   product.category_id,
        name:          product.name,
        sku:           product.sku ?? '',
        description:   product.description ?? '',
        unit:          product.unit,
        track_stock:   product.track_stock,
        is_available:  product.is_available,
        buy_price:     product.buy_price,
        sell_price:    product.sell_price,
        current_stock: product.current_stock,
        min_stock:     product.min_stock,
    }
    editImageFile.value = null
    editImagePreview.value = null
    editCurrentImageUrl.value = product.image_url ?? null
    showEditDialog.value = true
}

const editMarginPercentage = computed(() => {
    if (!editForm.value.buy_price || !editForm.value.sell_price) return 0
    return ((editForm.value.sell_price - editForm.value.buy_price) / editForm.value.buy_price * 100).toFixed(1)
})

function submitEdit() {
    processing.value = true
    showEditDialog.value = false // Tutup modal dulu, responsif
    router.post(
        `/admin/stores/${props.store.id}/products/${editForm.value.id}`,
        {
            _method:       'PUT',
            category_id:   editForm.value.category_id,
            name:          editForm.value.name,
            sku:           editForm.value.sku || null,
            description:   editForm.value.description || null,
            unit:          editForm.value.unit,
            track_stock:   editForm.value.track_stock,
            is_available:  editForm.value.is_available,
            buy_price:     Number(editForm.value.buy_price),
            sell_price:    Number(editForm.value.sell_price),
            current_stock: Number(editForm.value.current_stock),
            min_stock:     Number(editForm.value.min_stock),
            image:         editImageFile.value ?? undefined,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                showEditDialog.value = true // Buka lagi kalau ada error
                showAlert('Gagal menyimpan produk: ' + Object.values(errors).flat().join(', '))
            },
            onFinish: () => {
                processing.value = false
            },
        },
    )
}

function deleteProduct(product: Product) {
    showConfirm({
        title: 'Hapus Produk',
        description: `Hapus produk "${product.name}"? Tindakan ini tidak dapat dibatalkan.`,
        variant: 'destructive',
        confirmLabel: 'Ya, Hapus',
        onConfirm: () => {
            processing.value = true
            router.delete(
                `/admin/stores/${props.store.id}/products/${product.id}`,
                {
                    preserveScroll: true,
                    onError: (errors) => {
                        showAlert('Gagal menghapus produk: ' + Object.values(errors).join(', '))
                    },
                    onFinish: () => {
                        processing.value = false
                    },
                },
            )
        },
    })
}
</script>

<template>
    <AdminLayout :title="`Kelola Produk — ${store.name}`">
        <div class="mb-4">
            <Link href="/admin/dashboard">
                <Button variant="outline" size="sm" class="cursor-pointer">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
            </Link>
        </div>

        <!-- Store Info Header -->
        <div class="mb-6 rounded-2xl border bg-card p-4 md:p-5 shadow-sm">
            <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
                <div class="flex h-12 w-12 md:h-14 md:w-14 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <StoreIcon class="h-6 w-6 md:h-7 md:w-7" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg md:text-xl font-bold truncate">{{ store.name }}</h2>
                    <p class="text-xs md:text-sm text-muted-foreground truncate">{{ store.city ?? 'Lokasi belum diatur' }}</p>
                </div>
                <!-- Stats -->
                <div class="flex w-full md:w-auto items-center justify-between md:justify-end gap-4 md:gap-6 pt-2 md:pt-0 border-t md:border-t-0 border-border md:border-none">
                    <div class="text-center">
                        <p class="text-lg md:text-2xl font-bold text-primary">{{ products.length }}</p>
                        <p class="text-[10px] md:text-xs text-muted-foreground uppercase tracking-tight">Produk</p>
                    </div>
                    <div class="hidden md:block w-px h-8 bg-border" />
                    <div class="text-center">
                        <p class="text-lg md:text-2xl font-bold">{{ filteredProducts.filter(p => p.is_available).length }}</p>
                        <p class="text-[10px] md:text-xs text-muted-foreground uppercase tracking-tight">Tersedia</p>
                    </div>
                    <div class="hidden md:block w-px h-8 bg-border" />
                    <div class="text-center">
                        <p class="text-lg md:text-2xl font-bold">{{ other_stores.length }}</p>
                        <p class="text-[10px] md:text-xs text-muted-foreground uppercase tracking-tight">Toko Lain</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toolbar: search, filter, layout -->
        <div class="mb-4 space-y-3">
            <div class="flex flex-col gap-3 rounded-xl border bg-muted/20 p-3 md:flex-row md:items-center">
                <!-- Search & Layout toggle -->
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <div class="relative min-w-0 flex-1">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="searchQuery" placeholder="Cari..." class="h-9 pl-8" />
                    </div>
                    <div class="flex shrink-0 items-center rounded-md border bg-background p-0.5 md:hidden">
                        <button
                            type="button"
                            class="cursor-pointer rounded px-2.5 py-1.5 text-muted-foreground transition-colors"
                            :class="layout === 'table' && 'bg-accent text-foreground shadow-sm'"
                            @click="layout = 'table'"
                        >
                            <List class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded px-2.5 py-1.5 text-muted-foreground transition-colors"
                            :class="layout === 'grid' && 'bg-accent text-foreground shadow-sm'"
                            @click="layout = 'grid'"
                        >
                            <LayoutGrid class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Filters (Scrollable) -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                    <FilterSelect
                        :model-value="filterValues.category_id"
                        :options="tableFeatures.filters.category_id"
                        @update:model-value="setFilter('category_id', $event)"
                    />
                    <FilterSelect
                        :model-value="filterValues.status"
                        :options="tableFeatures.filters.status"
                        @update:model-value="setFilter('status', $event)"
                    />
                    <Button
                        v-if="searchQuery || sortKey || filterValues.category_id !== 'all' || filterValues.status !== 'all'"
                        variant="ghost"
                        size="sm"
                        class="h-9 shrink-0 text-muted-foreground hover:text-destructive"
                        @click="clearFilters"
                    >
                        <X class="mr-1 h-3 w-3" /> Reset
                    </Button>
                </div>

                <div class="hidden md:block h-6 w-px bg-border" />
                
                <!-- Desktop Layout Toggles -->
                <div class="hidden md:flex items-center rounded-md border bg-background p-0.5">
                    <button
                        type="button"
                        class="cursor-pointer rounded px-2 py-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        :class="layout === 'table' && 'bg-accent text-foreground'"
                        title="Tabel"
                        @click="layout = 'table'"
                    >
                        <List class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded px-2 py-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        :class="layout === 'grid' && 'bg-accent text-foreground'"
                        title="Grid"
                        @click="layout = 'grid'"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </button>
                </div>

                <!-- Desktop Actions -->
                <div class="hidden md:flex flex-1 items-center justify-end gap-2">
                    <Button variant="outline" size="sm" class="h-9" @click="openAddDialog">
                        <Plus class="mr-1 h-4 w-4" /> Tambah
                    </Button>
                    <Button
                        size="sm"
                        class="h-9"
                        :disabled="selectedProducts.length === 0"
                        @click="openCopyDialog"
                    >
                        <Copy class="mr-1 h-4 w-4" /> Salin
                        <span v-if="selectedProducts.length > 0" class="ml-1.5 rounded-full bg-white/20 px-1.5 py-0.5 text-[10px]">
                            {{ selectedProducts.length }}
                        </span>
                    </Button>
                </div>
            </div>

            <!-- Mobile Quick Actions Bar -->
            <div class="flex items-center gap-2 md:hidden">
                <Button variant="outline" size="lg" class="flex-1 h-11 shadow-sm" @click="openAddDialog">
                    <Plus class="mr-2 h-4 w-4 text-primary" /> Tambah Produk
                </Button>
                <Button
                    size="lg"
                    class="flex-1 h-11 shadow-sm"
                    :disabled="selectedProducts.length === 0"
                    @click="openCopyDialog"
                >
                    <Copy class="mr-2 h-4 w-4" /> Salin
                    <span v-if="selectedProducts.length > 0" class="ml-2 rounded-full bg-white/20 px-2 py-0.5 text-xs font-bold leading-none">
                        {{ selectedProducts.length }}
                    </span>
                </Button>
            </div>
        </div>

        <!-- Summary -->
        <div class="mb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-sm">
            <span class="text-muted-foreground">
                Menampilkan <strong class="text-foreground">{{ filteredProducts.length }}</strong> dari {{ products.length }} produk
            </span>
            <button
                v-if="selectedProducts.length > 0"
                class="cursor-pointer text-primary hover:underline self-start sm:self-auto font-medium"
                @click="selectedProducts = []"
            >
                Batal pilih ({{ selectedProducts.length }} item terpiih)
            </button>
        </div>

        <!-- ── TABLE LAYOUT ── -->
        <div v-if="layout === 'table'">
        <!-- Di HP data tabel disajikan sebagai kartu supaya tidak perlu geser horizontal -->
        <div class="space-y-2 md:hidden">
            <div
                v-for="product in filteredProducts"
                :key="product.id"
                class="rounded-lg border p-3"
                :class="selectedProducts.includes(product.id) ? 'ring-2 ring-primary' : ''"
            >
                <div class="flex items-start gap-3">
                    <Checkbox
                        class="mt-1 shrink-0"
                        :checked="selectedProducts.includes(product.id)"
                        @update:checked="toggleProduct(product.id)"
                    />
                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg border bg-muted">
                        <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center text-sm font-bold text-primary">
                            {{ product.name.charAt(0).toUpperCase() }}
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="break-words font-medium leading-tight">{{ product.name }}</p>
                        <p v-if="product.sku" class="break-words text-xs text-muted-foreground">{{ product.sku }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <span
                                v-if="product.category"
                                class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                                :style="product.category_color
                                    ? { backgroundColor: product.category_color + '25', color: product.category_color }
                                    : {}"
                                :class="!product.category_color && 'bg-secondary'"
                            >
                                <span
                                    v-if="product.category_color"
                                    class="h-1.5 w-1.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: product.category_color }"
                                />
                                {{ product.category }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="product.is_available ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="product.is_available ? 'bg-success' : 'bg-muted-foreground'" />
                                {{ product.is_available ? 'Tersedia' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2 border-t pt-2 text-xs">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase text-muted-foreground">Stok</p>
                        <p v-if="product.has_inventory" class="tabular-nums font-medium" :class="product.current_stock <= product.min_stock ? 'text-warning' : ''">
                            {{ Number(product.current_stock).toLocaleString('id-ID') }} <span class="text-[10px] text-muted-foreground">{{ product.unit }}</span>
                        </p>
                        <p v-else class="text-muted-foreground">Tidak dilacak</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase text-muted-foreground">Harga Beli</p>
                        <p class="whitespace-nowrap font-mono tabular-nums text-muted-foreground">{{ formatCurrency(product.buy_price) }}</p>
                    </div>
                    <div class="min-w-0 text-right">
                        <p class="text-[10px] uppercase text-muted-foreground">Harga Jual</p>
                        <p class="whitespace-nowrap font-mono font-semibold tabular-nums">{{ formatCurrency(product.sell_price) }}</p>
                    </div>
                </div>

                <div class="mt-2 grid grid-cols-4 gap-1 border-t pt-2">
                    <button class="action-btn w-full !text-blue-500 hover:!bg-blue-500/15" @click="openStockDialog(product)" title="Edit Stok"><Package class="h-4 w-4" /></button>
                    <button class="action-btn w-full !text-amber-500 hover:!bg-amber-500/15" @click="openPriceDialog(product)" title="Set Harga"><DollarSign class="h-4 w-4" /></button>
                    <button class="action-btn w-full !text-violet-500 hover:!bg-violet-500/15" @click="openEditDialog(product)" title="Edit Produk"><Pencil class="h-4 w-4" /></button>
                    <button class="action-btn w-full !text-red-500 hover:!bg-red-500/15" @click="deleteProduct(product)" title="Hapus"><Trash2 class="h-4 w-4" /></button>
                </div>
            </div>

            <div v-if="filteredProducts.length === 0" class="flex flex-col items-center gap-3 rounded-lg border border-dashed py-14 text-muted-foreground">
                <Package class="h-10 w-10 opacity-30" />
                <p class="font-medium">Tidak ada produk ditemukan</p>
                <p v-if="searchQuery" class="text-sm">Coba kata kunci lain atau hapus filter</p>
                <Button v-else variant="outline" size="sm" @click="openAddDialog">
                    <Package class="h-4 w-4" />
                    Tambah Produk Pertama
                </Button>
            </div>
        </div>

        <div class="hidden overflow-hidden rounded-2xl border bg-card shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/20 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <th class="w-10 px-4 py-3">
                                <Checkbox :checked="allSelected" @update:checked="allSelected = $event" />
                            </th>
                            <TableHeadSortable
                                label="Produk"
                                sort-key="name"
                                :current-sort-key="sortKey"
                                :sort-dir="sortDir"
                                @sort="setSort"
                            />
                            <TableHeadSortable
                                label="Kategori"
                                sort-key="category"
                                :current-sort-key="sortKey"
                                :sort-dir="sortDir"
                                @sort="setSort"
                            />
                            <TableHeadSortable
                                label="Stok"
                                sort-key="current_stock"
                                :current-sort-key="sortKey"
                                :sort-dir="sortDir"
                                align="center"
                                @sort="setSort"
                            />
                            <TableHeadSortable
                                label="Harga Beli"
                                sort-key="buy_price"
                                :current-sort-key="sortKey"
                                :sort-dir="sortDir"
                                align="right"
                                @sort="setSort"
                            />
                            <TableHeadSortable
                                label="Harga Jual"
                                sort-key="sell_price"
                                :current-sort-key="sortKey"
                                :sort-dir="sortDir"
                                align="right"
                                @sort="setSort"
                            />
                            <TableHeadSortable
                                label="Status"
                                sort-key="is_available"
                                :current-sort-key="sortKey"
                                :sort-dir="sortDir"
                                align="center"
                                @sort="setSort"
                            />
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="group transition-colors hover:bg-muted/40"
                            :class="selectedProducts.includes(product.id) ? 'bg-primary/5' : ''"
                        >
                            <!-- Checkbox -->
                            <td class="px-4 py-3.5">
                                <Checkbox
                                    :checked="selectedProducts.includes(product.id)"
                                    @update:checked="toggleProduct(product.id)"
                                />
                            </td>

                            <!-- Produk -->
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg border bg-muted">
                                        <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
                                        <div v-else class="flex h-full w-full items-center justify-center text-sm font-bold text-primary">
                                            {{ product.name.charAt(0).toUpperCase() }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-semibold leading-tight">{{ product.name }}</p>
                                        <p v-if="product.sku" class="text-xs text-muted-foreground">{{ product.sku }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Kategori -->
                            <td class="px-4 py-3.5">
                                <span
                                    v-if="product.category"
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                                    :style="product.category_color
                                        ? { backgroundColor: product.category_color + '25', color: product.category_color }
                                        : {}"
                                    :class="!product.category_color && 'bg-secondary'"
                                >
                                    <span
                                        v-if="product.category_color"
                                        class="h-1.5 w-1.5 shrink-0 rounded-full"
                                        :style="{ backgroundColor: product.category_color }"
                                    />
                                    {{ product.category }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>

                            <!-- Stok -->
                            <td class="px-4 py-3.5">
                                <div v-if="product.has_inventory">
                                    <span class="inline-flex items-center gap-1 font-medium" :class="product.current_stock <= product.min_stock ? 'text-warning' : 'text-foreground'">
                                        <span v-if="product.current_stock <= product.min_stock" class="h-1.5 w-1.5 rounded-full bg-warning" />
                                        {{ Number(product.current_stock).toLocaleString('id-ID') }}
                                        <span class="text-xs font-normal text-muted-foreground">{{ product.unit }}</span>
                                    </span>
                                    <p v-if="product.current_stock <= product.min_stock" class="text-xs text-warning">Stok menipis</p>
                                </div>
                                <span v-else class="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground">Tidak dilacak</span>
                            </td>

                            <!-- Harga Beli -->
                            <td class="px-4 py-3.5 text-right">
                                <span class="font-mono text-xs text-muted-foreground">{{ formatCurrency(product.buy_price) }}</span>
                            </td>

                            <!-- Harga Jual -->
                            <td class="px-4 py-3.5 text-right">
                                <span class="font-mono text-sm font-semibold">{{ formatCurrency(product.sell_price) }}</span>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="product.is_available ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="product.is_available ? 'bg-success' : 'bg-muted-foreground'" />
                                    {{ product.is_available ? 'Tersedia' : 'Nonaktif' }}
                                </span>
                            </td>

                            <!-- Aksi -->
                            <td class="px-4 py-3.5 text-center">
                                <div class="flex items-center justify-center gap-1 opacity-80 transition-opacity group-hover:opacity-100">
                                    <button class="action-btn !text-blue-500 hover:!bg-blue-500/15" @click="openStockDialog(product)" title="Edit Stok"><Package class="h-4 w-4" /></button>
                                    <button class="action-btn !text-amber-500 hover:!bg-amber-500/15" @click="openPriceDialog(product)" title="Set Harga"><DollarSign class="h-4 w-4" /></button>
                                    <button class="action-btn !text-violet-500 hover:!bg-violet-500/15" @click="openEditDialog(product)" title="Edit Produk"><Pencil class="h-4 w-4" /></button>
                                    <button class="action-btn !text-red-500 hover:!bg-red-500/15" @click="deleteProduct(product)" title="Hapus"><Trash2 class="h-4 w-4" /></button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="filteredProducts.length === 0">
                            <td colspan="8" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-3 text-muted-foreground">
                                    <Package class="h-10 w-10 opacity-30" />
                                    <p class="font-medium">Tidak ada produk ditemukan</p>
                                    <p v-if="searchQuery" class="text-sm">Coba kata kunci lain atau hapus filter</p>
                                    <Button v-else variant="outline" size="sm" @click="openAddDialog">
                                        <Package class="h-4 w-4" />
                                        Tambah Produk Pertama
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        </div>

        <!-- ── GRID LAYOUT ── -->
        <div v-else>
            <!-- Empty state (grid) -->
            <div v-if="filteredProducts.length === 0" class="flex flex-col items-center gap-3 rounded-2xl border border-dashed py-20 text-muted-foreground">
                <Package class="h-12 w-12 opacity-30" />
                <p class="font-medium">Tidak ada produk ditemukan</p>
                <p v-if="searchQuery" class="text-sm">Coba kata kunci lain atau hapus filter</p>
                <Button v-else variant="outline" size="sm" @click="openAddDialog">
                    <Package class="h-4 w-4" />
                    Tambah Produk Pertama
                </Button>
            </div>

            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                <div
                    v-for="product in filteredProducts"
                    :key="product.id"
                    class="product-card group relative flex flex-col rounded-2xl border bg-card shadow-sm transition-all duration-200 hover:shadow-md overflow-hidden cursor-pointer"
                    :class="selectedProducts.includes(product.id) ? 'ring-2 ring-primary' : ''"
                    @click="toggleProduct(product.id)"
                >
                    <!-- Checkbox overlay -->
                    <div class="absolute left-2 top-2 z-10">
                        <div
                            class="flex h-5 w-5 items-center justify-center rounded-full border-2 bg-background shadow transition-all"
                            :class="selectedProducts.includes(product.id)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border opacity-100 md:opacity-0 md:group-hover:opacity-100'"
                        >
                            <Check v-if="selectedProducts.includes(product.id)" class="h-3 w-3" />
                        </div>
                    </div>

                    <!-- Status badge -->
                    <div class="absolute right-2 top-2 z-10">
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="product.is_available
                                ? 'bg-success/10 text-success'
                                : 'bg-muted text-muted-foreground'"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="product.is_available ? 'bg-success' : 'bg-muted-foreground'" />
                            {{ product.is_available ? 'Tersedia' : 'Nonaktif' }}
                        </span>
                    </div>

                    <!-- Product Image -->
                    <div class="aspect-square w-full overflow-hidden bg-muted">
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            :alt="product.name"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <div v-else class="flex h-full w-full flex-col items-center justify-center gap-2 text-muted-foreground">
                            <span class="text-4xl font-bold text-primary/20">{{ product.name.charAt(0).toUpperCase() }}</span>
                            <Package class="h-8 w-8 opacity-20" />
                        </div>
                    </div>

                    <!-- Card Content -->
                    <div class="flex flex-1 flex-col gap-2 p-3">
                        <!-- Name & Category -->
                        <div>
                            <p class="line-clamp-2 text-sm font-semibold leading-snug">{{ product.name }}</p>
                            <span
                                v-if="product.category"
                                class="mt-1 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium"
                                :style="product.category_color
                                    ? { backgroundColor: product.category_color + '25', color: product.category_color }
                                    : {}"
                                :class="!product.category_color && 'bg-secondary'"
                            >
                                <span
                                    v-if="product.category_color"
                                    class="h-1.5 w-1.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: product.category_color }"
                                />
                                {{ product.category }}
                            </span>
                        </div>

                        <!-- Price -->
                        <div class="mt-auto">
                            <p class="font-mono text-base font-bold text-primary">{{ formatCurrency(product.sell_price) }}</p>
                            <p class="font-mono text-xs text-muted-foreground">Beli: {{ formatCurrency(product.buy_price) }}</p>
                        </div>

                        <!-- Stock -->
                        <div class="flex items-center justify-between rounded-lg bg-muted/50 px-2.5 py-1.5">
                            <span class="text-xs text-muted-foreground">Stok</span>
                            <span v-if="product.has_inventory"
                                class="text-xs font-semibold"
                                :class="product.current_stock <= product.min_stock ? 'text-warning' : 'text-foreground'"
                            >
                                <span v-if="product.current_stock <= product.min_stock" class="mr-0.5">⚠</span>
                                {{ Number(product.current_stock).toLocaleString('id-ID') }} {{ product.unit }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground italic">Tidak dilacak</span>
                        </div>

                        <!-- Actions: ikon konsisten dengan tabel -->
                        <div class="flex items-center gap-1 pt-1" @click.stop>
                            <button class="action-btn flex-1 !text-blue-500 hover:!bg-blue-500/15" @click="openStockDialog(product)" title="Edit Stok">
                                <Package class="h-3.5 w-3.5" />
                            </button>
                            <button class="action-btn flex-1 !text-amber-500 hover:!bg-amber-500/15" @click="openPriceDialog(product)" title="Set Harga">
                                <DollarSign class="h-3.5 w-3.5" />
                            </button>
                            <button class="action-btn flex-1 !text-violet-500 hover:!bg-violet-500/15" @click="openEditDialog(product)" title="Edit Produk">
                                <Pencil class="h-3.5 w-3.5" />
                            </button>
                            <button class="action-btn flex-1 !text-red-500 hover:!bg-red-500/15" @click="deleteProduct(product)" title="Hapus">
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── DIALOGS ── -->

        <!-- Copy Dialog -->
        <Dialog :open="showCopyDialog" @update:open="showCopyDialog = $event">
            <DialogContent class="w-[95vw] max-w-lg">
                <DialogHeader>
                    <DialogTitle>Salin Produk ke Toko Lain</DialogTitle>
                    <DialogDescription>
                        Menyalin <strong>{{ selectedProducts.length }} produk</strong> dari
                        <strong>{{ store.name }}</strong> ke toko yang dipilih.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div>
                        <p class="mb-2 text-sm font-semibold">Toko Tujuan</p>
                        <div v-if="other_stores.length > 0" class="max-h-52 space-y-1.5 overflow-y-auto rounded-xl border p-2">
                            <label
                                v-for="otherStore in other_stores"
                                :key="otherStore.id"
                                class="flex cursor-pointer items-center gap-3 rounded-lg p-2.5 transition-colors hover:bg-accent"
                                :class="selectedTargetStores.includes(otherStore.id) ? 'bg-primary/5' : ''"
                            >
                                <Checkbox
                                    :checked="selectedTargetStores.includes(otherStore.id)"
                                    @update:checked="toggleTargetStore(otherStore.id)"
                                />
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-secondary text-sm font-bold">
                                    {{ otherStore.name.charAt(0) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="truncate text-sm font-medium">{{ otherStore.name }}</p>
                                    <p v-if="otherStore.city" class="text-xs text-muted-foreground">{{ otherStore.city }}</p>
                                </div>
                                <Badge v-if="!otherStore.is_active" variant="secondary" class="text-xs shrink-0">Nonaktif</Badge>
                            </label>
                        </div>
                        <div v-else class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                            Belum ada toko lain di brand ini.
                        </div>
                    </div>

                    <div class="rounded-xl border bg-muted/30 p-4 space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Opsi Salin</p>
                        <label class="flex cursor-pointer items-center gap-3">
                            <Checkbox v-model:checked="copyPrices" />
                            <div>
                                <p class="text-sm font-medium">Salin harga beli & jual</p>
                                <p class="text-xs text-muted-foreground">Harga dari toko ini akan diterapkan di toko tujuan</p>
                            </div>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3">
                            <Checkbox v-model:checked="copyStock" />
                            <div>
                                <p class="text-sm font-medium">Salin jumlah stok</p>
                                <p class="text-xs text-muted-foreground">Jumlah stok saat ini akan disalin ke toko tujuan</p>
                            </div>
                        </label>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showCopyDialog = false" :disabled="processing">Batal</Button>
                    <Button @click="submitCopy" :disabled="processing || selectedTargetStores.length === 0">
                        <Copy class="h-4 w-4" />
                        Salin Sekarang
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Stock Dialog -->
        <Dialog :open="showStockDialog" @update:open="showStockDialog = $event">
            <DialogContent class="w-[95vw] max-w-sm">
                <DialogHeader>
                    <DialogTitle>Edit Stok</DialogTitle>
                    <DialogDescription>
                        <strong>{{ stockForm.product_name }}</strong> — {{ store.name }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="rounded-xl border bg-muted/30 p-4 space-y-4">
                        <div>
                            <Label for="current_stock" class="mb-1.5 block text-sm font-medium">Stok Saat Ini</Label>
                            <Input id="current_stock" v-model.number="stockForm.current_stock" type="number" min="0" step="1" class="text-lg font-semibold" />
                        </div>
                        <div>
                            <Label for="min_stock" class="mb-1.5 block text-sm font-medium">Stok Minimum <span class="text-muted-foreground font-normal">(batas peringatan)</span></Label>
                            <Input id="min_stock" v-model.number="stockForm.min_stock" type="number" min="0" step="1" />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Sistem akan menampilkan peringatan stok menipis jika jumlah stok di bawah nilai minimum.
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showStockDialog = false" :disabled="processing">Batal</Button>
                    <Button @click="submitStock" :disabled="processing">
                        <Check class="h-4 w-4" />
                        Simpan Stok
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Price Dialog -->
        <Dialog :open="showPriceDialog" @update:open="showPriceDialog = $event">
            <DialogContent class="w-[95vw] max-w-sm">
                <DialogHeader>
                    <DialogTitle>Harga Khusus Toko</DialogTitle>
                    <DialogDescription>
                        <strong>{{ priceForm.product_name }}</strong> — {{ store.name }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="rounded-xl border bg-muted/30 p-4 space-y-4">
                        <div>
                            <Label for="buy_price" class="mb-1.5 block text-sm font-medium">Harga Beli (Rp)</Label>
                            <Input id="buy_price" v-model.number="priceForm.buy_price" type="number" min="0" step="100" />
                        </div>
                        <div>
                            <Label for="sell_price" class="mb-1.5 block text-sm font-medium">Harga Jual (Rp)</Label>
                            <Input id="sell_price" v-model.number="priceForm.sell_price" type="number" min="0" step="100" />
                        </div>
                    </div>

                    <!-- Margin indicator -->
                    <div v-if="priceForm.sell_price && priceForm.buy_price" class="flex items-center justify-between rounded-lg bg-primary/5 px-4 py-2.5">
                        <span class="text-sm text-muted-foreground">Margin keuntungan</span>
                        <span class="font-semibold text-primary">
                            {{ ((priceForm.sell_price - priceForm.buy_price) / priceForm.buy_price * 100).toFixed(1) }}%
                        </span>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Harga khusus ini hanya berlaku untuk toko ini. Jika tidak diatur, toko menggunakan harga default brand.
                    </p>
                </div>

                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button v-if="priceForm.has_custom_price" variant="outline" class="w-full sm:w-auto" @click="resetPrice(priceForm.product_id)" :disabled="processing">
                        Reset Default
                    </Button>
                    <Button variant="outline" class="w-full sm:w-auto" @click="showPriceDialog = false" :disabled="processing">Batal</Button>
                    <Button class="w-full sm:w-auto" @click="submitPrice" :disabled="processing">
                        <Check class="h-4 w-4" />
                        Simpan Harga
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Product Dialog -->
        <Dialog :open="showAddDialog" @update:open="showAddDialog = $event">
            <DialogContent class="w-[95vw] max-w-2xl">
                <DialogHeader class="pr-8">
                    <DialogTitle>Tambah Produk Baru</DialogTitle>
                    <DialogDescription>
                        Produk baru akan ditambahkan ke brand dan langsung tersedia di <strong>{{ store.name }}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[60vh] overflow-y-auto -mx-6 px-6">
                    <div class="space-y-5 pb-2">
                        <!-- Mode Produk -->
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="rounded-xl border-2 p-3 text-left transition-all"
                                :class="!addForm.is_rental_package ? 'border-primary bg-primary/5' : 'border-input hover:border-primary/40'"
                                @click="addForm.is_rental_package = false"
                            >
                                <p class="text-sm font-semibold">Produk Biasa</p>
                                <p class="text-xs text-muted-foreground">Makanan, minuman, item satuan</p>
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border-2 p-3 text-left transition-all"
                                :class="addForm.is_rental_package ? 'border-primary bg-primary/5' : 'border-input hover:border-primary/40'"
                                @click="addForm.is_rental_package = true"
                            >
                                <p class="text-sm font-semibold">Paket Rental PS</p>
                                <p class="text-xs text-muted-foreground">Dijual per durasi main</p>
                            </button>
                        </div>

                        <!-- Identitas -->
                        <div class="rounded-xl border bg-muted/20 p-4 space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Identitas Produk</p>

                            <!-- Foto Produk -->
                            <div>
                                <Label class="mb-1.5 block text-sm font-medium">Foto Produk</Label>
                                <div class="flex items-center gap-4">
                                    <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl border-2 border-dashed border-border bg-muted transition-colors hover:border-primary">
                                        <img
                                            v-if="addImagePreview"
                                            :src="addImagePreview"
                                            alt="Preview"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="flex h-full w-full flex-col items-center justify-center gap-1 text-muted-foreground">
                                            <Package class="h-6 w-6" />
                                            <span class="text-xs">Foto</span>
                                        </div>
                                        <button
                                            v-if="addImagePreview"
                                            type="button"
                                            class="absolute right-1 top-1 cursor-pointer rounded-full bg-destructive p-0.5 text-white shadow"
                                            @click="removeAddImage"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </div>
                                    <div class="flex-1">
                                        <label class="cursor-pointer">
                                            <span class="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent">
                                                <Package class="h-4 w-4" />
                                                {{ addImagePreview ? 'Ganti Foto' : 'Pilih Foto' }}
                                            </span>
                                            <input
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp"
                                                class="hidden"
                                                @change="onAddImageChange"
                                            />
                                        </label>
                                        <p class="mt-1.5 text-xs text-muted-foreground">JPG, PNG, atau WebP · Maks 2MB</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="col-span-2">
                                    <Label for="add_name" class="mb-1.5 block text-sm font-medium">Nama Produk *</Label>
                                    <Input id="add_name" v-model="addForm.name" placeholder="Contoh: Nasi Goreng Spesial" />
                                </div>
                                <div>
                                    <Label for="add_category" class="mb-1.5 block text-sm font-medium">Kategori</Label>
                                    <select
                                        id="add_category"
                                        v-model="addForm.category_id"
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option :value="null">Tanpa Kategori</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                </div>
                                <div v-if="!addForm.is_rental_package">
                                    <Label for="add_unit" class="mb-1.5 block text-sm font-medium">Satuan *</Label>
                                    <Input id="add_unit" v-model="addForm.unit" placeholder="pcs, porsi, kg..." />
                                </div>
                                <div>
                                    <Label for="add_sku" class="mb-1.5 block text-sm font-medium">SKU</Label>
                                    <Input id="add_sku" v-model="addForm.sku" placeholder="NS-001" />
                                </div>
                                <div>
                                    <Label for="add_description" class="mb-1.5 block text-sm font-medium">Deskripsi</Label>
                                    <Input id="add_description" v-model="addForm.description" placeholder="Deskripsi singkat..." />
                                </div>
                            </div>
                        </div>

                        <!-- Harga -->
                        <div class="rounded-xl border bg-muted/20 p-4 space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Harga</p>
                            <div class="grid gap-3" :class="addForm.is_rental_package ? 'grid-cols-1' : 'grid-cols-2'">
                                <div v-if="!addForm.is_rental_package">
                                    <Label for="add_buy_price" class="mb-1.5 block text-sm font-medium">Harga Beli *</Label>
                                    <MoneyInput id="add_buy_price" v-model="addForm.buy_price" placeholder="0" />
                                </div>
                                <div>
                                    <Label for="add_sell_price" class="mb-1.5 block text-sm font-medium">{{ addForm.is_rental_package ? 'Harga Paket *' : 'Harga Jual *' }}</Label>
                                    <MoneyInput id="add_sell_price" v-model="addForm.sell_price" placeholder="0" />
                                </div>
                            </div>
                            <div v-if="!addForm.is_rental_package && addForm.sell_price && addForm.buy_price" class="flex items-center justify-between rounded-lg bg-primary/5 px-3 py-2">
                                <span class="text-sm text-muted-foreground">Margin</span>
                                <span class="font-semibold text-primary">{{ addMarginPercentage }}%</span>
                            </div>
                        </div>

                        <!-- Durasi Paket Rental -->
                        <div v-if="addForm.is_rental_package" class="rounded-xl border bg-muted/20 p-4 space-y-2">
                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Durasi Paket *</Label>
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model.number="addForm.rental_duration_value"
                                    type="number"
                                    min="1"
                                    step="any"
                                    placeholder="Contoh: 1"
                                    class="flex-1"
                                />
                                <select
                                    v-model="addForm.rental_duration_unit"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="jam">Jam</option>
                                    <option value="menit">Menit</option>
                                </select>
                            </div>
                            <p v-if="addRentalMinutes" class="text-xs font-semibold text-muted-foreground">= {{ addRentalMinutes }} menit</p>
                        </div>

                        <!-- Stok -->
                        <div v-if="!addForm.is_rental_package" class="rounded-xl border bg-muted/20 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lacak Stok</p>
                                    <p class="text-xs text-muted-foreground mt-0.5">Pantau jumlah stok produk ini</p>
                                </div>
                                <Checkbox v-model:checked="addForm.track_stock" />
                            </div>
                            <div v-if="addForm.track_stock" class="grid grid-cols-2 gap-3 pt-1">
                                <div>
                                    <Label for="add_current_stock" class="mb-1.5 block text-sm font-medium">Stok Awal</Label>
                                    <Input id="add_current_stock" v-model.number="addForm.current_stock" type="number" min="0" step="1" />
                                </div>
                                <div>
                                    <Label for="add_min_stock" class="mb-1.5 block text-sm font-medium">Stok Minimum</Label>
                                    <Input id="add_min_stock" v-model.number="addForm.min_stock" type="number" min="0" step="1" />
                                </div>
                            </div>
                        </div>

                        <!-- Ketersediaan -->
                        <div class="flex items-center justify-between rounded-xl border bg-muted/20 p-4">
                            <div>
                                <p class="text-sm font-medium">Tersedia untuk Dijual</p>
                                <p class="text-xs text-muted-foreground">Tampilkan di menu kasir</p>
                            </div>
                            <Checkbox v-model:checked="addForm.is_available" />
                        </div>
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button variant="outline" @click="showAddDialog = false" :disabled="processing">Batal</Button>
                    <Button @click="submitAdd" :disabled="processing || !addForm.name || !addForm.unit || (addForm.is_rental_package && !addRentalMinutes)">
                        <Package class="h-4 w-4" />
                        Tambah Produk
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Product Dialog -->
        <Dialog :open="showEditDialog" @update:open="showEditDialog = $event">
            <DialogContent class="w-[95vw] max-w-2xl">
                <DialogHeader class="pr-8">
                    <DialogTitle>Edit Produk</DialogTitle>
                    <DialogDescription>
                        Info produk (nama, SKU, kategori) berlaku untuk <strong>semua toko</strong>. Harga &amp; stok khusus untuk <strong>{{ store.name }}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[60vh] overflow-y-auto -mx-6 px-6">
                <div class="space-y-4 pb-2">
                    <div class="rounded-xl border bg-muted/20 p-4 space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Identitas Produk</p>

                        <!-- Foto Produk -->
                        <div>
                            <Label class="mb-1.5 block text-sm font-medium">Foto Produk</Label>
                            <div class="flex items-center gap-4">
                                <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl border-2 border-dashed border-border bg-muted transition-colors hover:border-primary">
                                    <img
                                        v-if="editImagePreview || editCurrentImageUrl"
                                        :src="editImagePreview ?? editCurrentImageUrl ?? ''"
                                        alt="Preview"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full flex-col items-center justify-center gap-1 text-muted-foreground">
                                        <Package class="h-6 w-6" />
                                        <span class="text-xs">Foto</span>
                                    </div>
                                    <button
                                        v-if="editImagePreview || editCurrentImageUrl"
                                        type="button"
                                        class="absolute right-1 top-1 cursor-pointer rounded-full bg-destructive p-0.5 text-white shadow"
                                        @click="removeEditImage(); editCurrentImageUrl = null"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                                <div class="flex-1">
                                    <label class="cursor-pointer">
                                        <span class="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent">
                                            <Package class="h-4 w-4" />
                                            {{ (editImagePreview || editCurrentImageUrl) ? 'Ganti Foto' : 'Pilih Foto' }}
                                        </span>
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            class="hidden"
                                            @change="onEditImageChange"
                                        />
                                    </label>
                                    <p class="mt-1.5 text-xs text-muted-foreground">JPG, PNG, atau WebP · Maks 2MB</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <Label for="edit_name" class="mb-1.5 block text-sm font-medium">Nama Produk *</Label>
                                <Input id="edit_name" v-model="editForm.name" placeholder="Nama produk" />
                            </div>
                            <div>
                                <Label for="edit_category" class="mb-1.5 block text-sm font-medium">Kategori</Label>
                                <select
                                    id="edit_category"
                                    v-model="editForm.category_id"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option :value="null">Tanpa Kategori</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <Label for="edit_unit" class="mb-1.5 block text-sm font-medium">Satuan *</Label>
                                <Input id="edit_unit" v-model="editForm.unit" placeholder="pcs, porsi, kg..." />
                            </div>
                            <div>
                                <Label for="edit_sku" class="mb-1.5 block text-sm font-medium">SKU</Label>
                                <Input id="edit_sku" v-model="editForm.sku" placeholder="Kode SKU" />
                            </div>
                            <div>
                                <Label for="edit_description" class="mb-1.5 block text-sm font-medium">Deskripsi</Label>
                                <Input id="edit_description" v-model="editForm.description" placeholder="Deskripsi singkat" />
                            </div>
                        </div>
                    </div>

                    <!-- Harga -->
                    <div class="rounded-xl border bg-muted/20 p-4 space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Harga di Toko Ini</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="edit_buy_price" class="mb-1.5 block text-sm font-medium">Harga Beli (Rp)</Label>
                                <Input id="edit_buy_price" v-model.number="editForm.buy_price" type="number" min="0" step="100" />
                            </div>
                            <div>
                                <Label for="edit_sell_price" class="mb-1.5 block text-sm font-medium">Harga Jual (Rp)</Label>
                                <Input id="edit_sell_price" v-model.number="editForm.sell_price" type="number" min="0" step="100" />
                            </div>
                        </div>
                        <div v-if="editForm.sell_price && editForm.buy_price" class="flex items-center justify-between rounded-lg bg-primary/5 px-3 py-2">
                            <span class="text-sm text-muted-foreground">Margin</span>
                            <span class="font-semibold text-primary">{{ editMarginPercentage }}%</span>
                        </div>
                    </div>

                    <!-- Stok -->
                    <div class="rounded-xl border bg-muted/20 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lacak Stok</p>
                                <p class="text-xs text-muted-foreground mt-0.5">Pantau jumlah stok produk ini</p>
                            </div>
                            <Checkbox v-model:checked="editForm.track_stock" />
                        </div>
                        <div v-if="editForm.track_stock" class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <Label for="edit_current_stock" class="mb-1.5 block text-sm font-medium">Stok Saat Ini</Label>
                                <Input id="edit_current_stock" v-model.number="editForm.current_stock" type="number" min="0" step="1" class="text-lg font-semibold" />
                            </div>
                            <div>
                                <Label for="edit_min_stock" class="mb-1.5 block text-sm font-medium">Stok Minimum</Label>
                                <Input id="edit_min_stock" v-model.number="editForm.min_stock" type="number" min="0" step="1" />
                            </div>
                        </div>
                    </div>

                    <!-- Ketersediaan -->
                    <div class="flex items-center justify-between rounded-xl border bg-muted/20 p-4">
                        <div>
                            <p class="text-sm font-medium">Tersedia untuk Dijual</p>
                            <p class="text-xs text-muted-foreground">Tampilkan di menu kasir</p>
                        </div>
                        <Checkbox v-model:checked="editForm.is_available" />
                    </div>
                </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button variant="outline" @click="showEditDialog = false" :disabled="processing">Batal</Button>
                    <Button @click="submitEdit" :disabled="processing || !editForm.name || !editForm.unit">
                        <Check class="h-4 w-4" />
                        Simpan Perubahan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Confirm & Alert Dialogs -->
        <ConfirmDialog
            :open="confirmState.open"
            :title="confirmState.title"
            :description="confirmState.description"
            :variant="confirmState.variant"
            :confirm-label="confirmState.confirmLabel"
            @update:open="(v) => { if (!v) closeConfirm() }"
            @confirm="runConfirm"
        />
        <AlertDialog
            :open="alertState.open"
            :message="alertState.message"
            :variant="alertState.variant"
            @update:open="(v) => { alertState.open = v }"
        />
    </AdminLayout>
</template>

<style scoped>
.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    color: currentColor;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s;
}
.action-btn:hover {
    background-color: hsl(var(--accent));
    border-color: hsl(var(--border));
}

.product-card {
    user-select: none;
}
</style>
