<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { formatCurrency } from '@/lib/utils'
import {
    Plus, Package, Search, Pencil, Trash2, AlertCircle,
} from 'lucide-vue-next'

interface Product {
    id: number
    name: string
    slug: string
    sku: string | null
    category: string | null
    category_id: number | null
    unit: string
    is_available: boolean
    track_stock: boolean
    discount_percent: number
    is_active: boolean
    inventories_count: number
    sell_price: number
    buy_price: number
    image: string | null
}

interface Category {
    id: number
    name: string
    slug: string
    color: string | null
}

const props = defineProps<{
    products: Product[]
    categories: Category[]
}>()

const search = ref('')
const selectedCategory = ref<number | null>(null)
const deleteTarget = ref<Product | null>(null)

function getCategoryColor(categoryId: number | null) {
    if (!categoryId) return null
    return props.categories.find((c) => c.id === categoryId)?.color ?? null
}

const filteredProducts = computed(() => {
    let result = props.products

    if (search.value) {
        const query = search.value.toLowerCase()
        result = result.filter(
            (p) =>
                p.name.toLowerCase().includes(query) ||
                p.sku?.toLowerCase().includes(query) ||
                p.category?.toLowerCase().includes(query),
        )
    }

    if (selectedCategory.value) {
        result = result.filter((p) => p.category_id === selectedCategory.value)
    }

    return result
})

function handleDelete() {
    if (!deleteTarget.value) return
    router.delete(`/admin/products/${deleteTarget.value.id}`, {
        onFinish: () => { deleteTarget.value = null },
    })
}
</script>

