<script setup lang="ts">
import { toRef, computed } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { router, Link, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useTableFeatures } from '@/composables/useTableFeatures'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import TableToolbar from '@/components/TableToolbar.vue'
import FilterSelect from '@/components/FilterSelect.vue'
import { TableHeadSortable } from '@/components/ui/table'
import { Bar, Doughnut, Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
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
    TrendingDown,
    CalendarDays,
    Package,
    Users,
    UserCog,
    AlertTriangle,
    Receipt,
    BarChart3,
    CreditCard,
    Gamepad2,
} from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler)

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
    total_units: number
    active_units: number
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

interface TopUnit {
    unit_name: string
    total_revenue: number
    sessions_count: number
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
    top_units_by_sales: TopUnit[]
    chart_expense_by_category: { labels: string[]; amounts: number[] }
    chart_hourly_sales: { labels: string[]; data: number[]; counts: number[] }
    chart_payment_mix: { labels: string[]; amounts: number[] }
    monthly_trend: { labels: string[]; income: number[]; expenses: number[]; net: number[] }
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

const DONUT_COLORS = [
    'rgba(239, 68, 68, 0.85)', 'rgba(249, 115, 22, 0.85)', 'rgba(234, 179, 8, 0.85)',
    'rgba(34, 197, 94, 0.85)', 'rgba(59, 130, 246, 0.85)', 'rgba(139, 92, 246, 0.85)',
]

const chartExpenseCategoryConfig = computed(() => ({
    labels: props.chart_expense_by_category.labels,
    datasets: [{
        data: props.chart_expense_by_category.amounts,
        backgroundColor: DONUT_COLORS.slice(0, props.chart_expense_by_category.labels.length),
        borderWidth: 0,
    }],
}))

const chartPaymentMixConfig = computed(() => ({
    labels: props.chart_payment_mix.labels,
    datasets: [{
        data: props.chart_payment_mix.amounts,
        backgroundColor: DONUT_COLORS.slice(0, props.chart_payment_mix.labels.length),
        borderWidth: 0,
    }],
}))

const chartHourlyConfig = computed(() => ({
    labels: props.chart_hourly_sales.labels,
    datasets: [{
        label: 'Omzet',
        data: props.chart_hourly_sales.data,
        backgroundColor: 'rgba(59, 130, 246, 0.6)',
        borderColor: 'rgb(59, 130, 246)',
        borderWidth: 1,
    }],
}))

const chartMonthlyTrendConfig = computed(() => ({
    labels: props.monthly_trend.labels,
    datasets: [
        {
            label: 'Pemasukan',
            data: props.monthly_trend.income,
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.3,
        },
        {
            label: 'Pengeluaran',
            data: props.monthly_trend.expenses,
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.3,
        },
        {
            label: 'Laba Bersih',
            data: props.monthly_trend.net,
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.3,
        },
    ],
}))

// Legenda di samping memakan hampir separuh lebar layar ponsel, jadi dipindah ke bawah
const isNarrow = useMediaQuery('(max-width: 640px)')

const donutOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: (isNarrow.value ? 'bottom' : 'right') as 'bottom' | 'right',
            labels: { boxWidth: 12, padding: 10, font: { size: isNarrow.value ? 10 : 12 } },
        },
        tooltip: {
            callbacks: {
                label: (ctx: { label: string; raw: number }) => `${ctx.label}: ${formatCurrency(ctx.raw)}`,
            },
        },
    },
}))

const busiestHour = computed(() => {
    const data = props.chart_hourly_sales.data
    if (!data.some(v => v > 0)) return null
    const peakIndex = data.indexOf(Math.max(...data))
    return {
        label: props.chart_hourly_sales.labels[peakIndex],
        amount: data[peakIndex],
        orders: props.chart_hourly_sales.counts[peakIndex],
    }
})

const hasExpenseBreakdown = computed(() => props.chart_expense_by_category.labels.length > 0)
const hasPaymentMix = computed(() => props.chart_payment_mix.labels.length > 0)

