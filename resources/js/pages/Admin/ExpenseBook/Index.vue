<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import AlertDialog from '@/components/AlertDialog.vue'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'
import { formatCurrency } from '@/lib/utils'
import { BookOpen, Plus, Pencil, Trash2, Search, TrendingDown, ChevronLeft, ChevronRight, Calendar } from 'lucide-vue-next'

interface StoreItem {
    id: number
    name: string
    slug: string
}

interface ExpenseItem {
    id: number
    category: string
    description: string
    amount: number
    expense_date: string
    created_at: string
    creator_name: string | null
}

interface CategoryOption {
    value: string
    label: string
}

interface PaginatedExpenses {
    data: ExpenseItem[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    prev_page_url: string | null
    next_page_url: string | null
}

const props = defineProps<{
    store: StoreItem
    stores: StoreItem[]
    selected_date: string
    expenses: PaginatedExpenses
    total_amount: number
    category_filter: string
    search: string
    category_options: CategoryOption[]
}>()

const categoryLabels: Record<string, string> = {
    purchase: 'Pembelian',
    supplies: 'Perlengkapan',
    utilities: 'Listrik/Utilities',
    maintenance: 'Pemeliharaan',
    salary: 'Gaji',
    other: 'Lainnya',
}

const form = ref({
    category: 'other' as string,
    description: '',
    amount: '',
    expense_date: props.selected_date ?? new Date().toISOString().slice(0, 10),
})

const filterState = ref({
    selected_date: props.selected_date ?? '',
    category: props.category_filter ?? 'all',
    search: props.search ?? '',
})

/** YYYY-MM-DD → shift by days */
function shiftDate(ymd: string, deltaDays: number): string {
    const [y, m, d] = ymd.split('-').map(Number)
    const dt = new Date(y, m - 1, d + deltaDays)
    const yy = dt.getFullYear()
    const mm = String(dt.getMonth() + 1).padStart(2, '0')
    const dd = String(dt.getDate()).padStart(2, '0')
    return `${yy}-${mm}-${dd}`
}

const selectedDateLabel = computed(() => {
    const ymd = filterState.value.selected_date
    if (!ymd) return ''
    const [y, m, d] = ymd.split('-').map(Number)
    const dt = new Date(y, m - 1, d)
    return dt.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
})

const isToday = computed(() => {
    const t = new Date().toISOString().slice(0, 10)
    return filterState.value.selected_date === t
})

const totalCardTitle = computed(() =>
    isToday.value ? 'Total Pengeluaran (Hari Ini)' : 'Total Pengeluaran (Tanggal Terpilih)',
)

const prevDate = computed(() => shiftDate(filterState.value.selected_date || new Date().toISOString().slice(0, 10), -1))
const nextDate = computed(() => shiftDate(filterState.value.selected_date || new Date().toISOString().slice(0, 10), 1))

const processing = ref(false)
const deleteTarget = ref<ExpenseItem | null>(null)
const editTarget = ref<ExpenseItem | null>(null)
const alertState = ref<{ open: boolean; message: string; variant: 'info' | 'error' | 'warning' }>({
    open: false,
    message: '',
    variant: 'error',
})

function showAlert(message: string, variant: 'info' | 'error' | 'warning' = 'error') {
    alertState.value = { open: true, message, variant }
}
const editForm = ref({
    category: '',
    description: '',
    amount: '',
    expense_date: '',
})

watch(() => [props.selected_date, props.category_filter, props.search], ([sd, cf, s]) => {
    filterState.value.selected_date = (sd as string) ?? ''
    filterState.value.category = (cf as string) ?? 'all'
    filterState.value.search = (s as string) ?? ''
    form.value.expense_date = (sd as string) || form.value.expense_date
})

function queryParams(extra: Record<string, string | number | undefined> = {}) {
    return {
        store: props.store.id,
        date: filterState.value.selected_date || undefined,
        category: filterState.value.category !== 'all' ? filterState.value.category : undefined,
        search: filterState.value.search || undefined,
        ...extra,
    }
}

function changeStore(storeId: number) {
    router.get('/admin/expense-book', {
        store: storeId,
        date: filterState.value.selected_date || undefined,
        category: filterState.value.category !== 'all' ? filterState.value.category : undefined,
        search: filterState.value.search || undefined,
    })
}

function applyFilters() {
    router.get('/admin/expense-book', queryParams(), { preserveState: true })
}

function goToDay(ymd: string) {
    filterState.value.selected_date = ymd
    router.get('/admin/expense-book', queryParams({ date: ymd }), { preserveState: true })
}

function goToToday() {
    const t = new Date().toISOString().slice(0, 10)
    goToDay(t)
}

function submitForm() {
    if (!form.value.description.trim() || !form.value.amount || Number(form.value.amount) < 0) return
    processing.value = true
    router.post('/admin/expense-book', {
        ...form.value,
        amount: Number(form.value.amount),
        store: props.store.id,
    }, {
        preserveScroll: true,
        onFinish: () => { processing.value = false },
        onSuccess: () => {
            form.value = {
                category: 'other',
                description: '',
                amount: '',
                expense_date: new Date().toISOString().slice(0, 10),
            }
        },
    })
}

function openEdit(expense: ExpenseItem) {
    editTarget.value = expense
    editForm.value = {
        category: expense.category,
        description: expense.description,
        amount: String(expense.amount),
        expense_date: expense.expense_date,
    }
}

function submitEdit() {
    if (!editTarget.value || !editForm.value.description.trim() || !editForm.value.amount || Number(editForm.value.amount) < 0) return
    const snapshot = editTarget.value
    const id = snapshot.id
    processing.value = true
    editTarget.value = null
    router.put(`/admin/expense-book/${id}`, {
        category: editForm.value.category,
        description: editForm.value.description,
        amount: Number(editForm.value.amount),
        expense_date: editForm.value.expense_date,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            editTarget.value = snapshot
            showAlert('Gagal menyimpan: ' + Object.values(errors).flat().join(', '))
        },
        onFinish: () => {
            processing.value = false
        },
    })
}

