<script setup lang="ts">
import { ref, computed, toRef } from 'vue'
import { router } from '@inertiajs/vue3'
import { useTableFeatures } from '@/composables/useTableFeatures'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import TableToolbar from '@/components/TableToolbar.vue'
import FilterSelect from '@/components/FilterSelect.vue'
import AlertDialog from '@/components/AlertDialog.vue'
import { TableHeadSortable } from '@/components/ui/table'
import { Plus, Tags, Pencil, Trash2, Loader2, CheckCircle2, EyeOff, Package } from 'lucide-vue-next'

const presetColors = [
    '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#3b82f6', '#8b5cf6', '#ec4899', '#64748b',
]

interface CategoryItem {
    id: number
    name: string
    slug: string
    icon: string | null
    color: string | null
    sort_order: number
    is_active: boolean
    products_count: number
}

const props = defineProps<{
    categories: CategoryItem[]
}>()

const tableFeatures = useTableFeatures(toRef(props, 'categories'), {
    getSearchText: (c) => [c.name, c.color].filter(Boolean).join(' '),
    sortableFields: ['name', 'sort_order', 'products_count', 'is_active'],
    filters: {
        status: [
            { value: 'all', label: 'Semua Status' },
            { value: 'active', label: 'Aktif' },
            { value: 'inactive', label: 'Nonaktif' },
        ],
    },
    getFilterValue: (item, key) =>
        key === 'status' ? (item.is_active ? 'active' : 'inactive') : null,
})

const { searchQuery, sortKey, sortDir, filterValues, filteredAndSortedData, setSort, setFilter, clearFilters } = tableFeatures
const hasActiveFilters = computed(
    () =>
        !!searchQuery.value ||
        !!sortKey.value ||
        (filterValues.value?.status && filterValues.value.status !== 'all'),
)

const activeCount = computed(() => props.categories.filter(c => c.is_active).length)
const totalProducts = computed(() => props.categories.reduce((sum, c) => sum + c.products_count, 0))

const deleteTarget = ref<CategoryItem | null>(null)

function handleDelete() {
    if (!deleteTarget.value) return
    const id = deleteTarget.value.id
    deleteTarget.value = null 
    router.delete(`/admin/categories/${id}`, {
        preserveScroll: true,
        onError: (errors) => {
            showAlert('Gagal menghapus kategori: ' + Object.values(errors).flat().join(', '))
        },
    })
}

// Form Modal (create/edit)
const showFormDialog = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const processing = ref(false)
const categoryForm = ref({
    id: 0,
    name: '',
    color: '',
    sort_order: 0,
    is_active: true,
})

const alertState = ref<{ open: boolean; message: string; variant: 'info' | 'error' | 'warning' }>({
    open: false, message: '', variant: 'error',
})

function showAlert(message: string, variant: 'info' | 'error' | 'warning' = 'error') {
    alertState.value = { open: true, message, variant }
}

function resetCategoryForm() {
    categoryForm.value = {
        id: 0,
        name: '',
        color: '',
        sort_order: 0,
        is_active: true,
    }
}

function openAddCategory() {
    resetCategoryForm()
    formMode.value = 'create'
    showFormDialog.value = true
}

function openEditCategory(cat: CategoryItem) {
    categoryForm.value = {
        id: cat.id,
        name: cat.name,
        color: cat.color ?? '',
        sort_order: cat.sort_order,
        is_active: cat.is_active,
    }
    formMode.value = 'edit'
    showFormDialog.value = true
}

function submitCategory() {
    if (!categoryForm.value.name?.trim()) return
    processing.value = true
    showFormDialog.value = false
    if (formMode.value === 'create') {
        router.post('/admin/categories', {
            name: categoryForm.value.name,
            color: categoryForm.value.color || null,
            sort_order: categoryForm.value.sort_order,
            is_active: categoryForm.value.is_active,
        }, {
            preserveScroll: true,
            onError: (errors) => {
                showFormDialog.value = true
                showAlert('Gagal menambah kategori: ' + Object.values(errors).flat().join(', '))
            },
            onFinish: () => { processing.value = false },
        })
    } else {
        router.put(`/admin/categories/${categoryForm.value.id}`, {
            name: categoryForm.value.name,
            color: categoryForm.value.color || null,
            sort_order: categoryForm.value.sort_order,
            is_active: categoryForm.value.is_active,
        }, {
            preserveScroll: true,
            onError: (errors) => {
                showFormDialog.value = true
                showAlert('Gagal menyimpan kategori: ' + Object.values(errors).flat().join(', '))
            },
            onFinish: () => { processing.value = false },
        })
    }
}
</script>

