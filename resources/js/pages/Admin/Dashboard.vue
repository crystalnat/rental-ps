<script setup lang="ts">
import { toRef, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useTableFeatures } from '@/composables/useTableFeatures'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
import { formatCurrency, formatDateTime } from '@/lib/utils'
import { Label } from '@/components/ui/label'
import {
    TrendingUp,
    ShoppingCart,
    Wallet,
    Clock,
    TrendingDown,
    CalendarDays,
    Package,
    Users,
    UserCog,
    AlertTriangle,
    Store,
    Receipt,
    BarChart3,
    CreditCard,
} from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, Title, Tooltip, Legend, Filler)

interface StoreItem {
    id: number
    name: string
    slug: string
}

interface Stats {
    today_revenue: number
    today_orders: number
    today_expenses: number
    today_net: number
    pending_orders: number
    month_revenue: number
    month_orders: number
    month_expenses: number
    total_products: number
    total_customers: number
    total_employees: number
    low_stock_count: number
}

interface TopProduct {
    product_name: string
    total_qty: number
    total_amount: number
}

interface LowStockAlert {
    product_name: string
    store_name: string
    current_stock: number
    min_stock: number
    unit: string
}

interface PerStoreSummary {
    id: number
    name: string
    revenue: number
    orders: number
    expenses: number
    net: number
}

interface RecentOrder {
    id: number
    order_code: string
    status: string
    payment_status: string
    final_amount: number
    type: string
    customer_name: string
    table_name: string | null
    store_name: string
    created_at: string
}

const props = defineProps<{
    store: StoreItem | null
    stores: StoreItem[]
    stats: Stats
    chart_sales_week: { labels: string[]; data: number[] }
    chart_income_expense_week: { labels: string[]; income: number[]; expenses: number[] }
    top_products: TopProduct[]
    low_stock_alerts: LowStockAlert[]
    per_store_summary: PerStoreSummary[]
    recent_orders: RecentOrder[]
}>()

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline' | 'success' | 'warning'> = {
    pending: 'warning',
    confirmed: 'secondary',
    processing: 'default',
    ready: 'success',
    done: 'success',
    cancelled: 'destructive',
}

const statusLabel: Record<string, string> = {
    pending: 'Pending',
    confirmed: 'Dikonfirmasi',
    processing: 'Diproses',
    ready: 'Siap',
    done: 'Selesai',
    cancelled: 'Batal',
}

const tableFeatures = useTableFeatures(toRef(props, 'recent_orders'), {
    getSearchText: (o) => [o.order_code, o.customer_name, o.table_name, o.store_name].filter(Boolean).join(' '),
    sortableFields: ['order_code', 'customer_name', 'status', 'final_amount', 'created_at'],
    filters: {
        status: [
            { value: 'all', label: 'Semua Status' },
            ...Object.entries(statusLabel).map(([value, label]) => ({ value, label })),
        ],
    },
    getFilterValue: (item, key) => (key === 'status' ? item.status : null),
})

const { searchQuery, sortKey, sortDir, filterValues, filteredAndSortedData, setSort, setFilter, clearFilters } = tableFeatures
const hasActiveFilters = computed(
    () =>
        !!searchQuery.value ||
        !!sortKey.value ||
        (filterValues.value?.status && filterValues.value.status !== 'all'),
)

function selectStore(storeId: number | null) {
    router.get(route('admin.dashboard'), storeId ? { store: storeId } : {}, { preserveState: false })
}

const chartSalesConfig = computed(() => ({
    labels: props.chart_sales_week.labels,
    datasets: [{
        label: 'Penjualan',
        data: props.chart_sales_week.data,
        backgroundColor: 'rgba(16, 185, 129, 0.6)',
        borderColor: 'rgb(16, 185, 129)',
        borderWidth: 1,
    }],
}))

