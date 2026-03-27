<script setup lang="ts">
import { computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { ArrowLeft, Loader2, Package, Plus, Trash2, GripVertical } from 'lucide-vue-next'

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
    modifiers?: ModifierGroup[]
}

interface Category {
    id: number
    name: string
}

const props = defineProps<{
    product: ProductData | null
    categories: Category[]
}>()

const isEdit = computed(() => !!props.product)
const pageTitle = computed(() => isEdit.value ? `Edit: ${props.product!.name}` : 'Tambah Produk Baru')

const form = useForm({
    name:         props.product?.name         ?? '',
    category_id:  props.product?.category_id  ?? null,
    sku:          props.product?.sku          ?? '',
    description:  props.product?.description  ?? '',
    unit:         props.product?.unit         ?? 'pcs',
    track_stock:  props.product?.track_stock  ?? true,
    is_available: props.product?.is_available ?? true,
    buy_price:    props.product?.buy_price    ?? 0,
    sell_price:   props.product?.sell_price   ?? 0,
    discount_percent: props.product?.discount_percent ?? 0,
    modifiers:    (props.product?.modifiers ?? []) as ModifierGroup[],
})

function addModifierGroup() {
    form.modifiers.push({
        name: '',
        is_required: false,
        min_select: 0,
        max_select: 1,
        options: [{ name: '', price_extra: 0, is_active: true, is_available: true }]
    })
}

function removeModifierGroup(index: number) {
    form.modifiers.splice(index, 1)
}

function addOption(groupIndex: number) {
    form.modifiers[groupIndex].options.push({ name: '', price_extra: 0, is_active: true, is_available: true })
}

function removeOption(groupIndex: number, optionIndex: number) {
    form.modifiers[groupIndex].options.splice(optionIndex, 1)
}

function submit() {
    if (isEdit.value) {
        form.put(`/admin/products/${props.product!.id}`)
    } else {
        form.post('/admin/products')
    }
}

const units = ['pcs', 'kg', 'gram', 'liter', 'ml', 'porsi', 'pack', 'box', 'lusin', 'meter']
</script>

