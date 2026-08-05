<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { ArrowLeft, Trash2, Search, Loader2, ShoppingCart, Box, Warehouse, List } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'

interface Product {
    id: number
    name: string
    sku: string | null
    unit: string
    category?: string
    total_stock: number
}

interface POItem {
    id?: number
    product_id: number
    quantity: number | string
    buy_price: number | string
    product?: Product
}

interface PurchaseOrder {
    id: number
    store_id: number
    supplier_id: number
    expected_date: string | null
    notes: string | null
    items: POItem[]
}

const props = defineProps<{
    stores: { id: number; name: string }[]
    suppliers: { id: number; name: string }[]
    products: Product[]
    purchaseOrder?: PurchaseOrder
    isEditing?: boolean
}>()

const form = useForm({
    store_id: props.purchaseOrder?.store_id || (props.stores.length === 1 ? props.stores[0].id : ''),
    supplier_id: props.purchaseOrder?.supplier_id || '',
    expected_date: props.purchaseOrder?.expected_date?.split('T')[0] || '',
    notes: props.purchaseOrder?.notes || '',
    items: props.purchaseOrder?.items.map(item => ({
        product_id: item.product_id,
        product: item.product,
        quantity: item.quantity,
        buy_price: item.buy_price
    })) || [] as POItem[]
})

const productSearch = ref('')
const showBrowseModal = ref(false)

const filteredProducts = computed(() => {
    if (!productSearch.value) return []
    const q = productSearch.value.toLowerCase()
    return props.products.filter(p => 
        p.name.toLowerCase().includes(q) || 
        (p.sku && p.sku.toLowerCase().includes(q))
    ).slice(0, 10) 
})

const browseSearch = ref('')
const browsedProducts = computed(() => {
    if (!browseSearch.value) return props.products
    const q = browseSearch.value.toLowerCase()
    return props.products.filter(p => 
        p.name.toLowerCase().includes(q) || 
        (p.sku && p.sku.toLowerCase().includes(q)) ||
        (p.category && p.category.toLowerCase().includes(q))
    )
})

function addProduct(product: Product) {
    const existing = form.items.find(i => i.product_id === product.id)
    if (existing) {
        existing.quantity = Number(existing.quantity) + 1
    } else {
        form.items.push({
            product_id: product.id,
            product: product,
            quantity: 1,
            buy_price: ''
        })
    }
    productSearch.value = ''
    showBrowseModal.value = false
}

function removeItem(index: number) {
    form.items.splice(index, 1)
}

const grandTotal = computed(() => {
    return form.items.reduce((sum, item) => sum + ((Number(item.quantity) || 0) * (Number(item.buy_price) || 0)), 0)
})

function submit() {
    if (props.isEditing && props.purchaseOrder) {
        form.put(`/admin/purchase-orders/${props.purchaseOrder.id}`)
    } else {
        form.post('/admin/purchase-orders')
    }
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
}

function goBack() {
    router.visit('/admin/purchase-orders')
}
</script>