const chartIncomeExpenseConfig = computed(() => ({
    labels: props.chart_income_expense_week.labels,
    datasets: [
        {
            label: 'Pemasukan',
            data: props.chart_income_expense_week.income,
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.3,
        },
        {
            label: 'Pengeluaran',
            data: props.chart_income_expense_week.expenses,
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            fill: true,
            tension: 0.3,
        },
    ],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top' as const },
        tooltip: {
            callbacks: {
                label: (ctx: { raw: number }) => formatCurrency(ctx.raw),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { callback: (v: number) => formatCurrency(v) },
        },
    },
}
</script>

<template>
    <AdminLayout title="Dashboard">
        <!-- Store Selector (kiri) + Quick Links (kanan) -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
            <div v-if="stores.length > 1" class="flex items-center gap-2">
                <Label class="text-sm font-medium">Toko</Label>
                <select
                    :value="store?.id ?? ''"
                    class="filter-select h-9 min-w-[180px] rounded-md border border-input bg-background pl-3 pr-9 py-1 text-sm"
                    @change="selectStore(($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
                >
                    <option value="">Semua Toko</option>
                    <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="flex flex-wrap gap-2" :class="stores.length <= 1 ? 'ml-auto' : ''">
            <Button variant="outline" size="sm" as-child>
                <Link :href="route('admin.cashier.index')">
                    <CreditCard class="mr-2 h-4 w-4" />
                    Kasir
                </Link>
            </Button>
            <Button variant="outline" size="sm" as-child>
                <Link :href="route('admin.orders.index')">
                    <Receipt class="mr-2 h-4 w-4" />
                    Pesanan
                </Link>
            </Button>
            <Button variant="outline" size="sm" as-child>
                <Link :href="route('admin.reports.index')">
                    <BarChart3 class="mr-2 h-4 w-4" />
                    Laporan
                </Link>
            </Button>
            <Button variant="outline" size="sm" as-child>
                <Link :href="route('admin.customers.index')">
                    <Users class="mr-2 h-4 w-4" />
                    Pelanggan
                </Link>
            </Button>
            </div>
        </div>

        <!-- Stat Cards: Ringkasan Hari Ini -->
        <section class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">Ringkasan Hari Ini</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard variant="success">
                    <template #title>Pendapatan</template>
                    <template #value>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(stats.today_revenue) }}</p>
                    </template>
                    <template #subtitle>{{ stats.today_orders }} transaksi selesai</template>
                    <template #icon><TrendingUp class="h-5 w-5" /></template>
                </StatCard>
                <StatCard :variant="stats.today_net >= 0 ? 'primary' : 'destructive'">
                    <template #title>Net Profit</template>
                    <template #value>
                        <p class="text-2xl font-bold" :class="stats.today_net >= 0 ? 'text-primary' : 'text-destructive'">
                            {{ formatCurrency(stats.today_net) }}
                        </p>
                    </template>
                    <template #subtitle>Pengeluaran: {{ formatCurrency(stats.today_expenses) }}</template>
                    <template #icon><Wallet class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="warning">
                    <template #title>Pesanan Masuk</template>
                    <template #value>
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.pending_orders }}</p>
                    </template>
                    <template #subtitle>Menunggu diproses</template>
                    <template #icon><Clock class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="default">
                    <template #title>Total Transaksi</template>
                    <template #value>
                        <p class="text-2xl font-bold">{{ stats.today_orders }}</p>
                    </template>
                    <template #subtitle>Hari ini</template>
                    <template #icon><ShoppingCart class="h-5 w-5" /></template>
                </StatCard>
            </div>
        </section>

        <!-- Stat Cards: Lainnya -->
        <section class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">Detail & Master Data</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard variant="destructive">
                    <template #title>Pengeluaran Hari Ini</template>
                    <template #value>
                        <p class="text-2xl font-bold text-destructive">{{ formatCurrency(stats.today_expenses) }}</p>
                    </template>
                    <template #icon><TrendingDown class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="muted">
                    <template #title>Pendapatan Bulan Ini</template>
                    <template #value>
                        <p class="text-2xl font-bold">{{ formatCurrency(stats.month_revenue) }}</p>
                    </template>
                    <template #icon><CalendarDays class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="default">
                    <template #title>Pesanan Bulan Ini</template>
                    <template #value>
                        <p class="text-2xl font-bold">{{ stats.month_orders ?? 0 }}</p>
                    </template>
                    <template #subtitle>Transaksi terbayar</template>
                    <template #icon><Receipt class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="destructive">
                    <template #title>Pengeluaran Bulan Ini</template>
                    <template #value>
                        <p class="text-2xl font-bold text-destructive">{{ formatCurrency(stats.month_expenses ?? 0) }}</p>
                    </template>
                    <template #icon><TrendingDown class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="muted">
                    <template #title>Produk</template>
                    <template #value>
                        <p class="text-2xl font-bold">{{ stats.total_products }}</p>
                    </template>
                    <template #subtitle>Total produk aktif</template>
                    <template #icon><Package class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="muted">
                    <template #title>Pelanggan</template>
                    <template #value>
                        <p class="text-2xl font-bold">{{ stats.total_customers }}</p>
                    </template>
                    <template #subtitle>Total pelanggan</template>
                    <template #icon><Users class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="muted">
                    <template #title>Karyawan</template>
                    <template #value>
                        <p class="text-2xl font-bold">{{ stats.total_employees }}</p>
                    </template>
                    <template #subtitle>Karyawan aktif</template>
                    <template #icon><UserCog class="h-5 w-5" /></template>
                </StatCard>
                <StatCard :variant="stats.low_stock_count > 0 ? 'warning' : 'muted'">
                    <template #title>Stok Rendah</template>
                    <template #value>
                        <p class="text-2xl font-bold" :class="stats.low_stock_count > 0 ? 'text-amber-600 dark:text-amber-400' : ''">
                            {{ stats.low_stock_count }}
                        </p>
                    </template>
                    <template #subtitle>Produk perlu restock</template>
                    <template #icon><AlertTriangle class="h-5 w-5" /></template>
                </StatCard>
            </div>
        </section>

        <!-- Charts -->
        <section class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">Grafik 7 Hari Terakhir</h2>
            <div class="grid gap-6 lg:grid-cols-2">
                <Card variant="elevated">
                    <CardHeader>
                        <CardTitle>Penjualan 7 Hari Terakhir</CardTitle>
                            <CardDescription>Tren penjualan harian</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[280px]">
                            <Bar :data="chartSalesConfig" :options="chartOptions" />
                        </div>
                    </CardContent>
                </Card>
                <Card variant="elevated">
                    <CardHeader>
                        <CardTitle>Pemasukan vs Pengeluaran</CardTitle>
                        <CardDescription>7 hari terakhir</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[280px]">
                            <Line :data="chartIncomeExpenseConfig" :options="chartOptions" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>

        <!-- Per Store Summary (owner with multiple stores) -->
        <Card v-if="per_store_summary.length > 0" variant="elevated" class="mb-6">
            <CardHeader>
                <CardTitle>Ringkasan per Toko (Hari Ini)</CardTitle>
                <CardDescription>Pendapatan dan pengeluaran per cabang</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="s in per_store_summary"
                        :key="s.id"
                        class="rounded-lg border p-4"
                    >
                        <p class="font-medium">{{ s.name }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ s.orders }} transaksi · {{ formatCurrency(s.revenue) }}
                        </p>
                        <p class="text-xs" :class="s.net >= 0 ? 'text-success' : 'text-destructive'">
                            Net: {{ formatCurrency(s.net) }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Top Products & Low Stock -->
        <section class="mb-6">
            <div class="grid gap-6 lg:grid-cols-2">
            <Card variant="elevated">
                <CardHeader>
                    <CardTitle>Produk Terlaris Hari Ini</CardTitle>
                    <CardDescription>Top 5 berdasarkan jumlah terjual</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul v-if="top_products.length > 0" class="space-y-3">
                        <li
                            v-for="(p, i) in top_products"
                            :key="i"
                            class="flex items-center justify-between border-b pb-2 last:border-0 last:pb-0"
                        >
                            <span class="font-medium">{{ p.product_name }}</span>
                            <span class="text-sm text-muted-foreground">
                                {{ p.total_qty }} × {{ formatCurrency(p.total_amount) }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="py-4 text-center text-sm text-muted-foreground">
                        Belum ada penjualan hari ini
                    </p>
                </CardContent>
            </Card>
            <Card variant="elevated" :class="low_stock_alerts.length > 0 ? 'border-amber-500/40' : ''">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <AlertTriangle v-if="low_stock_alerts.length > 0" class="h-4 w-4 text-warning" />
                        Alert Stok Rendah
                    </CardTitle>
                    <CardDescription>Produk di bawah batas minimum</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul v-if="low_stock_alerts.length > 0" class="space-y-3">
                        <li
                            v-for="(a, i) in low_stock_alerts"
                            :key="i"
                            class="flex items-center justify-between rounded border border-warning/30 bg-warning/5 px-3 py-2"
                        >
                            <div>
                                <span class="font-medium">{{ a.product_name }}</span>
                                <span v-if="per_store_summary.length > 1" class="ml-2 text-xs text-muted-foreground">
                                    ({{ a.store_name }})
                                </span>
                            </div>
                            <span class="text-sm text-warning">
                                {{ a.current_stock }} / {{ a.min_stock }} {{ a.unit }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="py-4 text-center text-sm text-muted-foreground">
                        Semua stok aman
                    </p>
                </CardContent>
            </Card>
            </div>
        </section>

        <!-- Recent Orders -->
        <Card variant="elevated">
            <CardHeader>
                <CardTitle>Pesanan Terbaru</CardTitle>
                <CardDescription>15 transaksi terakhir</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <TableToolbar
                    v-if="recent_orders.length > 0"
                    v-model="searchQuery"
                    search-placeholder="Cari kode, customer, meja, toko..."
                    :has-active-filters="hasActiveFilters"
                    @clear="clearFilters"
                >
                    <template #filters>
                        <FilterSelect
                            :model-value="filterValues.status"
                            :options="tableFeatures.filters.status"
                            @update:model-value="setFilter('status', $event)"
                        />
                    </template>
                </TableToolbar>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <TableHeadSortable
                                    label="Kode Order"
                                    sort-key="order_code"
                                    :current-sort-key="sortKey"
                                    :sort-dir="sortDir"
                                    @sort="setSort"
                                />
                                <TableHeadSortable
                                    label="Customer"
                                    sort-key="customer_name"
                                    :current-sort-key="sortKey"
                                    :sort-dir="sortDir"
                                    @sort="setSort"
                                />
                                <th class="px-6 py-3">Meja</th>
                                <th v-if="per_store_summary.length > 1" class="px-6 py-3">Toko</th>
                                <TableHeadSortable
                                    label="Status"
                                    sort-key="status"
                                    :current-sort-key="sortKey"
                                    :sort-dir="sortDir"
                                    @sort="setSort"
                                />
                                <TableHeadSortable
                                    label="Total"
                                    sort-key="final_amount"
                                    :current-sort-key="sortKey"
                                    :sort-dir="sortDir"
                                    @sort="setSort"
                                />
                                <TableHeadSortable
                                    label="Waktu"
                                    sort-key="created_at"
                                    :current-sort-key="sortKey"
                                    :sort-dir="sortDir"
                                    @sort="setSort"
                                />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="order in filteredAndSortedData"
                                :key="order.id"
                                class="cursor-pointer border-b last:border-0 hover:bg-muted/50"
                                @click="router.visit(route('admin.orders.show', order.id))"
                            >
                                <td class="px-6 py-3 font-mono text-xs font-medium">
                                    <Link
                                        :href="route('admin.orders.show', order.id)"
                                        class="hover:underline"
                                        @click.stop
                                    >
                                        {{ order.order_code }}
                                    </Link>
                                </td>
                                <td class="px-6 py-3">{{ order.customer_name }}</td>
                                <td class="px-6 py-3 text-muted-foreground">{{ order.table_name ?? '—' }}</td>
                                <td v-if="per_store_summary.length > 1" class="px-6 py-3 text-muted-foreground">
                                    {{ order.store_name ?? '—' }}
                                </td>
                                <td class="px-6 py-3">
                                    <Badge :variant="statusVariant[order.status]">
                                        {{ statusLabel[order.status] }}
                                    </Badge>
                                </td>
                                <td class="px-6 py-3 font-medium">{{ formatCurrency(order.final_amount) }}</td>
                                <td class="px-6 py-3 text-xs text-muted-foreground">{{ formatDateTime(order.created_at) }}</td>
                            </tr>
                            <tr v-if="!filteredAndSortedData.length">
                                <td :colspan="per_store_summary.length > 1 ? 7 : 6" class="px-6 py-10 text-center text-muted-foreground">
                                    {{ recent_orders.length ? 'Tidak ada pesanan sesuai filter' : 'Belum ada pesanan' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
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
