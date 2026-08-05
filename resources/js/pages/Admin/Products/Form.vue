<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { ArrowLeft, Loader2, Package, Plus, Trash2, GripVertical, Gamepad2, ShoppingBag } from 'lucide-vue-next'

interface ModifierOption {
    id?: number
    name: string
    price_extra: number
    is_active: boolean
    is_available: boolean
}

interface ModifierGroup {
    id?: number
    name: string
    is_required: boolean
    min_select: number
    max_select: number
    options: ModifierOption[]
}

interface ProductData {
    id: number
    name: string
    slug: string
    category_id: number | null
    sku: string | null
    description: string | null
    unit: string
    track_stock: boolean
    is_available: boolean
    is_active: boolean
    buy_price: number
    sell_price: number
    discount_percent: number
    is_rental_package: boolean
    rental_duration_minutes: number | null
    included_items_json: any[]
    modifiers?: ModifierGroup[]
}

interface Category {
    id: number
    name: string
}

interface PickableProduct {
    id: number
    name: string
    unit: string
}

interface IncludedItem {
    product_id: number
    product_name: string
    qty: number
}

const props = defineProps<{
    product: ProductData | null
    categories: Category[]
    allProducts: PickableProduct[]
}>()

const isEdit = computed(() => !!props.product)

// ─── Step 1: Choose type ────────────────────────────────────────────────────────
// On edit, skip step 1 and go straight to form
const typeChosen = ref(isEdit.value)
const productType = ref<'regular' | 'rental'>(
    props.product?.is_rental_package ? 'rental' : 'regular'
)

function chooseType(type: 'regular' | 'rental') {
    productType.value = type
    typeChosen.value = true
    form.is_rental_package = type === 'rental'
    if (type === 'rental') {
        form.track_stock = false    // rental packages don't track stock
        form.unit = 'sesi'
    } else {
        form.track_stock = true
        form.unit = 'pcs'
    }
}

const pageTitle = computed(() => {
    if (isEdit.value) return `Edit: ${props.product!.name}`
    if (!typeChosen.value) return 'Tambah Produk Baru'
    return productType.value === 'rental' ? 'Tambah Paket Rental PS' : 'Tambah Produk Biasa'
})

// ─── Form ───────────────────────────────────────────────────────────────────────
const form = useForm({
    name:         props.product?.name         ?? '',
    image:        null as File | null,
    category_id:  props.product?.category_id  ?? null,
    sku:          props.product?.sku          ?? '',
    description:  props.product?.description  ?? '',
    unit:         props.product?.unit         ?? 'pcs',
    track_stock:  props.product?.track_stock  ?? true,
    is_available: props.product?.is_available ?? true,
    buy_price:    props.product?.buy_price    ?? 0,
    sell_price:   props.product?.sell_price   ?? 0,
    discount_percent: props.product?.discount_percent ?? 0,
    is_rental_package: props.product?.is_rental_package ?? false,
    rental_duration_minutes: props.product?.rental_duration_minutes ?? null,
    included_items_json: (props.product?.included_items_json ?? []) as Array<{ product_id: number; product_name: string; qty: number }>,
    modifiers:    (props.product?.modifiers ?? []) as ModifierGroup[],
})

// Keep form.is_rental_package in sync with productType
watch(productType, (val) => {
    form.is_rental_package = val === 'rental'
})

// ─── Modifier helpers ──────────────────────────────────────────────────────────
function addModifierGroup() {
    form.modifiers.push({
        name: '', is_required: false, min_select: 0, max_select: 1,
        options: [{ name: '', price_extra: 0, is_active: true, is_available: true }]
    })
}
function removeModifierGroup(i: number) { form.modifiers.splice(i, 1) }
function addOption(gi: number) {
    form.modifiers[gi].options.push({ name: '', price_extra: 0, is_active: true, is_available: true })
}
function removeOption(gi: number, oi: number) { form.modifiers[gi].options.splice(oi, 1) }