// Sumbu Y pakai format singkat di layar kecil, label rupiah penuh memakan lebar area grafik
function shortCurrency(value: number): string {
    if (Math.abs(value) >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} jt`
    if (Math.abs(value) >= 1_000) return `${Math.round(value / 1_000)} rb`
    return String(value)
}

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            labels: { boxWidth: 12, padding: 10, font: { size: isNarrow.value ? 10 : 12 } },
        },
        tooltip: {
            callbacks: {
                label: (ctx: { raw: number }) => formatCurrency(ctx.raw),
            },
        },
    },
    scales: {
        x: {
            ticks: {
                font: { size: isNarrow.value ? 9 : 11 },
                maxRotation: isNarrow.value ? 60 : 0,
                autoSkip: true,
                maxTicksLimit: isNarrow.value ? 8 : 24,
            },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: {
                font: { size: isNarrow.value ? 9 : 11 },
                callback: (v: number) => (isNarrow.value ? shortCurrency(v) : formatCurrency(v)),
            },
        },
    },
}))

const isCashier = computed(() => usePage().props.auth.user.role === 'cashier')
</script>

<template>
    <AdminLayout title="Dashboard">
        <!-- Store Selector (kiri) + Quick Links (kanan) -->
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4">
            <div v-if="stores.length > 1" class="flex items-center gap-2">
                <Label class="shrink-0 text-sm font-medium">Toko</Label>
                <select
                    :value="store?.id ?? ''"
                    class="filter-select h-9 w-full rounded-md border border-input bg-background py-1 pl-3 pr-9 text-sm sm:w-auto sm:min-w-[180px]"
                    @change="selectStore(($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
                >
                    <option value="">Semua Toko</option>
                    <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div
                class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap"
                :class="stores.length <= 1 ? 'sm:ml-auto' : ''"
            >
            <Button variant="outline" size="sm" class="w-full sm:w-auto" as-child>
                <Link :href="route('admin.cashier.index')">
                    <CreditCard class="mr-2 h-4 w-4 shrink-0" />
                    Kasir
                </Link>
            </Button>
            <Button variant="outline" size="sm" class="w-full sm:w-auto" as-child>
                <Link :href="route('admin.orders.index')">
                    <Receipt class="mr-2 h-4 w-4 shrink-0" />
                    Pesanan
                </Link>
            </Button>
            <Button v-if="!isCashier" variant="outline" size="sm" class="w-full sm:w-auto" as-child>
                <Link :href="route('admin.reports.index')">
                    <BarChart3 class="mr-2 h-4 w-4 shrink-0" />
                    Laporan
                </Link>
            </Button>
            <Button v-if="!isCashier" variant="outline" size="sm" class="w-full sm:w-auto" as-child>
                <Link :href="route('admin.customers.index')">
                    <Users class="mr-2 h-4 w-4 shrink-0" />
                    Pelanggan
                </Link>
            </Button>
            </div>
        </div>

        <!-- Stat Cards: Ringkasan Hari Ini -->
        <section class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">{{ isCashier ? 'Informasi Kasir' : 'Ringkasan Hari Ini' }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatCard variant="success">
                    <template #title>Pendapatan Hari Ini</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words text-emerald-600 dark:text-emerald-400">{{ formatCurrency(stats.today_revenue) }}</p>
                    </template>
                    <template #subtitle>{{ stats.today_orders }} sesi/item terjual</template>
                    <template #icon><TrendingUp class="h-5 w-5" /></template>
                </StatCard>
                
                <StatCard v-if="isCashier" variant="destructive">
                    <template #title>Pengeluaran Harian</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words text-destructive">{{ formatCurrency(stats.today_expenses) }}</p>
                    </template>
                    <template #icon><TrendingDown class="h-5 w-5" /></template>
                </StatCard>
                <StatCard v-if="isCashier" variant="muted">
                    <template #title>Produk</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words">{{ stats.total_products }}</p>
                    </template>
                    <template #subtitle>Total produk aktif</template>
                    <template #icon><Package class="h-5 w-5" /></template>
                </StatCard>
                <StatCard v-if="isCashier" :variant="stats.low_stock_count > 0 ? 'warning' : 'muted'">
                    <template #title>Stok Rendah</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words" :class="stats.low_stock_count > 0 ? 'text-amber-600 dark:text-amber-400' : ''">
                            {{ stats.low_stock_count }}
                        </p>
                    </template>
                    <template #subtitle>Produk perlu restock</template>
                    <template #icon><AlertTriangle class="h-5 w-5" /></template>
                </StatCard>

                <template v-if="!isCashier">
                    <StatCard :variant="stats.today_net >= 0 ? 'primary' : 'destructive'">
                        <template #title>Net Profit</template>
                        <template #value>
                            <p class="text-lg sm:text-2xl font-bold tabular-nums break-words" :class="stats.today_net >= 0 ? 'text-primary' : 'text-destructive'">
                                {{ formatCurrency(stats.today_net) }}
                            </p>
                        </template>
                        <template #subtitle>Biaya Listrik/Lain: {{ formatCurrency(stats.today_expenses) }}</template>
                        <template #icon><Wallet class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard :variant="stats.active_units > 0 ? 'success' : 'muted'">
                        <template #title>Unit PS Aktif</template>
                        <template #value>
                            <p class="text-lg sm:text-2xl font-bold tabular-nums break-words" :class="stats.active_units > 0 ? 'text-emerald-600' : ''">{{ stats.active_units }} / {{ stats.total_units }}</p>
                        </template>
                        <template #subtitle>Unit sedang disewa/on</template>
                        <template #icon><Gamepad2 class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard variant="default">
                        <template #title>Total Transaksi</template>
                        <template #value>
                            <p class="text-lg sm:text-2xl font-bold tabular-nums break-words">{{ stats.today_orders }}</p>
                        </template>
                        <template #subtitle>Sesi rental & jajan hari ini</template>
                        <template #icon><ShoppingCart class="h-5 w-5" /></template>
                    </StatCard>
                </template>
            </div>
        </section>

        <!-- Stat Cards: Lainnya (Hidden for Cashier) -->
        <section v-if="!isCashier" class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">Detail & Master Data</h2>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatCard variant="destructive">
                    <template #title>Pengeluaran Hari Ini</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words text-destructive">{{ formatCurrency(stats.today_expenses) }}</p>
                    </template>
                    <template #icon><TrendingDown class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="muted">
                    <template #title>Pendapatan Bulan Ini</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words">{{ formatCurrency(stats.month_revenue) }}</p>
                    </template>
                    <template #icon><CalendarDays class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="default">
                    <template #title>Total Sesi & Transaksi</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words">{{ stats.month_orders ?? 0 }}</p>
                    </template>
                    <template #subtitle>Akumulasi bulan ini</template>
                    <template #icon><Gamepad2 class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="destructive">
                    <template #title>Pengeluaran Bulan Ini</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words text-destructive">{{ formatCurrency(stats.month_expenses ?? 0) }}</p>
                    </template>
                    <template #icon><TrendingDown class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="muted">
                    <template #title>Produk</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words">{{ stats.total_products }}</p>
                    </template>
                    <template #subtitle>Total produk aktif</template>
                    <template #icon><Package class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="muted">
                    <template #title>Pelanggan</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words">{{ stats.total_customers }}</p>
                    </template>
                    <template #subtitle>Total pelanggan</template>
                    <template #icon><Users class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="muted">
                    <template #title>Karyawan</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words">{{ stats.total_employees }}</p>
                    </template>
                    <template #subtitle>Operator aktif</template>
                    <template #icon><UserCog class="h-5 w-5" /></template>
                </StatCard>
                <StatCard :variant="stats.low_stock_count > 0 ? 'warning' : 'muted'">
                    <template #title>Stok Rendah</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-bold tabular-nums break-words" :class="stats.low_stock_count > 0 ? 'text-amber-600 dark:text-amber-400' : ''">
                            {{ stats.low_stock_count }}
                        </p>
                    </template>
                    <template #subtitle>Produk perlu restock</template>
                    <template #icon><AlertTriangle class="h-5 w-5" /></template>
                </StatCard>
            </div>
        </section>

        <!-- Charts (Hidden for Cashier) -->
        <section v-if="!isCashier" class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">Grafik 7 Hari Terakhir</h2>
            <div class="grid gap-4 sm:gap-6 md:grid-cols-2">
                <Card variant="elevated">
                    <CardHeader>
                        <CardTitle>Penjualan 7 Hari Terakhir</CardTitle>
                            <CardDescription>Tren penjualan harian</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[220px] sm:h-[260px] lg:h-[280px]">
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
                        <div class="h-[220px] sm:h-[260px] lg:h-[280px]">
                            <Line :data="chartIncomeExpenseConfig" :options="chartOptions" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>

        <!-- Analitik lanjutan (Hidden for Cashier) -->
        <section v-if="!isCashier" class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">Analitik</h2>
            <div class="grid gap-4 sm:gap-6 md:grid-cols-2">
                <Card variant="elevated">
                    <CardHeader>
                        <CardTitle>Jam Sibuk</CardTitle>
                        <CardDescription>
                            Omzet per jam, 7 hari terakhir
                            <template v-if="busiestHour">
                                &middot; puncak {{ busiestHour.label }} ({{ busiestHour.orders }} transaksi)
                            </template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[220px] sm:h-[260px] lg:h-[280px]">
                            <Bar :data="chartHourlyConfig" :options="chartOptions" />
                        </div>
                    </CardContent>
                </Card>

                <Card variant="elevated">
                    <CardHeader>
                        <CardTitle>Tren 6 Bulan</CardTitle>
                        <CardDescription>Pemasukan, pengeluaran, dan laba bersih per bulan</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[220px] sm:h-[260px] lg:h-[280px]">
                            <Line :data="chartMonthlyTrendConfig" :options="chartOptions" />
                        </div>
                    </CardContent>
                </Card>

                <Card variant="elevated">
                    <CardHeader>
                        <CardTitle>Pengeluaran per Kategori</CardTitle>
                        <CardDescription>Bulan berjalan</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[220px] sm:h-[260px] lg:h-[280px]">
                            <Doughnut v-if="hasExpenseBreakdown" :data="chartExpenseCategoryConfig" :options="donutOptions" />
                            <p v-else class="flex h-full items-center justify-center text-sm text-muted-foreground">
                                Belum ada pengeluaran bulan ini
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card variant="elevated">
                    <CardHeader>
                        <CardTitle>Metode Pembayaran</CardTitle>
                        <CardDescription>7 hari terakhir</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[220px] sm:h-[260px] lg:h-[280px]">
                            <Doughnut v-if="hasPaymentMix" :data="chartPaymentMixConfig" :options="donutOptions" />
                            <p v-else class="flex h-full items-center justify-center text-sm text-muted-foreground">
                                Belum ada transaksi
                            </p>
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
                <div class="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                    <div
                        v-for="s in per_store_summary"
                        :key="s.id"
                        class="rounded-lg border p-3 sm:p-4"
                    >
                        <p class="break-words font-medium">{{ s.name }}</p>
                        <p class="mt-1 text-sm text-muted-foreground tabular-nums">
                            {{ s.orders }} transaksi · {{ formatCurrency(s.revenue) }}
                        </p>
                        <p class="text-xs tabular-nums" :class="s.net >= 0 ? 'text-success' : 'text-destructive'">
                            Net: {{ formatCurrency(s.net) }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Top Products & Low Stock (Hidden for Cashier, replaced by individual stat cards) -->
        <section v-if="!isCashier" class="mb-6">
            <div class="grid gap-4 sm:gap-6 md:grid-cols-2">
            <Card variant="elevated">
                <CardHeader>
                    <CardTitle>Produk Terlaris Hari Ini</CardTitle>
                    <CardDescription>Jajanan & Voucher terlaris</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul v-if="top_products.length > 0" class="space-y-3">
                        <li
                            v-for="(p, i) in top_products"
                            :key="i"
                            class="flex items-center justify-between gap-3 border-b pb-2 last:border-0 last:pb-0"
                        >
                            <span class="min-w-0 flex-1 break-words font-medium">{{ p.product_name }}</span>
                            <div class="shrink-0 text-right">
                                <p class="text-xs text-muted-foreground tabular-nums">{{ p.total_qty }} terjual</p>
                                <p class="text-sm font-semibold tabular-nums">{{ formatCurrency(p.total_amount) }}</p>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="py-4 text-center text-sm text-muted-foreground">
                        Belum ada penjualan hari ini
                    </p>
                </CardContent>
            </Card>
            <Card variant="elevated">
                <CardHeader>
                    <CardTitle>Unit PS Teraktif (Bulan Ini)</CardTitle>
                    <CardDescription>Berdasarkan total omzet & jumlah sesi</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul v-if="top_units_by_sales.length > 0" class="space-y-3">
                        <li
                            v-for="(u, i) in top_units_by_sales"
                            :key="i"
                            class="flex items-center justify-between gap-3 border-b pb-2 last:border-0 last:pb-0"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 font-bold text-primary">
                                    {{ i + 1 }}
                                </div>
                                <span class="min-w-0 break-words font-medium">{{ u.unit_name }}</span>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xs text-muted-foreground tabular-nums">{{ u.sessions_count }} sesi</p>
                                <p class="text-sm font-semibold tabular-nums">{{ formatCurrency(u.total_revenue) }}</p>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="py-4 text-center text-sm text-muted-foreground">
                        Belum ada riwayat sesi bulan ini
                    </p>
                </CardContent>
            </Card>
            </div>
        </section>

        <!-- Alerts -->
        <section v-if="!isCashier" class="mb-6">
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
                            class="flex flex-col gap-1 rounded border border-warning/30 bg-warning/5 px-3 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                        >
                            <div class="min-w-0">
                                <span class="break-words font-medium">{{ a.product_name }}</span>
                                <span v-if="per_store_summary.length > 1" class="ml-2 text-xs text-muted-foreground">
                                    ({{ a.store_name }})
                                </span>
                            </div>
                            <span class="shrink-0 text-sm tabular-nums text-warning">
                                {{ a.current_stock }} / {{ a.min_stock }} {{ a.unit }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="py-4 text-center text-sm text-muted-foreground">
                        Semua stok aman
                    </p>
                </CardContent>
            </Card>
        </section>

        <!-- Recent Orders -->
        <Card variant="elevated">
            <CardHeader>
                <CardTitle>Rental & Pesanan Terbaru</CardTitle>
                <CardDescription>15 transaksi terakhir di dashboard</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <TableToolbar
                    v-if="recent_orders.length > 0"
                    v-model="searchQuery"
                    search-placeholder="Cari kode, customer, unit, toko..."
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
                                    class-names="hidden sm:table-cell"
                                    @sort="setSort"
                                />
                                <th class="hidden px-4 py-3 md:table-cell lg:px-6">Unit PS</th>
                                <th v-if="per_store_summary.length > 1" class="hidden px-4 py-3 lg:table-cell lg:px-6">Toko</th>
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
                                    class-names="hidden md:table-cell"
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
                                <td class="px-4 py-3 font-mono text-xs font-medium lg:px-6">
                                    <Link
                                        :href="route('admin.orders.show', order.id)"
                                        class="hover:underline"
                                        @click.stop
                                    >
                                        {{ order.order_code }}
                                    </Link>
                                    <!-- Customer & waktu ikut di sel kode saat kolomnya disembunyikan di layar sempit -->
                                    <span class="mt-0.5 block font-sans text-xs text-muted-foreground sm:hidden">
                                        {{ order.customer_name }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3 sm:table-cell lg:px-6">{{ order.customer_name }}</td>
                                <td class="hidden px-4 py-3 text-muted-foreground md:table-cell lg:px-6">{{ order.table_name ?? '—' }}</td>
                                <td v-if="per_store_summary.length > 1" class="hidden px-4 py-3 text-muted-foreground lg:table-cell lg:px-6">
                                    {{ order.store_name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 lg:px-6">
                                    <Badge :variant="statusVariant[order.status]">
                                        {{ statusLabel[order.status] }}
                                    </Badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums lg:px-6 lg:text-left">
                                    {{ formatCurrency(order.final_amount) }}
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-xs text-muted-foreground md:table-cell lg:px-6">
                                    {{ formatDateTime(order.created_at) }}
                                </td>
                            </tr>
                            <tr v-if="!filteredAndSortedData.length">
                                <td :colspan="per_store_summary.length > 1 ? 7 : 6" class="px-4 py-10 text-center text-muted-foreground lg:px-6">
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
    /* Panah digambar sebagai background, tanpa ruang ini teks panjang tertutup panah */
    padding-right: 2rem;
}
</style>