<template>
    <AdminLayout title="Kategori">
        <!-- Summary -->
        <div class="mb-6 grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-4">
            <StatCard variant="primary" class="p-3 md:p-5">
                <template #title>Total Kategori</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold tabular-nums">{{ categories.length }}</p>
                </template>
                <template #icon><Tags class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="success" class="p-3 md:p-5">
                <template #title>Aktif</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ activeCount }}</p>
                </template>
                <template #icon><CheckCircle2 class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="default" class="p-3 md:p-5">
                <template #title>Nonaktif</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold tabular-nums text-muted-foreground">{{ categories.length - activeCount }}</p>
                </template>
                <template #icon><EyeOff class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="default" class="p-3 md:p-5">
                <template #title>Total Produk</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold tabular-nums">{{ totalProducts }}</p>
                </template>
                <template #icon><Package class="h-5 w-5" /></template>
            </StatCard>
        </div>

        <!-- Table Toolbar -->
        <TableToolbar
            v-model="searchQuery"
            search-placeholder="Cari kategori..."
            :has-active-filters="hasActiveFilters"
            @clear="clearFilters"
        >
            <template #filters>
                <FilterSelect
                    :model-value="filterValues.status"
                    :options="tableFeatures.filters.status"
                    @update:model-value="setFilter('status', $event)"
                />
                <Button size="sm" class="flex-1 sm:flex-none h-9" @click="openAddCategory">
                    <Plus class="mr-1 h-4 w-4" />
                    Tambah
                </Button>
            </template>
        </TableToolbar>

        <!-- Categories Table -->
        <Card variant="elevated" class="mt-4">
            <CardContent class="p-0">
                <!-- Mobile: kartu per baris supaya tabel tidak perlu digeser horizontal -->
                <div v-if="categories.length > 0" class="divide-y md:hidden">
                    <div
                        v-for="category in filteredAndSortedData"
                        :key="`card-${category.id}`"
                        class="flex items-start gap-3 p-3"
                        :class="{ 'opacity-60': !category.is_active }"
                    >
                        <div
                            class="mt-1 h-3 w-3 shrink-0 rounded-full border"
                            :style="category.color ? { backgroundColor: category.color } : { backgroundColor: 'transparent' }"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="break-words font-medium">{{ category.name }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <Badge :variant="category.is_active ? 'success' : 'secondary'">
                                    {{ category.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
                                <Badge variant="outline" class="font-normal tabular-nums">
                                    {{ category.products_count }} produk
                                </Badge>
                                <Badge variant="outline" class="font-normal tabular-nums">
                                    Urutan {{ category.sort_order }}
                                </Badge>
                                <span
                                    v-if="category.color"
                                    class="rounded px-1.5 py-0.5 font-mono text-[10px] uppercase"
                                    :style="{ backgroundColor: category.color + '30', color: category.color }"
                                >
                                    {{ category.color }}
                                </span>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="openEditCategory(category)">
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="deleteTarget = category"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                    <p v-if="filteredAndSortedData.length === 0" class="py-12 text-center text-muted-foreground">
                        Tidak ada kategori sesuai filter
                    </p>
                </div>

                <div v-if="categories.length > 0" class="hidden md:block">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b bg-muted/30">
                                <TableHeadSortable
                                    label="Nama"
                                    sort-key="name"
                                    :current-sort-key="sortKey"
                                    :sort-dir="sortDir"
                                    @sort="setSort"
                                />
                                <th class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:table-cell">Warna</th>
                                <TableHeadSortable
                                    label="Urutan"
                                    sort-key="sort_order"
                                    :current-sort-key="sortKey"
                                    :sort-dir="sortDir"
                                    align="center"
                                    class-names="hidden md:table-cell"
                                    @sort="setSort"
                                />
                                <TableHeadSortable
                                    label="Produk"
                                    sort-key="products_count"
                                    :current-sort-key="sortKey"
                                    :sort-dir="sortDir"
                                    align="center"
                                    @sort="setSort"
                                />
                                <TableHeadSortable
                                    label="Status"
                                    sort-key="is_active"
                                    :current-sort-key="sortKey"
                                    :sort-dir="sortDir"
                                    align="center"
                                    class-names="hidden md:table-cell"
                                    @sort="setSort"
                                />
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <tr
                                v-for="category in filteredAndSortedData"
                                :key="category.id"
                                class="border-b transition-colors hover:bg-muted/20"
                                :class="{ 'opacity-60': !category.is_active }"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="h-3 w-3 shrink-0 rounded-full border"
                                            :style="category.color
                                                ? { backgroundColor: category.color }
                                                : { backgroundColor: 'transparent' }"
                                        />
                                        <span class="font-medium">{{ category.name }}</span>
                                    </div>
                                </td>
                                <td class="hidden px-4 py-3 sm:table-cell">
                                    <span
                                        v-if="category.color"
                                        class="inline-block rounded px-2 py-0.5 font-mono text-xs uppercase"
                                        :style="{ backgroundColor: category.color + '30', color: category.color }"
                                    >
                                        {{ category.color }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                                <td class="hidden px-4 py-3 text-center tabular-nums md:table-cell">{{ category.sort_order }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col items-center gap-1">
                                        <Badge variant="outline" class="w-fit font-normal tabular-nums">
                                            {{ category.products_count }} <span class="ml-0.5 hidden sm:inline">produk</span>
                                        </Badge>
                                        <Badge :variant="category.is_active ? 'success' : 'secondary'" class="w-fit md:hidden">
                                            {{ category.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="hidden px-4 py-3 text-center md:table-cell">
                                    <Badge :variant="category.is_active ? 'success' : 'secondary'">
                                        {{ category.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 w-8 p-0"
                                            @click="openEditCategory(category)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 w-8 p-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="deleteTarget = category"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredAndSortedData.length === 0">
                                <td colspan="6" class="py-12 text-center text-muted-foreground">
                                    Tidak ada kategori sesuai filter
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="categories.length === 0"
                    class="flex flex-col items-center justify-center py-16 text-center"
                >
                    <Tags class="mb-3 h-12 w-12 text-muted-foreground/50" />
                    <p class="text-muted-foreground">Belum ada kategori</p>
                    <Button size="sm" class="mt-3" @click="openAddCategory">
                        <Plus class="h-4 w-4" />
                        Tambah Kategori Pertama
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!deleteTarget" @update:open="(v) => { if (!v) deleteTarget = null }">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Hapus Kategori</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus kategori
                        <strong>{{ deleteTarget?.name }}</strong>?
                        Produk dalam kategori ini akan menjadi tanpa kategori.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteTarget = null">Batal</Button>
                    <Button variant="destructive" @click="handleDelete">Ya, Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Create/Edit Category Modal -->
        <Dialog :open="showFormDialog" @update:open="showFormDialog = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ formMode === 'create' ? 'Tambah Kategori' : 'Edit Kategori' }}</DialogTitle>
                    <DialogDescription>
                        {{ formMode === 'create' ? 'Kategori baru untuk mengelompokkan produk' : 'Ubah data kategori' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div class="space-y-2">
                        <Label for="cat_name">Nama <span class="text-destructive">*</span></Label>
                        <Input
                            id="cat_name"
                            v-model="categoryForm.name"
                            placeholder="Contoh: Makanan, Minuman"
                            :disabled="processing"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="cat_color">Warna (opsional)</Label>
                        <div class="flex items-center gap-2">
                            <input
                                id="cat_color"
                                v-model="categoryForm.color"
                                type="color"
                                class="h-9 w-14 cursor-pointer rounded border border-input bg-transparent p-0.5"
                                :disabled="processing"
                            />
                            <Input
                                v-model="categoryForm.color"
                                placeholder="#3b82f6"
                                class="font-mono flex-1"
                                :disabled="processing"
                            />
                        </div>
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <button
                                v-for="c in presetColors"
                                :key="c"
                                type="button"
                                class="h-6 w-6 cursor-pointer rounded-full border-2 transition-transform hover:scale-110"
                                :class="{ 'border-foreground ring-2 ring-offset-2': categoryForm.color === c }"
                                :style="{ backgroundColor: c }"
                                @click="categoryForm.color = c"
                            />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="cat_sort">Urutan Tampil</Label>
                        <Input
                            id="cat_sort"
                            v-model.number="categoryForm.sort_order"
                            type="number"
                            min="0"
                            :disabled="processing"
                        />
                    </div>
                    <div class="flex items-center gap-2 rounded-lg border p-3">
                        <Checkbox
                            id="cat_active"
                            :checked="categoryForm.is_active"
                            @update:checked="(v) => categoryForm.is_active = !!v"
                        />
                        <Label for="cat_active" class="cursor-pointer text-sm">Aktif — tampil di daftar</Label>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showFormDialog = false" :disabled="processing">Batal</Button>
                    <Button @click="submitCategory" :disabled="processing || !categoryForm.name?.trim()">
                        <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                        {{ formMode === 'create' ? 'Tambah Kategori' : 'Simpan' }}
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
