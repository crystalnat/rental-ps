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
        <template #headerActions>
            <Link href="/admin/products/create">
                <Button size="sm">
                    <Plus class="h-4 w-4" />
                    Tambah Produk
                </Button>
            </Link>
        </template>

        <!-- Summary Cards -->
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="flex items-center gap-4 pt-5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/15 text-primary">
                        <Package class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total Produk</p>
                        <p class="mt-1 text-2xl font-semibold">{{ products.length }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 pt-5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-success/15 text-success">
                        <Package class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tersedia</p>
                        <p class="mt-1 text-2xl font-semibold">{{ products.filter(p => p.is_available).length }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 pt-5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-warning/15 text-warning">
                        <AlertCircle class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tidak Tersedia</p>
                        <p class="mt-1 text-2xl font-semibold">{{ products.filter(p => !p.is_available).length }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card class="mb-4">
            <CardContent class="flex flex-wrap items-center gap-3 py-4">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Cari produk, SKU, kategori..."
                        class="pl-9"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
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
                    <div>
                        <h3 class="font-semibold line-clamp-2">{{ product.name }}</h3>
                        <p v-if="product.sku" class="text-xs text-muted-foreground">SKU: {{ product.sku }}</p>
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

                    <div class="flex items-center justify-between border-t pt-2 mt-2">
                        <div>
                            <p class="text-xs text-muted-foreground">Harga Jual</p>
                            <div v-if="product.discount_percent > 0">
                                <p class="text-[10px] text-muted-foreground line-through">{{ formatCurrency(product.sell_price) }}</p>
                                <p class="font-bold text-sm text-destructive">
                                    {{ formatCurrency(product.sell_price - Math.round(product.sell_price * (product.discount_percent / 100))) }}
                                </p>
                            </div>
                            <div v-else>
                                <p class="font-semibold text-sm">{{ formatCurrency(product.sell_price) }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <Badge v-if="product.discount_percent > 0" variant="destructive" class="text-[10px] px-1 py-0 h-4">
                                <span class="font-mono">{{ product.discount_percent }}%</span>
                            </Badge>
                            <Badge :variant="product.is_available ? 'success' : 'secondary'" class="text-[10px] opacity-100">
                                {{ product.is_available ? 'Tersedia' : 'Tidak' }}
                            </Badge>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <Link :href="`/admin/products/${product.id}/edit`" class="flex-1">
                            <Button variant="outline" size="sm" class="w-full">
                                <Pencil class="h-3.5 w-3.5" />
                                Edit
                            </Button>
                        </Link>
                        <Button
                            variant="outline"
                            size="sm"
                            class="text-destructive hover:bg-destructive/10"
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
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Hapus Produk</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus produk
                        <strong>{{ deleteTarget?.name }}</strong>?
                        Tindakan ini tidak dapat dibatalkan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteTarget = null">Batal</Button>
                    <Button variant="destructive" @click="handleDelete">Ya, Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
