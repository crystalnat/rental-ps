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
import { ArrowLeft, Loader2, Package } from 'lucide-vue-next'

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
})

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
