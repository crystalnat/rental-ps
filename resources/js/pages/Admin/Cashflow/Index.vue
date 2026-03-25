<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import TableToolbar from '@/components/TableToolbar.vue'
import FilterSelect from '@/components/FilterSelect.vue'
import { TableHeadSortable } from '@/components/ui/table'
import { Bar, Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js'
import axios from 'axios'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog'
import { Separator } from '@/components/ui/separator'
import { formatCurrency } from '@/lib/utils'
import {
    TrendingUp,
    TrendingDown,
    Wallet,
    Calendar,
    Search,
    Receipt,
    ChevronRight,
    ArrowUpRight,
    ArrowDownRight,
    Loader2,
    FileText,
} from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, Title, Tooltip, Legend, Filler)

interface StoreItem {
    id: number
    name: string
    slug: string
}

interface HistoryItem {
    id: string
    type: 'income' | 'expense'
    date: string
    date_sort?: string
    description: string
    category: string | null
    category_label: string | null
    amount: number
    detail: string | null
    order_id?: number
    expense_id?: number
}

interface PaginatedHistory {
    data: HistoryItem[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    prev_page_url: string | null
    next_page_url: string | null
}

interface CategoryOption {
    value: string
    label: string
}

const props = defineProps<{
    store: StoreItem
    stores: StoreItem[]
    date_from: string
    date_to: string
    income: number
    expenses: number
    net: number
    order_count: number
    history: PaginatedHistory
    filters: { search: string; type: string; category: string; sort?: string; dir?: 'asc' | 'desc' }
    category_options: CategoryOption[]
    chart_income_expense: { labels: string[]; income: number[]; expenses: number[] }
}>()

const chartIncomeExpenseData = computed(() => ({
    labels: props.chart_income_expense.labels,
    datasets: [
        {
            label: 'Pemasukan',
            data: props.chart_income_expense.income,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: 'rgb(16, 185, 129)',
            borderWidth: 1,
        },
        {
            label: 'Pengeluaran',
            data: props.chart_income_expense.expenses,
            backgroundColor: 'rgba(239, 68, 68, 0.7)',
            borderColor: 'rgb(239, 68, 68)',
            borderWidth: 1,
        },
    ],
}))

const chartIncomeExpenseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top' as const },
        tooltip: {
            callbacks: {
                label: (ctx: any) => formatCurrency(ctx.raw),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (value: number) => formatCurrency(value),
            },
        },
    },
}

const chartIncomeExpenseLineData = computed(() => ({
    labels: props.chart_income_expense.labels,
    datasets: [
        {
            label: 'Pemasukan',
            data: props.chart_income_expense.income,
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 0,
        },
        {
            label: 'Pengeluaran',
            data: props.chart_income_expense.expenses,
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 0,
        },
    ],
}))

const filterState = ref({
    date_from: props.date_from,
    date_to: props.date_to,
    search: props.filters.search,
    type: props.filters.type,
    category: props.filters.category,
})

const sortKey = ref<string | null>(props.filters.sort ?? 'date')
const sortDir = ref<'asc' | 'desc'>(props.filters.dir ?? 'desc')

const typeFilterOptions = [
    { value: 'all', label: 'Semua Tipe' },
    { value: 'income', label: 'Pemasukan' },
    { value: 'expense', label: 'Pengeluaran' },
]
watch(
    () => props.date_from,
    (v) => { filterState.value.date_from = v ?? '' },
)
watch(
    () => props.date_to,
    (v) => { filterState.value.date_to = v ?? '' },
)
watch(
    () => props.filters.search,
    (v) => { filterState.value.search = v ?? '' },
)
watch(
    () => props.filters.type,
    (v) => { filterState.value.type = v ?? 'all' },
)
watch(
    () => props.filters.category,
    (v) => { filterState.value.category = v ?? 'all' },
)
watch(
    () => [props.filters.sort, props.filters.dir],
    () => {
        sortKey.value = props.filters.sort ?? 'date'
        sortDir.value = props.filters.dir ?? 'desc'
    },
)