<template>
    <Head :title="isEditing ? 'Ubah Purchase Order' : 'Buat Purchase Order'" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header Intena (Sama dengan Shift Detail) -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <Button variant="ghost" size="sm" @click="goBack" class="self-start px-0 hover:bg-transparent">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>
                <h1 class="min-w-0 break-words text-lg font-bold text-foreground sm:text-xl">
                    {{ isEditing ? 'Ubah Purchase Order' : 'Buat Purchase Order Baru' }}
                </h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Info -->
                <Card>
                    <CardContent class="p-4 sm:p-6">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-4 border-b pb-2 flex items-center gap-2">
                            <Warehouse class="h-4 w-4" /> Informasi Dasar
                        </h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6">
                            <div class="space-y-2">
                                <Label for="store" class="text-sm font-medium">Cabang Masuk <span class="text-destructive">*</span></Label>
                                <select 
                                    id="store" 
                                    v-model="form.store_id"
                                    required
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled>Pilih Cabang</option>
                                    <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <p v-if="form.errors.store_id" class="text-xs text-destructive">{{ form.errors.store_id }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="supplier" class="text-sm font-medium">Supplier Tujuan <span class="text-destructive">*</span></Label>
                                <select 
                                    id="supplier" 
                                    v-model="form.supplier_id"
                                    required
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled>Pilih Supplier</option>
                                    <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                                </select>
                                <p v-if="form.errors.supplier_id" class="text-xs text-destructive">{{ form.errors.supplier_id }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="expected_date" class="text-sm font-medium">Tanggal Estimasi Datang</Label>
                                <Input id="expected_date" type="date" v-model="form.expected_date" class="text-foreground" />
                                <p v-if="form.errors.expected_date" class="text-xs text-destructive">{{ form.errors.expected_date }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="notes" class="text-sm font-medium">Catatan Tambahan</Label>
                                <Input id="notes" v-model="form.notes" placeholder="Catatan untuk PO ini..." class="text-foreground" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Product Items -->
                <Card>
                    <CardContent class="p-0">
                        <div class="p-4 border-b flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-2">
                                <Box class="h-4 w-4" /> Daftar Item Pesanan
                            </h3>
                            
                            <div class="flex w-full items-center gap-2 md:max-w-md">
                                <div class="relative flex-1">
                                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input 
                                        v-model="productSearch" 
                                        placeholder="Cepat: Ketik Nama/SKU..." 
                                        class="pl-9 h-9 text-foreground"
                                    />
                                    
                                    <div v-if="productSearch && filteredProducts.length > 0" class="absolute left-0 mt-2 w-full rounded-lg border bg-popover text-popover-foreground shadow-xl z-20 max-h-60 overflow-y-auto">
                                        <button
                                            v-for="p in filteredProducts"
                                            :key="p.id"
                                            type="button"
                                            class="flex w-full items-center px-4 py-3 hover:bg-accent hover:text-accent-foreground text-sm text-left transition-colors"
                                            @click="addProduct(p)"
                                        >
                                            <div class="flex-1">
                                                <div class="font-medium">{{ p.name }}</div>
                                                <div class="text-[10px] text-muted-foreground" v-if="p.sku">SKU: {{ p.sku }} | Stok: {{ Number(p.total_stock) }}</div>
                                            </div>
                                            <Badge variant="outline" class="ml-auto text-[10px]">{{ p.unit }}</Badge>
                                        </button>
                                    </div>
                                </div>

                                <Button type="button" variant="outline" size="sm" class="h-9 gap-1" @click="showBrowseModal = true">
                                    <List class="h-4 w-4" /> Pilih Produk
                                </Button>
                            </div>
                        </div>

                        <!-- Mobile: daftar kartu vertikal -->
                        <div class="md:hidden divide-y">
                            <div v-for="(item, index) in form.items" :key="index" class="p-4 space-y-3">
                                <div class="flex items-start gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-foreground break-words">{{ item.product?.name }}</div>
                                        <div class="text-[10px] text-muted-foreground break-words" v-if="item.product?.sku">SKU: {{ item.product?.sku }}</div>
                                    </div>
                                    <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0 text-destructive hover:bg-destructive/10" @click="removeItem(index)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <span class="text-[10px] uppercase tracking-wide text-muted-foreground">Kuantitas</span>
                                        <Input v-model="item.quantity" type="number" step="0.01" min="0.01" class="h-9 w-full text-center text-sm text-foreground tabular-nums" required />
                                    </div>
                                    <div class="space-y-1">
                                        <span class="text-[10px] uppercase tracking-wide text-muted-foreground">Harga Beli</span>
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-muted-foreground text-[10px]">Rp</span>
                                            <Input v-model="item.buy_price" type="number" step="1" min="0" class="h-9 w-full pl-7 text-right text-sm text-foreground tabular-nums" required />
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between border-t pt-2">
                                    <span class="text-[10px] uppercase tracking-wide text-muted-foreground">Subtotal</span>
                                    <span class="font-semibold text-foreground tabular-nums whitespace-nowrap">
                                        {{ formatCurrency((Number(item.quantity) || 0) * (Number(item.buy_price) || 0)) }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="form.items.length === 0" class="py-12 px-4 text-center text-muted-foreground bg-muted/5">
                                <div class="flex flex-col items-center">
                                    <ShoppingCart class="h-10 w-10 text-muted-foreground/30 mb-2" />
                                    <p class="font-medium text-sm text-muted-foreground">Keranjang Pesanan Kosong</p>
                                    <p class="text-xs text-muted-foreground break-words">Silakan klik "Pilih Produk" atau cari di atas.</p>
                                </div>
                            </div>

                            <div v-else class="flex items-center justify-between gap-3 bg-muted/5 p-4">
                                <span class="text-[10px] uppercase tracking-wider text-muted-foreground break-words">Total Estimasi Pembelian</span>
                                <span class="text-lg font-semibold text-primary tabular-nums whitespace-nowrap">{{ formatCurrency(grandTotal) }}</span>
                            </div>
                        </div>

                        <!-- Desktop: tabel -->
                        <div class="hidden md:block">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead class="bg-muted/30 text-muted-foreground border-b text-xs font-semibold uppercase tracking-wide">
                                    <tr>
                                        <th class="h-10 px-4">Produk</th>
                                        <th class="h-10 px-4 w-32 text-center">Kuantitas</th>
                                        <th class="h-10 px-4 w-40 text-right">Harga Beli Estimasi</th>
                                        <th class="h-10 px-4 w-32 text-right">Subtotal</th>
                                        <th class="h-10 w-12 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-muted/10 transition-colors">
                                        <td class="p-4 align-middle">
                                            <div class="font-medium text-foreground">{{ item.product?.name }}</div>
                                            <div class="text-[10px] text-muted-foreground" v-if="item.product?.sku">SKU: {{ item.product?.sku }}</div>
                                        </td>
                                        <td class="p-2 align-middle">
                                            <div class="flex items-center justify-center">
                                                <Input v-model="item.quantity" type="number" step="0.01" min="0.01" class="h-8 w-24 text-center text-xs text-foreground" required />
                                            </div>
                                        </td>
                                        <td class="p-2 align-middle">
                                            <div class="flex items-center justify-end">
                                                <div class="relative w-36">
                                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-muted-foreground text-[10px]">Rp</span>
                                                    <Input v-model="item.buy_price" type="number" step="1" min="0" class="h-8 pl-7 text-right text-xs text-foreground tabular-nums" required />
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4 align-middle text-right font-medium text-foreground tabular-nums">
                                            {{ formatCurrency((Number(item.quantity) || 0) * (Number(item.buy_price) || 0)) }}
                                        </td>
                                        <td class="p-2 align-middle text-center">
                                            <Button type="button" variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:bg-destructive/10" @click="removeItem(index)">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                    <tr v-if="form.items.length === 0">
                                        <td colspan="5" class="py-16 text-center text-muted-foreground bg-muted/5">
                                            <div class="flex flex-col items-center">
                                                <ShoppingCart class="h-10 w-10 text-muted-foreground/30 mb-2" />
                                                <p class="font-medium text-sm text-muted-foreground">Keranjang Pesanan Kosong</p>
                                                <p class="text-xs text-muted-foreground">Silakan klik "Pilih Produk" atau cari di atas.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="form.items.length > 0" class="bg-muted/5 font-semibold">
                                    <tr class="border-t">
                                        <td colspan="3" class="p-4 text-right uppercase text-xs tracking-wider text-muted-foreground">Total Estimasi Pembelian</td>
                                        <td class="p-4 text-right text-lg text-primary tabular-nums">{{ formatCurrency(grandTotal) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex flex-col-reverse gap-3 py-4 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" class="w-full sm:w-auto sm:px-6" @click="goBack">Batal</Button>
                    <Button type="submit" class="w-full shadow-sm sm:w-auto sm:px-8" :disabled="form.processing || form.items.length === 0">
                        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isEditing ? 'Simpan Perubahan' : 'Proses Purchase Order' }}
                    </Button>
                </div>
            </form>
        </div>

        <!-- Browse Products Modal -->
        <Dialog :open="showBrowseModal" @update:open="showBrowseModal = $event">
            <DialogContent class="w-[95vw] max-w-4xl max-h-[90dvh] flex flex-col p-0 overflow-hidden">
                <DialogHeader class="p-6 pb-2">
                    <DialogTitle>Daftar Inventaris Produk</DialogTitle>
                    <DialogDescription>
                        Informasi stok saat ini untuk membantu Anda menentukan jumlah pesanan.
                    </DialogDescription>
                </DialogHeader>
                
                <div class="px-6 py-2">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="browseSearch" placeholder="Cari nama, SKU, atau kategori..." class="pl-9 text-foreground" />
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-4 border-t sm:px-6">
                    <!-- Mobile: daftar kartu vertikal -->
                    <div class="md:hidden space-y-3">
                        <div v-for="p in browsedProducts" :key="p.id" class="rounded-lg border p-3 space-y-2">
                            <div class="min-w-0">
                                <div class="font-medium text-foreground break-words">{{ p.name }}</div>
                                <div class="text-[10px] text-muted-foreground break-words" v-if="p.sku">{{ p.sku }}</div>
                                <div class="text-[10px] text-muted-foreground break-words">Kategori: {{ p.category || '—' }}</div>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <Badge variant="outline" class="tabular-nums whitespace-nowrap" :class="Number(p.total_stock) <= 0 ? 'text-destructive border-destructive' : 'text-foreground'">
                                    {{ Number(p.total_stock) }} {{ p.unit }}
                                </Badge>
                                <Button size="sm" variant="secondary" class="h-8 shrink-0" @click="addProduct(p)">
                                    Pilih
                                </Button>
                            </div>
                        </div>
                        <div v-if="browsedProducts.length === 0" class="py-10 text-center text-muted-foreground italic">
                            Produk tidak ditemukan.
                        </div>
                    </div>

                    <table class="hidden w-full text-sm md:table">
                        <thead class="bg-muted text-xs font-semibold uppercase tracking-wide sticky top-0 z-10 border-b">
                            <tr>
                                <th class="px-3 py-2 text-left">Produk / SKU</th>
                                <th class="px-3 py-2 text-left">Kategori</th>
                                <th class="px-3 py-2 text-center">Stok Saat Ini</th>
                                <th class="px-3 py-2 text-right">Opsi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="p in browsedProducts" :key="p.id" class="hover:bg-muted/50 transition-colors">
                                <td class="px-3 py-3">
                                    <div class="font-medium text-foreground">{{ p.name }}</div>
                                    <div class="text-[10px] text-muted-foreground" v-if="p.sku">{{ p.sku }}</div>
                                </td>
                                <td class="px-3 py-3 text-xs text-muted-foreground">
                                    {{ p.category || '—' }}
                                </td>
                                <td class="px-3 py-3 text-center align-middle">
                                    <Badge variant="outline" :class="Number(p.total_stock) <= 0 ? 'text-destructive border-destructive' : 'text-foreground'">
                                        {{ Number(p.total_stock) }} {{ p.unit }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <Button size="sm" variant="secondary" @click="addProduct(p)" class="h-8">
                                        Pilih
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="browsedProducts.length === 0">
                                <td colspan="4" class="py-10 text-center text-muted-foreground italic">
                                    Produk tidak ditemukan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DialogFooter class="p-6 pt-2 border-t mt-auto">
                    <Button variant="outline" @click="showBrowseModal = false">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AdminLayout>
</template>