<template>
    <AdminLayout title="Produk">

        <!-- Summary Cards -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
            <Card>
                <CardContent class="flex items-center gap-3 p-3 pt-3 sm:gap-4 sm:p-5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary sm:h-11 sm:w-11">
                        <Package class="h-4 w-4 sm:h-5 sm:w-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-muted-foreground sm:text-xs">Total Produk</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums sm:text-2xl">{{ products.length }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-3 pt-3 sm:gap-4 sm:p-5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-success/15 text-success sm:h-11 sm:w-11">
                        <Package class="h-4 w-4 sm:h-5 sm:w-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-muted-foreground sm:text-xs">Tersedia</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums sm:text-2xl">{{ products.filter(p => p.is_available).length }}</p>
                    </div>
                </CardContent>
            </Card>
            <!-- Kartu ketiga jadi lebar penuh di HP agar grid 2 kolom tidak menyisakan sel kosong -->
            <Card class="col-span-2 lg:col-span-1">
                <CardContent class="flex items-center gap-3 p-3 pt-3 sm:gap-4 sm:p-5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-warning sm:h-11 sm:w-11">
                        <AlertCircle class="h-4 w-4 sm:h-5 sm:w-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-muted-foreground sm:text-xs">Tidak Tersedia</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums sm:text-2xl">{{ products.filter(p => !p.is_available).length }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card class="mb-4">
            <CardContent class="flex flex-col gap-3 p-3 sm:p-4 lg:flex-row lg:flex-wrap lg:items-center">
                <div class="relative w-full min-w-0 lg:w-auto lg:flex-1 lg:min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Cari produk, SKU, kategori..."
                        class="pl-9"
                    />
                </div>
                <!-- Chip kategori digulir horizontal supaya tidak memaksa lebar halaman di HP -->
                <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0 lg:pb-0 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                    <Button
                        variant="outline"
                        size="sm"
                        class="shrink-0 whitespace-nowrap"
                        :class="{ 'bg-primary/10 text-primary border-primary': !selectedCategory }"
                        @click="selectedCategory = null"
                    >
                        Semua ({{ products.length }})
                    </Button>
                    <Button
                        v-for="cat in categories"
                        :key="cat.id"
                        variant="outline"
                        size="sm"
                        class="shrink-0 whitespace-nowrap"
                        :class="{
                            'bg-primary/10 text-primary border-primary': selectedCategory === cat.id && !cat.color,
                        }"
                        :style="cat.color
                            ? (selectedCategory === cat.id
                                ? { backgroundColor: cat.color + '20', borderColor: cat.color, color: cat.color }
                                : { borderColor: cat.color + '60', color: cat.color })
                            : {}"
                        @click="selectedCategory = selectedCategory === cat.id ? null : cat.id"
                    >
                        <span
                            v-if="cat.color"
                            class="mr-1 h-2 w-2 shrink-0 rounded-full"
                            :style="{ backgroundColor: cat.color }"
                        />
                        {{ cat.name }} ({{ products.filter(p => p.category_id === cat.id).length }})
                    </Button>
                </div>
                <!-- Tambah button moved here -->
                <Link href="/admin/products/create" class="w-full shrink-0 lg:ml-auto lg:w-auto">
                    <Button size="sm" class="w-full gap-2 lg:w-auto">
                        <Plus class="h-4 w-4" />
                        Tambah Produk
                    </Button>
                </Link>
            </CardContent>
        </Card>

        <!-- Products Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="product in filteredProducts"
                :key="product.id"
                class="group relative rounded-2xl border border-border/70 bg-card/95 p-4 shadow-sm transition-all"
            >
                <!-- Image Placeholder -->
                <div class="mb-3 flex h-32 items-center justify-center rounded-lg bg-muted">
                    <Package class="h-12 w-12 text-muted-foreground/50" />
                </div>

                <!-- Info -->
                <div class="space-y-2">
                    <div class="min-w-0">
                        <h3 class="line-clamp-2 break-words font-semibold">{{ product.name }}</h3>
                        <p v-if="product.sku" class="break-words text-xs text-muted-foreground">SKU: {{ product.sku }}</p>
                    </div>

                    <span
                        v-if="product.category"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                        :style="getCategoryColor(product.category_id)
                            ? {
                                backgroundColor: (getCategoryColor(product.category_id) ?? '') + '25',
                                color: getCategoryColor(product.category_id) ?? undefined,
                            }
                            : {}"
                        :class="!getCategoryColor(product.category_id) && 'border border-input'"
                    >
                        <span
                            v-if="getCategoryColor(product.category_id)"
                            class="h-1.5 w-1.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: getCategoryColor(product.category_id) ?? undefined }"
                        />
                        {{ product.category }}
                    </span>

                    <div class="mt-2 flex items-center justify-between gap-2 border-t pt-2">
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">Harga Jual</p>
                            <div v-if="product.discount_percent > 0">
                                <p class="whitespace-nowrap text-[10px] text-muted-foreground line-through">{{ formatCurrency(product.sell_price) }}</p>
                                <p class="whitespace-nowrap text-sm font-bold text-destructive">
                                    {{ formatCurrency(product.sell_price - Math.round(product.sell_price * (product.discount_percent / 100))) }}
                                </p>
                            </div>
                            <div v-else>
                                <p class="whitespace-nowrap text-sm font-semibold">{{ formatCurrency(product.sell_price) }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <Badge v-if="product.discount_percent > 0" variant="destructive" class="text-[10px] px-1 py-0 h-4">
                                <span class="font-mono">{{ product.discount_percent }}%</span>
                            </Badge>
                            <Badge :variant="product.is_available ? 'success' : 'secondary'" class="text-[10px] opacity-100">
                                {{ product.is_available ? 'Tersedia' : 'Tidak' }}
                            </Badge>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <Link :href="`/admin/products/${product.id}/edit`" class="min-w-0 flex-1">
                            <Button variant="outline" size="sm" class="w-full">
                                <Pencil class="h-3.5 w-3.5" />
                                Edit
                            </Button>
                        </Link>
                        <Button
                            variant="outline"
                            size="sm"
                            class="shrink-0 text-destructive hover:bg-destructive/10"
                            @click="deleteTarget = product"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Add New Card -->
            <Link
                href="/admin/products/create"
                class="flex min-h-[300px] cursor-pointer items-center justify-center rounded-2xl border-2 border-dashed border-muted-foreground/30 bg-muted/20 text-muted-foreground transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary"
            >
                <div class="text-center">
                    <Plus class="mx-auto mb-2 h-8 w-8" />
                    <p class="text-sm font-medium">Tambah Produk Baru</p>
                </div>
            </Link>
        </div>

        <p v-if="filteredProducts.length === 0 && (search || selectedCategory)" class="py-10 text-center text-muted-foreground">
            Tidak ada produk ditemukan
        </p>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!deleteTarget" @update:open="(v) => { if (!v) deleteTarget = null }">
            <DialogContent class="w-[95vw] max-w-sm">
                <DialogHeader>
                    <DialogTitle>Hapus Produk</DialogTitle>
                    <DialogDescription class="break-words">
                        Apakah Anda yakin ingin menghapus produk
                        <strong>{{ deleteTarget?.name }}</strong>?
                        Tindakan ini tidak dapat dibatalkan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="deleteTarget = null">Batal</Button>
                    <Button variant="destructive" class="w-full sm:w-auto" @click="handleDelete">Ya, Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