function buildCashflowQuery(extra: Record<string, unknown> = {}) {
    return {
        store: props.store.id,
        date_from: filterState.value.date_from,
        date_to: filterState.value.date_to,
        search: filterState.value.search || undefined,
        type: filterState.value.type !== 'all' ? filterState.value.type : undefined,
        category: filterState.value.category !== 'all' ? filterState.value.category : undefined,
        sort: sortKey.value ?? 'date',
        dir: sortDir.value,
        ...extra,
    }
}

function changeStore(storeId: number) {
    router.get('/admin/cashflow', {
        ...buildCashflowQuery(),
        store: storeId,
    })
}

function applyFilters() {
    router.get('/admin/cashflow', buildCashflowQuery(), { preserveState: true })
}

function setSort(key: string) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortDir.value = 'asc'
    }
    router.get('/admin/cashflow', buildCashflowQuery(), { preserveState: true, preserveScroll: true })
}

function goToPage(url: string | null) {
    if (url) router.visit(url)
}

// Order Detail Modal
const selectedOrder = ref<any>(null)
const loadingDetail = ref(false)
const showDetailModal = ref(false)

async function viewOrder(id: number) {
    loadingDetail.value = true
    showDetailModal.value = true
    try {
        const res = await axios.get(`/admin/orders/${id}/detail`)
        selectedOrder.value = res.data
    } catch (e) {
        console.error(e)
        showDetailModal.value = false
    } finally {
        loadingDetail.value = false
    }
}

const hasActiveFilters = computed(
    () =>
        !!filterState.value.search ||
        filterState.value.type !== 'all' ||
        filterState.value.category !== 'all' ||
        sortKey.value !== 'date' ||
        sortDir.value !== 'desc',
)

function clearFilters() {
    filterState.value.search = ''
    filterState.value.type = 'all'
    filterState.value.category = 'all'
    sortKey.value = 'date'
    sortDir.value = 'desc'
    applyFilters()
}
</script>