<template>
    <AdminLayout :title="pageTitle">
        <template #headerActions>
            <Link href="/admin/products">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
            </Link>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Product Info -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Package class="h-4 w-4" />
                            </div>
                            <div>
                                <CardTitle>Informasi Produk</CardTitle>
                                <CardDescription>Detail dasar produk</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Produk <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Kopi Susu"
                                :disabled="form.processing"
                                required
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
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
                                <Label for="sku">SKU</Label>
                                <Input
                                    id="sku"
                                    v-model="form.sku"
                                    placeholder="KSS-001"
                                    :disabled="form.processing"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Deskripsi</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Deskripsi singkat produk..."
                                :rows="3"
                                :disabled="form.processing"
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Pricing -->
                <Card>
                    <CardHeader>
                        <CardTitle>Harga & Unit</CardTitle>
                        <CardDescription>Harga default untuk semua toko</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="buy_price">Harga Beli (HPP) <span class="text-destructive">*</span></Label>
                                <Input
                                    id="buy_price"
                                    v-model.number="form.buy_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :disabled="form.processing"
                                    required
                                />
                                <p v-if="form.errors.buy_price" class="text-xs text-destructive">{{ form.errors.buy_price }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="sell_price">Harga Jual <span class="text-destructive">*</span></Label>
                                <Input
                                    id="sell_price"
                                    v-model.number="form.sell_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :disabled="form.processing"
                                    required
                                />
                                <p v-if="form.errors.sell_price" class="text-xs text-destructive">{{ form.errors.sell_price }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="unit">Satuan <span class="text-destructive">*</span></Label>
                                <select
                                    id="unit"
                                    v-model="form.unit"
                                    :disabled="form.processing"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option v-for="unit in units" :key="unit" :value="unit">{{ unit }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label for="discount_percent">Diskon Penjualan (%)</Label>
                                <Input
                                    id="discount_percent"
                                    v-model.number="form.discount_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    :disabled="form.processing"
                                />
                                <p class="text-[10px] text-muted-foreground">Diskon otomatis aktif saat masuk keranjang Kasir.</p>
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

                <!-- Modifiers -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0">
                        <div>
                            <CardTitle>Varian & Modifier</CardTitle>
                            <CardDescription>Pilihan tambahan (Size, Topping, Gula, dll)</CardDescription>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addModifierGroup">
                            <Plus class="mr-1 h-3.5 w-3.5" />
                            Tambah Group
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div v-if="form.modifiers.length === 0" class="flex flex-col items-center justify-center py-8 text-center border-2 border-dashed rounded-xl">
                            <p class="text-sm text-muted-foreground">Belum ada varian produk.</p>
                        </div>

                        <div v-for="(group, gIdx) in form.modifiers" :key="gIdx" class="relative rounded-xl border bg-muted/30 p-4 space-y-4">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="absolute top-2 right-2 h-7 w-7 text-muted-foreground hover:text-destructive"
                                @click="removeModifierGroup(gIdx)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label class="text-xs">Nama Group (Contoh: Ukuran, Topping)</Label>
                                    <Input v-model="group.name" placeholder="Pilih Ukuran" required />
                                </div>
                                <div class="flex items-center gap-4 pt-6">
                                    <div class="flex items-center gap-2">
                                        <Switch v-model:checked="group.is_required" />
                                        <Label class="text-xs">Wajib Pilih</Label>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Label class="text-xs">Max Pilih</Label>
                                        <Input v-model.number="group.max_select" type="number" min="1" class="h-8 w-16" />
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <Label class="text-xs font-bold text-muted-foreground">Pilihan Opsi</Label>
                                <div v-for="(opt, oIdx) in group.options" :key="oIdx" class="flex items-center gap-2">
                                    <div class="flex h-10 w-full items-center gap-2 rounded-md bg-background px-2 border">
                                        <GripVertical class="h-4 w-4 text-muted-foreground/30" />
                                        <input
                                            v-model="opt.name"
                                            class="flex-1 bg-transparent border-none text-sm outline-none placeholder:text-muted-foreground/50"
                                            placeholder="Nama Opsi (Hot / Large)"
                                            required
                                        />
                                        <div class="h-4 w-px bg-border mx-1" />
                                        <span class="text-[10px] text-muted-foreground">+Rp</span>
                                        <input
                                            v-model.number="opt.price_extra"
                                            type="number"
                                            class="w-20 bg-transparent border-none text-sm font-bold text-right outline-none"
                                            min="0"
                                        />
                                        <div class="h-4 w-px bg-border mx-1" />
                                        <div class="flex items-center gap-1.5 px-1 py-0.5 rounded bg-muted/50">
                                            <Switch 
                                                :checked="opt.is_available" 
                                                @update:checked="opt.is_available = $event" 
                                                class="scale-[0.7]" 
                                                title="Tersedia (Ready Stock)"
                                            />
                                            <span class="text-[9px] font-bold" :class="opt.is_available ? 'text-green-600' : 'text-destructive'">
                                                {{ opt.is_available ? 'READY' : 'EMPTY' }}
                                            </span>
                                        </div>
                                    </div>
                                    <Button
                                        v-if="group.options.length > 1"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                        @click="removeOption(gIdx, oIdx)"
                                    >
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

                <!-- Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle>Pengaturan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <p class="text-sm font-medium">Lacak Stok</p>
                                <p class="text-xs text-muted-foreground">Aktifkan tracking stok untuk produk ini</p>
                            </div>
                            <Switch v-model:checked="form.track_stock" :disabled="form.processing" />
                        </div>
                        <div class="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <p class="text-sm font-medium">Tersedia untuk Dijual</p>
                                <p class="text-xs text-muted-foreground">Produk akan muncul di menu customer</p>
                            </div>
                            <Switch v-model:checked="form.is_available" :disabled="form.processing" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-3 rounded-xl border bg-card p-4">
                    <Link href="/admin/products">
                        <Button type="button" variant="outline" :disabled="form.processing">Batal</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="animate-spin" />
                        {{ isEdit ? 'Simpan Perubahan' : 'Buat Produk' }}
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