// ─── Included items — product picker ──────────────────────────────────────────
const pickerSearch = ref('')
const pickerQty = ref(1)
const pickerSelectedId = ref<number | null>(null)
const showPickerDropdown = ref(false)

const filteredPickable = computed(() => {
    const q = pickerSearch.value.toLowerCase()
    return props.allProducts.filter(p =>
        !q || p.name.toLowerCase().includes(q)
    ).slice(0, 20)
})

const pickerSelectedProduct = computed(() =>
    props.allProducts.find(p => p.id === pickerSelectedId.value) ?? null
)

function selectPickerProduct(p: PickableProduct) {
    pickerSelectedId.value = p.id
    pickerSearch.value = p.name
    showPickerDropdown.value = false
}

function addIncludedItem() {
    if (!pickerSelectedId.value || !pickerSelectedProduct.value) return
    // Prevent duplicates
    const exists = form.included_items_json.find(
        (i: IncludedItem) => i.product_id === pickerSelectedId.value
    )
    if (exists) {
        exists.qty += pickerQty.value
    } else {
        form.included_items_json.push({
            product_id: pickerSelectedId.value,
            product_name: pickerSelectedProduct.value.name,
            qty: pickerQty.value,
        })
    }
    // Reset picker
    pickerSelectedId.value = null
    pickerSearch.value = ''
    pickerQty.value = 1
}
function removeIncludedItem(i: number) { form.included_items_json.splice(i, 1) }