<template>
    <AdminLayout :title="`Cashflow - ${store.name}`">
        <div class="space-y-6">
            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard variant="success">
                    <template #title>Pemasukan</template>
                    <template #value>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(income) }}</p>
                    </template>
                    <template #subtitle>{{ order_count }} transaksi</template>
                    <template #icon><TrendingUp class="h-5 w-5" /></template>
                </StatCard>

                <StatCard variant="destructive">
                    <template #title>Pengeluaran</template>
                    <template #value>
                        <p class="text-2xl font-bold text-destructive">{{ formatCurrency(expenses) }}</p>
                    </template>
                    <template #icon><TrendingDown class="h-5 w-5" /></template>
                </StatCard>

                <StatCard :variant="net >= 0 ? 'primary' : 'destructive'">
                    <template #title>Net</template>
                    <template #value>
                        <p class="text-2xl font-bold" :class="net >= 0 ? 'text-primary' : 'text-destructive'">
                            {{ formatCurrency(net) }}
                        </p>
                    </template>
                    <template #subtitle>Pemasukan − Pengeluaran</template>
                    <template #icon><Wallet class="h-5 w-5" /></template>
                </StatCard>

                <StatCard variant="muted">
                    <template #title>Periode</template>
                    <template #value>
                        <p class="text-2xl font-bold">{{ date_from }} – {{ date_to }}</p>
                    </template>
                    <template #icon><Calendar class="h-5 w-5" /></template>
                </StatCard>
            </div>

            <!-- Filters -->
            <Card variant="elevated">
                <CardHeader class="p-4 md:p-6 pb-4">
                    <CardTitle class="flex items-center gap-2 text-lg md:text-xl font-bold">
                        <Receipt class="h-5 w-5 text-primary" />
                        Riwayat Arus Kas
                    </CardTitle>
                    <CardDescription class="text-xs md:text-sm">
                        Pemasukan dari penjualan dan pengeluaran dalam periode yang dipilih
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 p-4 md:p-6 pt-0">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div v-if="stores.length > 1" class="flex flex-col gap-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Toko</Label>
                            <select
                                :value="store.id"
                                class="filter-select h-10 w-full rounded-md border border-input bg-background px-3 text-sm font-medium"
                                @change="changeStore(Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5" :class="stores.length > 1 ? 'md:col-start-2' : 'md:col-span-2'">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Periode</Label>
                            <div class="flex items-center gap-2">
                                <Input v-model="filterState.date_from" type="date" class="h-10 flex-1 min-w-0" />
                                <span class="shrink-0 text-muted-foreground text-xs font-bold px-1">s/d</span>
                                <Input v-model="filterState.date_to" type="date" class="h-10 flex-1 min-w-0" />
                            </div>
                        </div>
                    </div>

                    <TableToolbar
                        v-model="filterState.search"
                        search-placeholder="Cari..."
                        :has-active-filters="hasActiveFilters"
                        @clear="clearFilters"
                    >
                        <template #filters>
                            <FilterSelect
                                v-model="filterState.type"
                                :options="typeFilterOptions"
                            />
                            <FilterSelect
                                v-if="filterState.type === 'expense' || filterState.type === 'all'"
                                v-model="filterState.category"
                                :options="category_options"
                            />
                            <Button size="sm" class="flex-1 sm:flex-none h-9 md:h-9" @click="applyFilters">
                                <Search class="h-4 w-4 sm:mr-1.5" />
                                <span class="hidden sm:inline">Terapkan</span>
                            </Button>
                        </template>
                    </TableToolbar>
                </CardContent>
            </Card>

            <!-- Charts -->
            <div class="grid gap-6 lg:grid-cols-2">
                <Card variant="elevated" class="overflow-hidden">
                    <CardHeader class="p-4 md:p-6">
                        <CardTitle class="text-base md:text-lg">Pemasukan vs Pengeluaran (Bar)</CardTitle>
                        <CardDescription class="text-xs">
                            Perbandingan nominal dalam periode terpilih
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-2 md:p-6 pt-0">
                        <div class="relative h-[250px] md:h-[300px] w-full">
                            <Bar
                                v-if="chart_income_expense.labels.length > 0"
                                :data="chartIncomeExpenseData"
                                :options="chartIncomeExpenseOptions"
                            />
                            <div v-else class="flex h-full items-center justify-center text-muted-foreground text-xs">
                                Tidak ada data
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card variant="elevated" class="overflow-hidden">
                    <CardHeader class="p-4 md:p-6">
                        <CardTitle class="text-base md:text-lg">Tren Arus Kas (Line)</CardTitle>
                        <CardDescription class="text-xs">
                            Visualisasi tren harian
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-2 md:p-6 pt-0">
                        <div class="relative h-[250px] md:h-[300px] w-full">
                            <Line
                                v-if="chart_income_expense.labels.length > 0"
                                :data="chartIncomeExpenseLineData"
                                :options="chartIncomeExpenseOptions"
                            />
                            <div v-else class="flex h-full items-center justify-center text-muted-foreground text-xs">
                                Tidak ada data
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- History Table -->
            <Card variant="elevated" class="mt-4">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Riwayat Transaksi</CardTitle>
                    <CardDescription>
                        Urutkan kolom dengan klik header. Gunakan toolbar di atas untuk mencari dan memfilter.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="w-full overflow-x-auto">
                        <table class="w-full min-w-[500px] text-sm md:min-w-full">
                            <thead>
                                <tr class="border-b bg-muted/30">
                                    <TableHeadSortable
                                        label="Tanggal"
                                        sort-key="date"
                                        class="hidden md:table-cell"
                                        :current-sort-key="sortKey"
                                        :sort-dir="sortDir"
                                        @sort="setSort"
                                    />
                                    <TableHeadSortable
                                        label="Tipe"
                                        sort-key="type"
                                        class="hidden sm:table-cell"
                                        :current-sort-key="sortKey"
                                        :sort-dir="sortDir"
                                        @sort="setSort"
                                    />
                                    <TableHeadSortable
                                        label="Keterangan"
                                        sort-key="description"
                                        :current-sort-key="sortKey"
                                        :sort-dir="sortDir"
                                        @sort="setSort"
                                    />
                                    <TableHeadSortable
                                        label="Kategori"
                                        sort-key="category_label"
                                        class="hidden lg:table-cell"
                                        :current-sort-key="sortKey"
                                        :sort-dir="sortDir"
                                        @sort="setSort"
                                    />
                                    <TableHeadSortable
                                        label="Oleh"
                                        sort-key="detail"
                                        class="hidden md:table-cell"
                                        :current-sort-key="sortKey"
                                        :sort-dir="sortDir"
                                        @sort="setSort"
                                    />
                                    <TableHeadSortable
                                        label="Jumlah"
                                        sort-key="amount"
                                        align="right"
                                        :current-sort-key="sortKey"
                                        :sort-dir="sortDir"
                                        @sort="setSort"
                                    />
                                    <th class="w-20 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in history.data"
                                    :key="row.id"
                                    class="border-b transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 text-muted-foreground hidden md:table-cell whitespace-nowrap">{{ row.date }}</td>
                                    <td class="px-4 py-3 hidden sm:table-cell">
                                        <Badge
                                            class="w-fit"
                                            :class="row.type === 'income'
                                                ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                                : 'bg-destructive/15 text-destructive hover:bg-destructive/25'"
                                        >
                                            {{ row.type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-bold text-foreground">{{ row.description }}</span>
                                            <div class="flex items-center gap-1.5 md:hidden">
                                                <span class="text-[10px] text-muted-foreground">{{ row.date }}</span>
                                                <span class="h-1 w-1 rounded-full bg-border" />
                                                <span class="text-[10px]" :class="row.type === 'income' ? 'text-emerald-600' : 'text-destructive font-semibold'">
                                                    {{ row.type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}
                                                </span>
                                            </div>
                                            <span v-if="row.category_label" class="text-[11px] text-muted-foreground lg:hidden italic">#{{ row.category_label }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground hidden lg:table-cell">
                                        {{ row.category_label ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground text-xs hidden md:table-cell">{{ row.detail ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right font-bold tabular-nums" :class="row.type === 'income' ? 'text-emerald-600' : 'text-destructive'">
                                        <div class="flex flex-col items-end">
                                            <span>{{ row.type === 'income' ? '+' : '−' }}{{ formatCurrency(row.amount) }}</span>
                                            <Button
                                                v-if="row.order_id"
                                                variant="link"
                                                size="sm"
                                                class="h-auto p-0 text-[10px] text-primary"
                                                @click="viewOrder(row.order_id)"
                                            >
                                                #DETAIL
                                            </Button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell">
                                        <Button
                                            v-if="row.order_id"
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 text-xs"
                                            @click="viewOrder(row.order_id)"
                                        >
                                            Detail
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="history.data.length === 0">
                                    <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                                        Tidak ada data dalam periode ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="history.last_page > 1"
                        class="flex items-center justify-between border-t px-4 py-3"
                    >
                        <p class="text-sm text-muted-foreground">
                            Menampilkan {{ history.from }}–{{ history.to }} dari {{ history.total }} transaksi
                        </p>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!history.prev_page_url"
                                @click="goToPage(history.prev_page_url)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!history.next_page_url"
                                @click="goToPage(history.next_page_url)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Detail Order Modal -->
        <Dialog :open="showDetailModal" @update:open="showDetailModal = $event">
            <DialogContent class="max-w-md sm:max-w-lg p-0 overflow-hidden border-none shadow-2xl">
                <div v-if="loadingDetail" class="flex flex-col items-center justify-center py-20 gap-3">
                    <Loader2 class="h-8 w-8 animate-spin text-primary" />
                    <p class="text-sm text-muted-foreground animate-pulse">Memuat detail transaksi...</p>
                </div>
                <template v-else-if="selectedOrder">
                    <div class="bg-primary/5 px-6 py-5 border-b border-primary/10">
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-primary/60">Detail Transaksi</p>
                                <DialogTitle class="text-xl font-black tracking-tight flex items-center gap-2">
                                    <FileText class="h-5 w-5 text-primary" />
                                    {{ selectedOrder.order_code }}
                                </DialogTitle>
                            </div>
                            <Badge variant="outline" class="bg-background/50 backdrop-blur-sm border-primary/20 text-primary capitalize">
                                {{ selectedOrder.type.replace('_', ' ') }}
                            </Badge>
                        </div>
                    </div>
                    
                    <div class="px-6 py-5 space-y-6 max-h-[70vh] overflow-y-auto">
                        <!-- Store & Cashier Info -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold uppercase text-muted-foreground">Toko</p>
                                <p class="text-sm font-semibold">{{ selectedOrder.store_name }}</p>
                            </div>
                            <div class="space-y-1 text-right">
                                <p class="text-[10px] font-bold uppercase text-muted-foreground">Kasir</p>
                                <p class="text-sm font-semibold">{{ selectedOrder.cashier_name ?? '—' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold uppercase text-muted-foreground">Waktu</p>
                                <p class="text-xs font-medium">{{ selectedOrder.created_at }}</p>
                            </div>
                            <div class="space-y-1 text-right">
                                <p class="text-[10px] font-bold uppercase text-muted-foreground">Pelanggan</p>
                                <p class="text-sm font-semibold truncate">{{ selectedOrder.customer_name ?? 'Umum' }}</p>
                            </div>
                        </div>

                        <Separator />

                        <!-- Items Table -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold uppercase tracking-tighter text-muted-foreground">Daftar Produk</h4>
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-muted">{{ selectedOrder.items.length }} Items</span>
                            </div>
                            <div class="space-y-3">
                                <div v-for="(item, i) in selectedOrder.items" :key="i" class="flex justify-between items-start group">
                                    <div class="min-w-0 pr-4">
                                        <p class="text-sm font-bold group-hover:text-primary transition-colors">{{ item.product_name }}</p>
                                        <p class="text-[11px] text-muted-foreground italic font-medium">
                                            {{ item.quantity }} {{ item.unit }} × {{ formatCurrency(item.unit_price) }}
                                        </p>
                                    </div>
                                    <p class="text-sm font-black tabular-nums whitespace-nowrap">{{ formatCurrency(item.subtotal) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-muted/50 rounded-2xl p-4 space-y-2 border border-border/50">
                            <div class="flex justify-between text-xs font-medium">
                                <span class="text-muted-foreground">Subtotal</span>
                                <span class="tabular-nums">{{ formatCurrency(selectedOrder.subtotal) }}</span>
                            </div>
                            <div v-if="selectedOrder.discount_amount > 0" class="flex justify-between text-xs font-medium text-emerald-600">
                                <span>Diskon</span>
                                <span class="tabular-nums">-{{ formatCurrency(selectedOrder.discount_amount) }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-border/50 flex-col items-end gap-1">
                                <span class="text-[10px] font-bold uppercase text-muted-foreground tracking-widest">Total Bayar ({{ selectedOrder.payment_method }})</span>
                                <span class="text-2xl font-black text-primary p-0 leading-none tabular-nums">{{ formatCurrency(selectedOrder.final_amount) }}</span>
                            </div>
                        </div>

                        <div v-if="selectedOrder.notes" class="bg-amber-50 dark:bg-amber-950/20 rounded-xl p-3 border border-amber-100 dark:border-amber-900/30">
                            <p class="text-[10px] font-bold text-amber-800 dark:text-amber-400 uppercase mb-1">Catatan</p>
                            <p class="text-xs text-amber-700 dark:text-amber-500 leading-relaxed italic">"{{ selectedOrder.notes }}"</p>
                        </div>
                    </div>
                    
                    <div class="p-6 pt-0">
                        <Button variant="outline" class="w-full rounded-xl h-12 font-bold hover:bg-muted transition-all" @click="showDetailModal = false">
                            Tutup Detail
                        </Button>
                    </div>
                </template>
            </DialogContent>
        </Dialog>
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