function handleDelete() {
    if (!deleteTarget.value) return
    const id = deleteTarget.value.id
    deleteTarget.value = null
    router.delete(`/admin/expense-book/${id}`, {
        preserveScroll: true,
        onError: (errors) => {
            showAlert('Gagal menghapus: ' + Object.values(errors).flat().join(', '))
        },
    })
}

function goToPage(url: string | null) {
    if (url) router.visit(url)
}

function onDateInputChange() {
    const d = filterState.value.selected_date
    if (d) goToDay(d)
}
</script>

<template>
    <AdminLayout :title="`Pembukuan Harian - ${store.name}`">
        <div class="space-y-4 md:space-y-6">
            <!-- Header Mobile Actions -->
            <div class="flex flex-col gap-4 lg:hidden">
                <StatCard variant="destructive" class="border-none shadow-sm">
                    <template #title>{{ totalCardTitle }}</template>
                    <template #value>
                        <p class="text-2xl md:text-3xl font-black text-destructive tabular-nums tracking-tight">
                            {{ formatCurrency(total_amount) }}
                        </p>
                    </template>
                    <template #subtitle>{{ expenses.total }} catatan transaksi</template>
                    <template #icon><TrendingDown class="h-6 w-6" /></template>
                </StatCard>
            </div>
            <!-- Filters Card -->
            <Card variant="elevated" class="overflow-hidden">
                <CardHeader class="p-4 md:p-6 pb-2 md:pb-4">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-lg md:text-xl font-bold">
                            <BookOpen class="h-5 w-5 text-primary" />
                            Pembukuan Harian
                        </CardTitle>
                    </div>
                    <CardDescription class="text-xs md:text-sm">
                        Kelola dan tinjau pengeluaran operasional toko Anda.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-4 md:p-6 pt-2">
                    <div class="space-y-4">
                        <div v-if="stores.length > 1" class="flex flex-col gap-1.5">
                            <Label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Pilih Toko</Label>
                            <select
                                :value="store.id"
                                class="filter-select h-10 w-full rounded-md border border-input bg-background px-3 text-sm font-medium"
                                @change="changeStore(Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Navigasi Tanggal</Label>
                                <div class="flex items-center gap-1">
                                    <Button type="button" variant="outline" size="icon" class="h-10 w-10 shrink-0" @click="goToDay(prevDate)">
                                        <ChevronLeft class="h-4 w-4" />
                                    </Button>
                                    <div class="relative flex-1">
                                        <Calendar class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            v-model="filterState.selected_date"
                                            type="date"
                                            class="h-10 w-full pl-9 font-medium"
                                            @change="onDateInputChange"
                                        />
                                    </div>
                                    <Button type="button" variant="outline" size="icon" class="h-10 w-10 shrink-0" @click="goToDay(nextDate)">
                                        <ChevronRight class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Filter Kategori</Label>
                                <select
                                    v-model="filterState.category"
                                    class="filter-select h-10 w-full rounded-md border border-input bg-background px-3 text-sm font-medium"
                                >
                                    <option v-for="opt in category_options" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5 md:col-span-2 lg:col-span-1">
                                <Label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Pencarian</Label>
                                <div class="flex gap-2">
                                    <div class="relative flex-1">
                                        <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            v-model="filterState.search"
                                            placeholder="Cari deskripsi..."
                                            class="h-10 pl-9"
                                            @keyup.enter="applyFilters"
                                        />
                                    </div>
                                    <Button class="h-10 px-4 md:px-6 font-bold" @click="applyFilters">
                                        <Search class="h-4 w-4 sm:mr-2" />
                                        <span class="hidden sm:inline">Terapkan</span>
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedDateLabel" class="flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                            <p class="text-[11px] font-bold uppercase tracking-widest text-primary italic">
                                {{ selectedDateLabel }}
                            </p>
                            <Button v-if="!isToday" variant="ghost" size="sm" class="h-7 text-[10px] font-black uppercase text-primary" @click="goToToday">
                                Kembali ke Hari Ini
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-5">
                <!-- Form tambah pengeluaran (Now First on Mobile & Desktop for better flow) -->
                <div class="lg:col-span-2">
                    <Card variant="elevated" class="overflow-hidden">
                        <CardHeader class="p-4 md:p-6 pb-2 md:pb-4">
                            <CardTitle class="flex items-center gap-2 text-lg md:text-xl font-bold">
                                <Plus class="h-5 w-5 text-primary" />
                                Catat Pengeluaran
                            </CardTitle>
                            <CardDescription class="text-xs md:text-sm">
                                Isi form untuk mencatat pengeluaran harian.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-4 md:p-6 pt-2">
                            <form class="space-y-4" @submit.prevent="submitForm">
                                <div class="space-y-1.5">
                                    <Label for="category" class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Kategori</Label>
                                    <select
                                        id="category"
                                        v-model="form.category"
                                        class="filter-select flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm font-medium"
                                    >
                                        <option v-for="opt in category_options.filter(o => o.value !== 'all')" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="description" class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Deskripsi</Label>
                                    <Input
                                        id="description"
                                        v-model="form.description"
                                        placeholder="Contoh: Beli token listrik @warung"
                                        class="h-10 font-medium"
                                        required
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <Label for="amount" class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Jumlah (Rp)</Label>
                                        <Input
                                            id="amount"
                                            v-model="form.amount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="0"
                                            class="h-10 font-bold tabular-nums"
                                            required
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="expense_date" class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Tanggal</Label>
                                        <Input
                                            id="expense_date"
                                            v-model="form.expense_date"
                                            type="date"
                                            class="h-10 font-medium"
                                            required
                                        />
                                    </div>
                                </div>
                                <Button type="submit" class="w-full h-11 font-black uppercase tracking-widest shadow-lg shadow-primary/20" :disabled="processing">
                                    {{ processing ? 'Menyimpan...' : 'Simpan Pengeluaran' }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <!-- Daftar pengeluaran -->
                <div class="lg:col-span-3">
                    <div class="mb-6 hidden lg:block">
                        <StatCard variant="destructive">
                            <template #title>{{ totalCardTitle }}</template>
                            <template #value>
                                <p class="text-2xl font-black text-destructive tabular-nums tracking-tight">{{ formatCurrency(total_amount) }}</p>
                            </template>
                            <template #subtitle>{{ expenses.total }} catatan transaksi</template>
                            <template #icon><TrendingDown class="h-5 w-5" /></template>
                        </StatCard>
                    </div>

                    <Card variant="elevated" class="overflow-hidden">
                        <CardHeader class="p-4 md:p-6 pb-2 md:pb-4">
                            <CardTitle class="text-lg md:text-xl font-bold">Riwayat Pengeluaran</CardTitle>
                            <CardDescription class="text-xs md:text-sm">
                                Daftar pengeluaran pada tanggal yang dipilih
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="w-full overflow-x-auto overflow-y-hidden">
                                <table class="w-full min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b bg-muted/30 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                            <th class="px-4 md:px-6 py-4">Waktu & Deskripsi</th>
                                            <th class="px-4 md:px-6 py-4 hidden sm:table-cell">Kategori</th>
                                            <th class="px-4 md:px-6 py-4 text-right">Nominal</th>
                                            <th class="px-4 md:px-6 py-4 w-16 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr
                                            v-for="e in expenses.data"
                                            :key="e.id"
                                            class="group hover:bg-muted/50 transition-colors"
                                        >
                                            <td class="px-4 md:px-6 py-4">
                                                <div class="flex flex-col gap-1">
                                                    <span class="text-xs font-bold text-muted-foreground tabular-nums">{{ e.created_at }}</span>
                                                    <span class="font-medium text-foreground line-clamp-2">{{ e.description }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 md:px-6 py-4">
                                                <Badge variant="secondary" class="rounded-md font-bold text-[10px] uppercase">
                                                    {{ categoryLabels[e.category] ?? e.category }}
                                                </Badge>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 text-right">
                                                <span class="font-black text-destructive tabular-nums">
                                                    {{ formatCurrency(e.amount) }}
                                                </span>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 hidden md:table-cell">
                                                <span class="text-xs text-muted-foreground font-medium">{{ e.creator_name ?? '—' }}</span>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="h-9 w-9 rounded-full hover:bg-primary/10 hover:text-primary transition-colors"
                                                        @click.stop="openEdit(e)"
                                                    >
                                                        <Pencil class="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="h-9 w-9 rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors text-destructive/60"
                                                        @click.stop="deleteTarget = e"
                                                    >
                                                        <Trash2 class="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!expenses.data.length">
                                            <td colspan="6" class="px-4 md:px-6 py-16 text-center">
                                                <div class="flex flex-col items-center gap-2">
                                                    <BookOpen class="h-10 w-10 text-muted-foreground/30" />
                                                    <p class="text-sm font-medium text-muted-foreground">Belum ada pengeluaran pada tanggal ini</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div
                                v-if="expenses.last_page > 1"
                                class="flex items-center justify-between border-t px-6 py-3"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{ expenses.from }}–{{ expenses.to }} dari {{ expenses.total }}
                                </p>
                                <div class="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="!expenses.prev_page_url"
                                        @click="goToPage(expenses.prev_page_url)"
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="!expenses.next_page_url"
                                        @click="goToPage(expenses.next_page_url)"
                                    >
                                        Selanjutnya
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Edit Dialog -->
        <Dialog :open="!!editTarget" @update:open="(v: boolean) => !v && (editTarget = null)">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Ubah Pengeluaran</DialogTitle>
                </DialogHeader>
                <form v-if="editTarget" class="space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <Label>Kategori</Label>
                        <select
                            v-model="editForm.category"
                            class="filter-select mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option v-for="opt in category_options.filter(o => o.value !== 'all')" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label>Deskripsi</Label>
                        <Input v-model="editForm.description" class="mt-1" required />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Jumlah (Rp)</Label>
                            <Input
                                v-model="editForm.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1"
                                required
                            />
                        </div>
                        <div>
                            <Label>Tanggal</Label>
                            <Input v-model="editForm.expense_date" type="date" class="mt-1" required />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="editTarget = null">
                            Batal
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Menyimpan...' : 'Simpan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirm -->
        <ConfirmDialog
            :open="!!deleteTarget"
            title="Hapus Pengeluaran"
            description="Apakah Anda yakin ingin menghapus catatan pengeluaran ini?"
            variant="destructive"
            confirm-label="Ya, Hapus"
            @update:open="(v) => !v && (deleteTarget = null)"
            @confirm="handleDelete"
        />

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
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.25rem 1.25rem;
}
</style>