// ─── Submit ────────────────────────────────────────────────────────────────────
function submit() {
    if (isEdit.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/admin/products/${props.product!.id}`)
    } else {
        form.post('/admin/products')
    }
}

const units = ['pcs', 'kg', 'gram', 'liter', 'ml', 'porsi', 'pack', 'box', 'lusin', 'meter', 'sesi']

// Duration quick pick
const durationPresets = [30, 60, 90, 120, 180, 300]
function formatDuration(m: number) {
    const h = Math.floor(m / 60)
    const min = m % 60
    if (h > 0 && min > 0) return `${h}j ${min}m`
    if (h > 0) return `${h} Jam`
    return `${m} Menit`
}
</script>

<template>
    <AdminLayout :title="pageTitle">
        <div class="mx-auto max-w-2xl">
            <!-- ─── Back button in body ─────────────────────────────────── -->
            <div class="mb-5 flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3">
                <Link href="/admin/products">
                    <Button variant="outline" size="sm" class="gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Kembali ke Daftar Produk
                    </Button>
                </Link>
                <div class="min-w-0 break-words text-sm text-muted-foreground">
                    <span v-if="!typeChosen">Pilih tipe produk terlebih dahulu</span>
                    <span v-else-if="productType === 'rental'" class="flex items-center gap-1">
                        <Gamepad2 class="h-3.5 w-3.5 text-primary" />
                        Mode: <strong class="text-primary">Paket Rental PS</strong>
                    </span>
                    <span v-else class="flex items-center gap-1">
                        <ShoppingBag class="h-3.5 w-3.5 text-primary" />
                        Mode: <strong class="text-primary">Produk Biasa</strong>
                    </span>
                </div>
            </div>

            <!-- ─── STEP 1: Choose type (only on create) ──────────────── -->
            <div v-if="!typeChosen" class="space-y-4">
                <div class="text-center mb-6">
                    <h1 class="text-xl font-bold">Anda ingin menambahkan apa?</h1>
                    <p class="text-sm text-muted-foreground mt-1">Pilih tipe produk agar form yang ditampilkan sesuai</p>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <!-- Produk Biasa -->
                    <button
                        type="button"
                        class="group rounded-2xl border-2 border-input bg-card p-6 text-left transition-all hover:border-primary hover:bg-primary/5 hover:shadow-md"
                        @click="chooseType('regular')"
                    >
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 text-blue-600 mb-4 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <ShoppingBag class="h-6 w-6" />
                        </div>
                        <h3 class="font-bold text-base mb-1">Produk Biasa</h3>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Makanan, minuman, snack, atau item yang dijual satuan. Bisa memiliki varian/modifier, tracking stok, dan kategori.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            <span class="rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-semibold text-blue-700">Makanan & Minuman</span>
                            <span class="rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-semibold text-blue-700">Merchandise</span>
                            <span class="rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-semibold text-blue-700">Aksesori</span>
                        </div>
                    </button>

                    <!-- Paket Rental PS -->
                    <button
                        type="button"
                        class="group rounded-2xl border-2 border-input bg-card p-6 text-left transition-all hover:border-primary hover:bg-primary/5 hover:shadow-md"
                        @click="chooseType('rental')"
                    >
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-100 text-violet-600 mb-4 group-hover:bg-violet-600 group-hover:text-white transition-colors">
                            <Gamepad2 class="h-6 w-6" />
                        </div>
                        <h3 class="font-bold text-base mb-1">Paket Rental PS 🎮</h3>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Paket sewa PlayStation dengan durasi tertentu. Bisa menyertakan produk tambahan (makan, minum) yang otomatis masuk keranjang saat rental dimulai.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            <span class="rounded-full bg-violet-50 border border-violet-200 px-2 py-0.5 text-[10px] font-semibold text-violet-700">Paket 1 Jam</span>
                            <span class="rounded-full bg-violet-50 border border-violet-200 px-2 py-0.5 text-[10px] font-semibold text-violet-700">Paket + Makan</span>
                            <span class="rounded-full bg-violet-50 border border-violet-200 px-2 py-0.5 text-[10px] font-semibold text-violet-700">Paket VIP</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ─── STEP 2: Form ───────────────────────────────────────── -->
            <form v-else @submit.prevent="submit" class="space-y-6">

                <!-- ── Rental Package Banner ── -->
                <div v-if="productType === 'rental'" class="flex flex-wrap items-center gap-3 rounded-xl border-2 border-violet-300 bg-violet-50 px-4 py-3">
                    <Gamepad2 class="h-5 w-5 text-violet-600 shrink-0" />
                    <div class="min-w-0 flex-1 break-words">
                        <p class="text-sm font-bold text-violet-800">Mode: Paket Rental PS</p>
                        <p class="text-xs text-violet-600">Form ini untuk membuat paket sewa PS yang bisa dipilih kasir saat memulai rental.</p>
                    </div>
                    <button
                        v-if="!isEdit"
                        type="button"
                        class="ml-auto text-xs text-violet-500 underline hover:text-violet-700"
                        @click="typeChosen = false"
                    >Ganti tipe</button>
                </div>
                <div v-else-if="!isEdit" class="flex flex-wrap items-center gap-3 rounded-xl border bg-muted/40 px-4 py-3">
                    <ShoppingBag class="h-5 w-5 text-muted-foreground shrink-0" />
                    <p class="min-w-0 flex-1 break-words text-sm text-muted-foreground">Mode: <strong>Produk Biasa</strong> — Makanan, minuman, atau item satuan</p>
                    <button
                        type="button"
                        class="text-xs text-muted-foreground underline hover:text-foreground"
                        @click="typeChosen = false"
                    >Ganti tipe</button>
                </div>

                <!-- ── Card: Info Utama ── -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg text-white" :class="productType === 'rental' ? 'bg-violet-600' : 'bg-primary'">
                                <component :is="productType === 'rental' ? Gamepad2 : Package" class="h-4 w-4" />
                            </div>
                            <div>
                                <CardTitle>{{ productType === 'rental' ? 'Informasi Paket' : 'Informasi Produk' }}</CardTitle>
                                <CardDescription>{{ productType === 'rental' ? 'Nama dan deskripsi paket rental' : 'Detail dasar produk' }}</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">
                                {{ productType === 'rental' ? 'Nama Paket' : 'Nama Produk' }}
                                <span class="text-destructive"> *</span>
                            </Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                :placeholder="productType === 'rental' ? 'Paket 2 Jam + Makan' : 'Kopi Susu'"
                                :disabled="form.processing"
                                required
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="image">Foto (opsional)</Label>
                            <Input
                                id="image"
                                type="file"
                                accept="image/*"
                                :disabled="form.processing"
                                @change="(e: Event) => form.image = (e.target as HTMLInputElement).files?.[0] || null"
                            />
                            <p v-if="form.errors.image" class="text-xs text-destructive">{{ form.errors.image }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="category_id">Kategori</Label>
                                <select
                                    id="category_id"
                                    v-model="form.category_id"
                                    :disabled="form.processing"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option :value="null">Tanpa Kategori</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label for="sku">{{ productType === 'rental' ? 'Kode Paket (opsional)' : 'SKU' }}</Label>
                                <Input
                                    id="sku"
                                    v-model="form.sku"
                                    :placeholder="productType === 'rental' ? 'PKT-2J' : 'KSS-001'"
                                    :disabled="form.processing"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">{{ productType === 'rental' ? 'Deskripsi Paket (opsional)' : 'Deskripsi' }}</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                :placeholder="productType === 'rental' ? 'Nikmati 2 jam gaming + 1 porsi mie goreng dan 1 es teh manis...' : 'Deskripsi singkat produk...'"
                                :rows="3"
                                :disabled="form.processing"
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- ── Card: Paket Rental — Durasi & Harga ── -->
                <Card v-if="productType === 'rental'" class="border-violet-200 bg-violet-50/30">
                    <CardHeader>
                        <CardTitle class="text-violet-800">⏱️ Durasi & Harga Paket</CardTitle>
                        <CardDescription>Tentukan berapa lama dan berapa harga paket ini. Harga sudah termasuk durasi + semua item yang disertakan.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <!-- Duration picker -->
                        <div class="space-y-2">
                            <Label>Durasi Paket <span class="text-destructive"> *</span></Label>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <button
                                    v-for="d in durationPresets"
                                    :key="d"
                                    type="button"
                                    class="rounded-lg border-2 py-2.5 text-sm font-semibold transition-all"
                                    :class="form.rental_duration_minutes === d
                                        ? 'border-violet-500 bg-violet-500 text-white'
                                        : 'border-input hover:border-violet-300 hover:bg-violet-50'"
                                    @click="form.rental_duration_minutes = d"
                                >
                                    {{ formatDuration(d) }}
                                </button>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <Input
                                    :value="form.rental_duration_minutes ?? ''"
                                    type="number"
                                    min="1"
                                    placeholder="Atau ketik durasi custom (menit)"
                                    :disabled="form.processing"
                                    class="w-full flex-1 tabular-nums sm:w-auto"
                                    @input="form.rental_duration_minutes = ($event.target as HTMLInputElement).valueAsNumber || null"
                                />
                                <span class="text-xs text-muted-foreground whitespace-nowrap">menit</span>
                                <span v-if="form.rental_duration_minutes" class="text-xs font-semibold text-violet-700 whitespace-nowrap">
                                    = {{ formatDuration(form.rental_duration_minutes) }}
                                </span>
                            </div>
                            <p v-if="form.errors.rental_duration_minutes" class="text-xs text-destructive">{{ form.errors.rental_duration_minutes }}</p>
                        </div>

                        <!-- Rental pricing -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="buy_price_rental">Modal / HPP Paket</Label>
                                <Input id="buy_price_rental" v-model.number="form.buy_price" type="number" min="0" class="tabular-nums" :disabled="form.processing" />
                                <p class="text-[10px] text-muted-foreground">Estimasi biaya operasional paket ini</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="sell_price_rental">Harga Jual Paket <span class="text-destructive"> *</span></Label>
                                <Input id="sell_price_rental" v-model.number="form.sell_price" type="number" min="0" class="tabular-nums" :disabled="form.processing" required />
                                <p class="text-[10px] text-muted-foreground">Harga total yang dibayar pelanggan</p>
                                <p v-if="form.errors.sell_price" class="text-xs text-destructive">{{ form.errors.sell_price }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="discount_rental">Diskon Paket (%)</Label>
                                <Input id="discount_rental" v-model.number="form.discount_percent" type="number" min="0" max="100" class="tabular-nums" :disabled="form.processing" />
                                <p class="text-[10px] text-muted-foreground">Diskon dari harga jual normal</p>
                            </div>
                            <div v-if="form.sell_price > 0 && form.rental_duration_minutes" class="rounded-xl bg-violet-100 p-3 text-center">
                                <p class="text-[10px] text-violet-600 font-medium uppercase tracking-wide">Harga per Jam</p>
                                <p class="text-lg font-black text-violet-800">
                                    Rp {{ Math.round(form.sell_price / (form.rental_duration_minutes / 60)).toLocaleString('id-ID') }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- ── Card: Produk yang Disertakan (rental only) ── -->
                <Card v-if="productType === 'rental'" class="border-violet-200">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-violet-800">
                            <ShoppingBag class="h-4 w-4" />
                            Produk yang Disertakan dalam Paket
                        </CardTitle>
                        <CardDescription>
                            Item yang otomatis masuk keranjang belanja saat kasir memilih paket ini untuk di-checkout bersama.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <!-- Existing items -->
                        <div v-if="form.included_items_json.length > 0" class="space-y-2">
                            <div
                                v-for="(item, i) in form.included_items_json"
                                :key="i"
                                class="flex items-center gap-3 rounded-lg border bg-background px-3 py-2"
                            >
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700 tabular-nums">{{ item.qty }}x</span>
                                <span class="min-w-0 flex-1 break-words text-sm font-medium">{{ item.product_name }}</span>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive transition-colors"
                                    @click="removeIncludedItem(i)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center py-4 text-center border-2 border-dashed rounded-xl text-muted-foreground">
                            <p class="text-sm">Belum ada item yang disertakan</p>
                            <p class="text-xs mt-0.5">Paket ini hanya rental murni</p>
                        </div>

                        <!-- Product Picker -->
                        <div class="rounded-xl border-2 border-dashed border-violet-200 bg-violet-50/40 p-3 space-y-2">
                            <p class="text-xs font-semibold text-violet-700 mb-2">➕ Tambah Produk ke Paket</p>
                            <div class="flex flex-wrap gap-2">
                                <!-- Qty -->
                                <Input
                                    v-model.number="pickerQty"
                                    type="number"
                                    min="1"
                                    class="w-16 shrink-0 text-center tabular-nums"
                                    placeholder="Qty"
                                />
                                <!-- Searchable product select -->
                                <div class="relative min-w-[8rem] flex-1">
                                    <Input
                                        v-model="pickerSearch"
                                        placeholder="Cari produk..."
                                        class="w-full"
                                        :class="pickerSelectedId ? 'border-violet-400 bg-violet-50' : ''"
                                        @focus="showPickerDropdown = true"
                                        @input="pickerSelectedId = null; showPickerDropdown = true"
                                    />
                                    <!-- Selected badge -->
                                    <span
                                        v-if="pickerSelectedId"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-violet-600 px-2 py-0.5 text-[10px] font-bold text-white"
                                    >✓ Dipilih</span>

                                    <!-- Dropdown -->
                                    <div
                                        v-if="showPickerDropdown && filteredPickable.length > 0"
                                        class="absolute z-50 mt-1 w-full rounded-xl border bg-background shadow-lg overflow-hidden"
                                    >
                                        <div class="max-h-48 overflow-y-auto">
                                            <button
                                                v-for="p in filteredPickable"
                                                :key="p.id"
                                                type="button"
                                                class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-violet-50 transition-colors"
                                                :class="pickerSelectedId === p.id ? 'bg-violet-100 font-semibold text-violet-800' : ''"
                                                @mousedown.prevent="selectPickerProduct(p)"
                                            >
                                                <span class="flex-1">{{ p.name }}</span>
                                                <span class="text-[10px] text-muted-foreground">{{ p.unit }}</span>
                                            </button>
                                        </div>
                                        <div v-if="filteredPickable.length === 0" class="px-3 py-4 text-center text-sm text-muted-foreground">
                                            Tidak ada produk ditemukan
                                        </div>
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    size="sm"
                                    class="w-full shrink-0 gap-1 bg-violet-600 hover:bg-violet-700 sm:w-auto"
                                    :disabled="!pickerSelectedId"
                                    @click="addIncludedItem"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                    Tambah
                                </Button>
                            </div>
                            <p v-if="allProducts.length === 0" class="text-[10px] text-amber-600">
                                ⚠️ Belum ada produk biasa yang tersedia. Tambahkan produk reguler terlebih dahulu.
                            </p>
                            <p v-else class="text-[10px] text-muted-foreground">
                                Klik produk dari dropdown, atur jumlah (qty), lalu klik Tambah.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- ── Card: Harga & Unit (Produk Biasa only) ── -->
                <Card v-if="productType === 'regular'">
                    <CardHeader>
                        <CardTitle>Harga & Unit Penjualan</CardTitle>
                        <CardDescription>Harga default untuk semua toko</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="buy_price">Harga Beli / HPP <span class="text-destructive">*</span></Label>
                                <Input id="buy_price" v-model.number="form.buy_price" type="number" min="0" step="0.01" class="tabular-nums" :disabled="form.processing" required />
                                <p v-if="form.errors.buy_price" class="text-xs text-destructive">{{ form.errors.buy_price }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="sell_price">Harga Jual <span class="text-destructive">*</span></Label>
                                <Input id="sell_price" v-model.number="form.sell_price" type="number" min="0" step="0.01" class="tabular-nums" :disabled="form.processing" required />
                                <p v-if="form.errors.sell_price" class="text-xs text-destructive">{{ form.errors.sell_price }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="unit">Satuan <span class="text-destructive">*</span></Label>
                                <select id="unit" v-model="form.unit" :disabled="form.processing" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-50">
                                    <option v-for="unit in units" :key="unit" :value="unit">{{ unit }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label for="discount_percent">Diskon Penjualan (%)</Label>
                                <Input id="discount_percent" v-model.number="form.discount_percent" type="number" min="0" max="100" step="0.01" class="tabular-nums" :disabled="form.processing" />
                                <p class="text-[10px] text-muted-foreground">Otomatis aktif di kasir</p>
                                <p v-if="form.errors.discount_percent" class="text-xs text-destructive">{{ form.errors.discount_percent }}</p>
                            </div>
                        </div>

                        <div v-if="form.sell_price > 0 && form.buy_price > 0" class="rounded-lg bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">Margin Keuntungan</p>
                            <p class="text-lg font-semibold">
                                {{ ((form.sell_price - form.buy_price) / form.sell_price * 100).toFixed(1) }}%
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- ── Card: Varian & Modifier (Produk Biasa only) ── -->
                <Card v-if="productType === 'regular'">
                    <CardHeader class="flex flex-col items-start gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <CardTitle>Varian & Modifier</CardTitle>
                            <CardDescription>Pilihan tambahan (Size, Topping, Gula, dll)</CardDescription>
                        </div>
                        <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto" @click="addModifierGroup">
                            <Plus class="mr-1 h-3.5 w-3.5" />
                            Tambah Group
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div v-if="form.modifiers.length === 0" class="flex flex-col items-center justify-center py-8 text-center border-2 border-dashed rounded-xl">
                            <p class="text-sm text-muted-foreground">Belum ada varian produk.</p>
                        </div>

                        <div v-for="(group, gIdx) in form.modifiers" :key="gIdx" class="relative rounded-xl border bg-muted/30 p-4 space-y-4">
                            <Button type="button" variant="ghost" size="icon" class="absolute top-2 right-2 h-7 w-7 text-muted-foreground hover:text-destructive" @click="removeModifierGroup(gIdx)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label class="text-xs">Nama Group (Contoh: Ukuran, Topping)</Label>
                                    <Input v-model="group.name" placeholder="Pilih Ukuran" required />
                                </div>
                                <div class="flex flex-wrap items-center gap-4 sm:pt-6">
                                    <div class="flex items-center gap-2">
                                        <Switch v-model:checked="group.is_required" />
                                        <Label class="text-xs">Wajib Pilih</Label>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Label class="text-xs">Max Pilih</Label>
                                        <Input v-model.number="group.max_select" type="number" min="1" class="h-8 w-16 tabular-nums" />
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <Label class="text-xs font-bold text-muted-foreground">Pilihan Opsi</Label>
                                <div v-for="(opt, oIdx) in group.options" :key="oIdx" class="flex items-start gap-2 sm:items-center">
                                    <div class="flex min-h-10 w-full min-w-0 flex-wrap items-center gap-2 rounded-md bg-background px-2 py-1.5 border sm:flex-nowrap sm:py-0">
                                        <GripVertical class="hidden h-4 w-4 shrink-0 text-muted-foreground/30 sm:block" />
                                        <input v-model="opt.name" class="w-full min-w-0 flex-1 bg-transparent border-none text-sm outline-none placeholder:text-muted-foreground/50 sm:w-auto" placeholder="Nama Opsi (Hot / Large)" required />
                                        <div class="hidden h-4 w-px bg-border mx-1 sm:block" />
                                        <span class="text-[10px] text-muted-foreground">+Rp</span>
                                        <input v-model.number="opt.price_extra" type="number" class="w-20 bg-transparent border-none text-sm font-bold text-right tabular-nums outline-none" min="0" />
                                        <div class="hidden h-4 w-px bg-border mx-1 sm:block" />
                                        <div class="flex items-center gap-1.5 px-1 py-0.5 rounded bg-muted/50">
                                            <Switch :checked="opt.is_available" @update:checked="opt.is_available = $event" class="scale-[0.7]" title="Tersedia (Ready Stock)" />
                                            <span class="text-[9px] font-bold" :class="opt.is_available ? 'text-green-600' : 'text-destructive'">
                                                {{ opt.is_available ? 'READY' : 'EMPTY' }}
                                            </span>
                                        </div>
                                    </div>
                                    <Button v-if="group.options.length > 1" type="button" variant="ghost" size="icon" class="mt-1 h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive sm:mt-0" @click="removeOption(gIdx, oIdx)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                                <Button type="button" variant="ghost" size="sm" class="h-8 text-xs text-primary" @click="addOption(gIdx)">
                                    <Plus class="mr-1 h-3 w-3" /> Tambah Opsi
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- ── Card: Pengaturan ── -->
                <Card>
                    <CardHeader>
                        <CardTitle>Pengaturan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-if="productType === 'regular'" class="flex items-center justify-between gap-3 rounded-lg border p-3">
                            <div class="min-w-0 break-words">
                                <p class="text-sm font-medium">Lacak Stok</p>
                                <p class="text-xs text-muted-foreground">Aktifkan tracking stok untuk produk ini</p>
                            </div>
                            <Switch v-model:checked="form.track_stock" :disabled="form.processing" />
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-lg border p-3">
                            <div class="min-w-0 break-words">
                                <p class="text-sm font-medium">{{ productType === 'rental' ? 'Paket Aktif (muncul di kasir)' : 'Tersedia untuk Dijual' }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ productType === 'rental'
                                        ? 'Paket ini akan tampil di dialog "Mulai Rental" di kasir'
                                        : 'Produk akan muncul di menu kasir' }}
                                </p>
                            </div>
                            <Switch v-model:checked="form.is_available" :disabled="form.processing" />
                        </div>
                    </CardContent>
                </Card>

                <!-- ── Submit bar ── -->
                <div class="flex flex-col-reverse gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
                    <Link href="/admin/products" class="w-full sm:w-auto">
                        <Button type="button" variant="outline" class="w-full sm:w-auto" :disabled="form.processing">Batal</Button>
                    </Link>
                    <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing" :class="productType === 'rental' ? 'bg-violet-600 hover:bg-violet-700' : ''">
                        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isEdit ? 'Simpan Perubahan' : (productType === 'rental' ? '🎮 Buat Paket Rental' : 'Buat Produk') }}
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
