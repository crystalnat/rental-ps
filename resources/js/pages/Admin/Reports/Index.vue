<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
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
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatCurrency } from '@/lib/utils'
import {
    BarChart3,
    TrendingUp,
    TrendingDown,
    Wallet,
    Package,
    Calendar,
    Search,
    Store,
    ChevronDown,
} from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler)

interface StoreItem {
    id: number
    name: string
    slug: string
}

interface TopProduct {
    product_name: string
    total_qty: number
    total_amount: number
}

interface SalesByCashier {
    cashier_id: number | null
    cashier_name: string
    order_count: number
    total_amount: number
}

interface SalesByType {
    type: string
    label: string
    count: number
    total: number
}

interface SalesByPayment {
    method: string
    label: string
    count: number
    total: number
}

interface ExpenseCategoryDetail {
    category: string
    category_label: string
    total: number
    count: number
}

interface StoreReport {
    id: number
    name: string
    slug: string
    income: number
    expenses: number
    order_count: number
    gross_profit: number
    chart_sales: { labels: string[]; data: number[] }
    chart_expense_by_category: { labels: string[]; amounts: number[] }
    top_products: TopProduct[]
    sales_by_cashier: SalesByCashier[]
    sales_by_type: SalesByType[]
    sales_by_payment: SalesByPayment[]
}

interface OverallReport {
    income: number
    expenses: number
    net: number
    gross_profit: number
    order_count: number
    chart_sales: { labels: string[]; data: number[] }
    chart_expense_by_category: { labels: string[]; amounts: number[] }
    chart_sales_by_store: { labels: string[]; data: number[] }
    chart_expense_by_store: { labels: string[]; data: number[] }
    top_products: TopProduct[]
    sales_by_cashier: SalesByCashier[]
    sales_by_type: SalesByType[]
    sales_by_payment: SalesByPayment[]
    expense_by_category_detail: ExpenseCategoryDetail[]
}

const props = defineProps<{
    stores: StoreItem[]
    date_from: string
    date_to: string
    overall: OverallReport
    per_store: StoreReport[]
}>()

const filterState = ref({
    date_from: props.date_from ?? '',
    date_to: props.date_to ?? '',
})

const expandedStores = ref<Record<number, boolean>>(
    props.per_store?.length === 1 ? { [props.per_store[0].id]: true } : {},
)
function isStoreExpanded(id: number) {
    return expandedStores.value[id] ?? false
}

watch(() => [props.date_from, props.date_to], ([from, to]) => {
    filterState.value.date_from = (from as string) ?? ''
    filterState.value.date_to = (to as string) ?? ''
})

function makeChartSalesConfig(labels: string[], data: number[]) {
    return {
        labels,
        datasets: [{
            label: 'Penjualan',
            data,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: 'rgb(16, 185, 129)',
            borderWidth: 1,
        }],
    }
}

const chartSalesOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
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

function makeChartExpenseConfig(labels: string[], amounts: number[]) {
    const colors = [
        'rgba(239, 68, 68, 0.8)', 'rgba(249, 115, 22, 0.8)', 'rgba(234, 179, 8, 0.8)',
        'rgba(34, 197, 94, 0.8)', 'rgba(59, 130, 246, 0.8)', 'rgba(139, 92, 246, 0.8)',
    ]
    return {
        labels,
        datasets: [{
            data: amounts,
            backgroundColor: colors.slice(0, labels.length),
            borderWidth: 0,
        }],
    }
}

const chartExpenseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'right' as const },
        tooltip: {
            callbacks: {
                label: (ctx: { raw: number; label: string }) =>
                    `${ctx.label}: ${formatCurrency(ctx.raw)}`,
            },
        },
    },
}

function makeChartByStoreConfig(labels: string[], data: number[], color: string) {
    return {
        labels,
        datasets: [{
            label: 'Total',
            data,
            backgroundColor: color,
            borderWidth: 1,
        }],
    }
}

function applyFilters() {
    router.get('/admin/reports', {
        date_from: filterState.value.date_from || undefined,
        date_to: filterState.value.date_to || undefined,
    }, { preserveState: true })
}

function viewCashier(cashierId: number | null) {
    if (cashierId) router.visit(route('admin.users.show', cashierId))
}

function toggleStore(storeId: number) {
    expandedStores.value = { ...expandedStores.value, [storeId]: !expandedStores.value[storeId] }
}

const hasData = computed(() =>
    props.overall.order_count > 0 ||
    props.overall.expenses > 0 ||
    props.per_store.some(s => s.order_count > 0 || s.expenses > 0),
)
</script>

<template>
    <AdminLayout title="Laporan">
        <div class="space-y-8">
            <!-- Filters -->
            <Card variant="elevated" class="overflow-hidden border-none shadow-sm md:shadow-md">
                <CardHeader class="p-4 md:p-6">
                    <CardTitle class="flex items-center gap-2 text-lg md:text-xl font-bold">
                        <BarChart3 class="h-5 w-5 text-primary" />
                        Laporan Analitas
                    </CardTitle>
                    <CardDescription class="text-xs md:text-sm">
                        Pantau performa penjualan, pengeluaran, dan laba rugi seluruh cabang toko Anda.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-4 md:p-6 pt-0">
                    <div class="flex flex-col md:flex-row md:items-end gap-3 md:gap-4">
                        <div class="flex-1 space-y-1.5">
                            <Label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Periode Laporan</Label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1 group">
                                    <Calendar class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground group-focus-within:text-primary transition-colors" />
                                    <Input v-model="filterState.date_from" type="date" class="h-10 pl-9 font-medium" />
                                </div>
                                <span class="text-muted-foreground font-black">/</span>
                                <div class="relative flex-1 group">
                                    <Calendar class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground group-focus-within:text-primary transition-colors" />
                                    <Input v-model="filterState.date_to" type="date" class="h-10 pl-9 font-medium" />
                                </div>
                            </div>
                        </div>
                        <Button class="h-10 w-full md:w-auto font-bold px-6" @click="applyFilters">
                            <Search class="mr-2 h-4 w-4" />
                            Tampilkan Data
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <template v-if="!hasData">
                <Card variant="elevated">
                    <CardContent class="flex flex-col items-center justify-center py-16 text-center">
                        <BarChart3 class="mb-3 h-12 w-12 text-muted-foreground/50" />
                        <p class="text-muted-foreground">Tidak ada data dalam periode ini</p>
                    </CardContent>
                </Card>
            </template>

            <template v-else>
                <!-- ========== RINGKASAN KESELURUHAN ========== -->
                <section class="space-y-4 md:space-y-6">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h2 class="text-lg md:text-xl font-black tracking-tight text-primary">RINGKASAN KESELURUHAN</h2>
                        <Badge variant="outline" class="hidden sm:flex font-bold uppercase tracking-widest text-[10px]">Total Cabang: {{ per_store.length }}</Badge>
                    </div>

                    <!-- Overall Summary Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard variant="success" class="border-none shadow-sm">
                            <template #title>Penjualan</template>
                            <template #value>
                                <p class="text-2xl md:text-3xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ formatCurrency(overall.income) }}</p>
                            </template>
                            <template #subtitle>{{ overall.order_count }} Transaksi</template>
                            <template #icon><TrendingUp class="h-6 w-6" /></template>
                        </StatCard>
                        <StatCard variant="destructive" class="border-none shadow-sm">
                            <template #title>Pengeluaran</template>
                            <template #value>
                                <p class="text-2xl md:text-3xl font-black text-destructive tabular-nums">{{ formatCurrency(overall.expenses) }}</p>
                            </template>
                            <template #subtitle>Operasional & Inventaris</template>
                            <template #icon><TrendingDown class="h-6 w-6" /></template>
                        </StatCard>
                        <StatCard variant="primary" class="border-none shadow-sm">
                            <template #title>Laba Kotor</template>
                            <template #value>
                                <p class="text-2xl md:text-3xl font-black text-primary tabular-nums">{{ formatCurrency(overall.gross_profit) }}</p>
                            </template>
                            <template #subtitle>Sebelum Pengeluaran</template>
                            <template #icon><Package class="h-6 w-6" /></template>
                        </StatCard>
                        <StatCard :variant="overall.net >= 0 ? 'success' : 'destructive'" class="border-none shadow-sm ring-2" :class="overall.net >= 0 ? 'ring-emerald-500/20' : 'ring-destructive/20'">
                            <template #title>Laba Bersih</template>
                            <template #value>
                                <p class="text-2xl md:text-3xl font-black tabular-nums" :class="overall.net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'">
                                    {{ formatCurrency(overall.net) }}
                                </p>
                            </template>
                            <template #subtitle>Profit Akhir Periode</template>
                            <template #icon><Wallet class="h-6 w-6" /></template>
                        </StatCard>
                    </div>di                    <!-- Overall Charts -->
                    <div class="grid gap-4 md:gap-6 lg:grid-cols-2">
                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-2">
                                <CardTitle class="text-base md:text-lg font-bold">Penjualan Harian</CardTitle>
                                <CardDescription class="text-xs">Statistik gabungan seluruh cabang</CardDescription>
                            </CardHeader>
                            <CardContent class="p-4 md:p-6 pt-0">
                                <div class="h-[250px] md:h-[300px] w-full">
                                    <Bar
                                        v-if="overall.chart_sales.labels.length > 0"
                                        :data="makeChartSalesConfig(overall.chart_sales.labels, overall.chart_sales.data)"
                                        :options="chartSalesOptions"
                                    />
                                    <div v-else class="flex h-full items-center justify-center text-muted-foreground italic">Tidak ada data penjualan</div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-2">
                                <CardTitle class="text-base md:text-lg font-bold">Pengeluaran per Kategori</CardTitle>
                                <CardDescription class="text-xs">Distribusi biaya operasional</CardDescription>
                            </CardHeader>
                            <CardContent class="p-4 md:p-6 pt-0">
                                <div class="h-[250px] md:h-[300px] w-full">
                                    <Doughnut
                                        v-if="overall.chart_expense_by_category.labels.length > 0"
                                        :data="makeChartExpenseConfig(overall.chart_expense_by_category.labels, overall.chart_expense_by_category.amounts)"
                                        :options="{
                                            ...chartExpenseOptions,
                                            plugins: {
                                                ...chartExpenseOptions.plugins,
                                                legend: {
                                                    position: 'bottom',
                                                    labels: { boxWidth: 10, padding: 10, font: { size: 10 } }
                                                }
                                            }
                                        }"
                                    />
                                    <div v-else class="flex h-full items-center justify-center text-muted-foreground italic">Tidak ada data pengeluaran</div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-2">
                                <CardTitle class="text-base md:text-lg font-bold">Penjualan per Toko</CardTitle>
                                <CardDescription class="text-xs">Peringkat performa tiap cabang</CardDescription>
                            </CardHeader>
                            <CardContent class="p-4 md:p-6 pt-0">
                                <div class="h-[250px] md:h-[300px] w-full">
                                    <Bar
                                        v-if="overall.chart_sales_by_store.labels.length > 0"
                                        :data="makeChartByStoreConfig(overall.chart_sales_by_store.labels, overall.chart_sales_by_store.data, 'rgba(16, 185, 129, 0.7)')"
                                        :options="chartSalesOptions"
                                    />
                                    <div v-else class="flex h-full items-center justify-center text-muted-foreground italic">Tidak ada data per toko</div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-2">
                                <CardTitle class="text-base md:text-lg font-bold">Pengeluaran per Toko</CardTitle>
                                <CardDescription class="text-xs">Biaya operasional tiap cabang</CardDescription>
                            </CardHeader>
                            <CardContent class="p-4 md:p-6 pt-0">
                                <div class="h-[250px] md:h-[300px] w-full">
                                    <Bar
                                        v-if="overall.chart_expense_by_store.labels.length > 0"
                                        :data="makeChartByStoreConfig(overall.chart_expense_by_store.labels, overall.chart_expense_by_store.data, 'rgba(239, 68, 68, 0.7)')"
                                        :options="chartSalesOptions"
                                    />
                                    <div v-else class="flex h-full items-center justify-center text-muted-foreground italic">Tidak ada data per toko</div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>div>

                    <!-- Overall Tables -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-4">
                                <CardTitle class="text-base md:text-lg font-black">Produk Terlaris</CardTitle>
                                <CardDescription class="text-xs">Top 20 produk paling laku</CardDescription>
                            </CardHeader>
                            <CardContent class="p-0">
                                <div class="max-h-[400px] overflow-y-auto">
                                    <table class="w-full text-xs md:text-sm">
                                        <thead class="sticky top-0 bg-muted/90 backdrop-blur-sm shadow-sm z-10">
                                            <tr class="text-left text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                                <th class="px-4 py-3">Nama Produk</th>
                                                <th class="px-4 py-3 text-center">Qty</th>
                                                <th class="px-4 py-3 text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr v-for="(p, i) in overall.top_products" :key="i" class="hover:bg-primary/5 transition-colors">
                                                <td class="px-4 py-3 font-bold">{{ p.product_name }}</td>
                                                <td class="px-4 py-3 text-center font-medium tabular-nums">{{ p.total_qty }}</td>
                                                <td class="px-4 py-3 text-right font-black text-primary tabular-nums">{{ formatCurrency(p.total_amount) }}</td>
                                            </tr>
                                            <tr v-if="overall.top_products.length === 0">
                                                <td colspan="3" class="px-4 py-12 text-center text-muted-foreground italic">Belum ada data penjualan</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-4">
                                <CardTitle class="text-base md:text-lg font-black">Penjualan per Kasir</CardTitle>
                                <CardDescription class="text-xs">Statistik performa kasir</CardDescription>
                            </CardHeader>
                            <CardContent class="p-0">
                                <div class="max-h-[400px] overflow-y-auto">
                                    <table class="w-full text-xs md:text-sm">
                                        <thead class="sticky top-0 bg-muted/90 backdrop-blur-sm shadow-sm z-10">
                                            <tr class="text-left text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                                <th class="px-4 py-3">Nama Kasir</th>
                                                <th class="px-4 py-3 text-center">Tx</th>
                                                <th class="px-4 py-3 text-right">Total</th>
                                                <th class="w-12"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr v-for="(c, i) in overall.sales_by_cashier" :key="i" class="hover:bg-primary/5 transition-colors">
                                                <td class="px-4 py-3 font-bold">{{ c.cashier_name }}</td>
                                                <td class="px-4 py-3 text-center font-medium tabular-nums">{{ c.order_count }}</td>
                                                <td class="px-4 py-3 text-right font-black text-primary tabular-nums">{{ formatCurrency(c.total_amount) }}</td>
                                                <td class="px-4 py-3 text-center">
                                                    <Button v-if="c.cashier_id" variant="ghost" size="icon" class="h-8 w-8 rounded-full" @click="viewCashier(c.cashier_id)">
                                                        <Search class="h-3.5 w-3.5" />
                                                    </Button>
                                                </td>
                                            </tr>
                                            <tr v-if="overall.sales_by_cashier.length === 0">
                                                <td colspan="4" class="px-4 py-12 text-center text-muted-foreground italic">Belum ada data kasir</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card variant="elevated">
                            <CardHeader>
                                <CardTitle>Penjualan per Tipe Order</CardTitle>
                                <CardDescription>Dine In, Take Away, Walk In</CardDescription>
                            </CardHeader>
                            <CardContent class="p-0">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b bg-muted/50 text-left">
                                            <th class="px-4 py-3 font-medium text-muted-foreground">Tipe</th>
                                            <th class="px-4 py-3 font-medium text-muted-foreground text-center">Transaksi</th>
                                            <th class="px-4 py-3 font-medium text-muted-foreground text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(t, i) in overall.sales_by_type" :key="i" class="border-b hover:bg-muted/30">
                                            <td class="px-4 py-3 font-medium">{{ t.label }}</td>
                                            <td class="px-4 py-3 text-center">{{ t.count }}</td>
                                            <td class="px-4 py-3 text-right">{{ formatCurrency(t.total) }}</td>
                                        </tr>
                                        <tr v-if="overall.sales_by_type.length === 0">
                                            <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                        <Card variant="elevated">
                            <CardHeader>
                                <CardTitle>Penjualan per Metode Pembayaran</CardTitle>
                                <CardDescription>Tunai, QRIS, Transfer, E-Wallet</CardDescription>
                            </CardHeader>
                            <CardContent class="p-0">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b bg-muted/50 text-left">
                                            <th class="px-4 py-3 font-medium text-muted-foreground">Metode</th>
                                            <th class="px-4 py-3 font-medium text-muted-foreground text-center">Transaksi</th>
                                            <th class="px-4 py-3 font-medium text-muted-foreground text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(p, i) in overall.sales_by_payment" :key="i" class="border-b hover:bg-muted/30">
                                            <td class="px-4 py-3 font-medium">{{ p.label }}</td>
                                            <td class="px-4 py-3 text-center">{{ p.count }}</td>
                                            <td class="px-4 py-3 text-right">{{ formatCurrency(p.total) }}</td>
                                        </tr>
                                        <tr v-if="overall.sales_by_payment.length === 0">
                                            <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </div>

                    <Card variant="elevated">
                        <CardHeader>
                            <CardTitle>Detail Pengeluaran per Kategori</CardTitle>
                            <CardDescription>Jumlah transaksi dan total per kategori</CardDescription>
                        </CardHeader>
                        <CardContent class="p-0">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/50 text-left">
                                        <th class="px-4 py-3 font-medium text-muted-foreground">Kategori</th>
                                        <th class="px-4 py-3 font-medium text-muted-foreground text-center">Jumlah Transaksi</th>
                                        <th class="px-4 py-3 font-medium text-muted-foreground text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(e, i) in overall.expense_by_category_detail" :key="i" class="border-b hover:bg-muted/30">
                                        <td class="px-4 py-3 font-medium">{{ e.category_label }}</td>
                                        <td class="px-4 py-3 text-center">{{ e.count }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatCurrency(e.total) }}</td>
                                    </tr>
                                    <tr v-if="overall.expense_by_category_detail.length === 0">
                                        <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data</td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </section>

                <!-- ========== PER TOKO ========== -->
                <section v-if="per_store.length > 0" class="space-y-6">
                    <h2 class="text-lg font-semibold">Laporan per Toko</h2>

                    <Card v-for="store in per_store" :key="store.id" variant="elevated">
                        <button
                            class="flex w-full items-center justify-between p-4 text-left hover:bg-muted/30 transition-colors rounded-t-lg"
                            @click="toggleStore(store.id)"
                        >
                            <div class="flex items-center gap-3">
                                <Store class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="font-semibold">{{ store.name }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        Penjualan {{ formatCurrency(store.income) }} · Pengeluaran {{ formatCurrency(store.expenses) }} · {{ store.order_count }} transaksi
                                    </p>
                                </div>
                            </div>
                            <ChevronDown
                                class="h-5 w-5 text-muted-foreground transition-transform"
                                :class="{ 'rotate-180': isStoreExpanded(store.id) }"
                            />
                        </button>
                        <div v-show="isStoreExpanded(store.id)" class="border-t">
                                <div class="border-t px-4 pb-4 pt-2 space-y-6">
                                    <!-- Per-store summary -->
                                    <div class="grid gap-4 sm:grid-cols-4">
                                        <div class="rounded-lg border p-3">
                                            <p class="text-xs text-muted-foreground">Penjualan</p>
                                            <p class="text-lg font-bold text-emerald-600">{{ formatCurrency(store.income) }}</p>
                                        </div>
                                        <div class="rounded-lg border p-3">
                                            <p class="text-xs text-muted-foreground">Pengeluaran</p>
                                            <p class="text-lg font-bold text-destructive">{{ formatCurrency(store.expenses) }}</p>
                                        </div>
                                        <div class="rounded-lg border p-3">
                                            <p class="text-xs text-muted-foreground">Laba Kotor</p>
                                            <p class="text-lg font-bold text-primary">{{ formatCurrency(store.gross_profit) }}</p>
                                        </div>
                                        <div class="rounded-lg border p-3">
                                            <p class="text-xs text-muted-foreground">Transaksi</p>
                                            <p class="text-lg font-bold">{{ store.order_count }}</p>
                                        </div>
                                    </div>

                                    <!-- Per-store charts -->
                                    <div class="grid gap-6 lg:grid-cols-2">
                                        <div>
                                            <p class="mb-2 text-sm font-medium">Penjualan Harian</p>
                                            <div class="h-[220px]">
                                                <Bar
                                                    v-if="store.chart_sales.labels.length > 0"
                                                    :data="makeChartSalesConfig(store.chart_sales.labels, store.chart_sales.data)"
                                                    :options="chartSalesOptions"
                                                />
                                                <div v-else class="flex h-full items-center justify-center text-muted-foreground text-sm">Tidak ada data</div>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="mb-2 text-sm font-medium">Pengeluaran per Kategori</p>
                                            <div class="h-[220px]">
                                                <Doughnut
                                                    v-if="store.chart_expense_by_category.labels.length > 0"
                                                    :data="makeChartExpenseConfig(store.chart_expense_by_category.labels, store.chart_expense_by_category.amounts)"
                                                    :options="chartExpenseOptions"
                                                />
                                                <div v-else class="flex h-full items-center justify-center text-muted-foreground text-sm">Tidak ada data</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Per-store tables -->
                                    <div class="grid gap-6 lg:grid-cols-2">
                                        <div>
                                            <p class="mb-2 text-sm font-medium">Produk Terlaris</p>
                                            <div class="max-h-[280px] overflow-y-auto rounded-md border">
                                                <table class="w-full text-sm">
                                                    <thead class="sticky top-0 bg-muted/50">
                                                        <tr class="border-b text-left">
                                                            <th class="px-3 py-2 font-medium text-muted-foreground">Produk</th>
                                                            <th class="px-3 py-2 font-medium text-muted-foreground text-center">Qty</th>
                                                            <th class="px-3 py-2 font-medium text-muted-foreground text-right">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="(p, i) in store.top_products" :key="i" class="border-b hover:bg-muted/30">
                                                            <td class="px-3 py-2">{{ p.product_name }}</td>
                                                            <td class="px-3 py-2 text-center">{{ p.total_qty }}</td>
                                                            <td class="px-3 py-2 text-right">{{ formatCurrency(p.total_amount) }}</td>
                                                        </tr>
                                                        <tr v-if="store.top_products.length === 0">
                                                            <td colspan="3" class="px-3 py-6 text-center text-muted-foreground text-xs">Tidak ada data</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="mb-2 text-sm font-medium">Penjualan per Kasir</p>
                                            <div class="max-h-[280px] overflow-y-auto rounded-md border">
                                                <table class="w-full text-sm">
                                                    <thead class="sticky top-0 bg-muted/50">
                                                        <tr class="border-b text-left">
                                                            <th class="px-3 py-2 font-medium text-muted-foreground">Kasir</th>
                                                            <th class="px-3 py-2 font-medium text-muted-foreground text-center">Tx</th>
                                                            <th class="px-3 py-2 font-medium text-muted-foreground text-right">Total</th>
                                                            <th class="w-12"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="(c, i) in store.sales_by_cashier" :key="i" class="border-b hover:bg-muted/30">
                                                            <td class="px-3 py-2 font-medium">{{ c.cashier_name }}</td>
                                                            <td class="px-3 py-2 text-center">{{ c.order_count }}</td>
                                                            <td class="px-3 py-2 text-right">{{ formatCurrency(c.total_amount) }}</td>
                                                            <td class="px-3 py-2">
                                                                <Button v-if="c.cashier_id" variant="ghost" size="sm" class="h-6 text-xs" @click="viewCashier(c.cashier_id)">Detail</Button>
                                                            </td>
                                                        </tr>
                                                        <tr v-if="store.sales_by_cashier.length === 0">
                                                            <td colspan="4" class="px-3 py-6 text-center text-muted-foreground text-xs">Tidak ada data</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-6 sm:grid-cols-2">
                                        <div>
                                            <p class="mb-2 text-sm font-medium">Per Tipe Order</p>
                                            <table class="w-full text-sm rounded-md border">
                                                <tbody>
                                                    <tr v-for="(t, i) in store.sales_by_type" :key="i" class="border-b last:border-0">
                                                        <td class="px-3 py-2">{{ t.label }}</td>
                                                        <td class="px-3 py-2 text-center">{{ t.count }}</td>
                                                        <td class="px-3 py-2 text-right">{{ formatCurrency(t.total) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div>
                                            <p class="mb-2 text-sm font-medium">Per Metode Pembayaran</p>
                                            <table class="w-full text-sm rounded-md border">
                                                <tbody>
                                                    <tr v-for="(p, i) in store.sales_by_payment" :key="i" class="border-b last:border-0">
                                                        <td class="px-3 py-2">{{ p.label }}</td>
                                                        <td class="px-3 py-2 text-center">{{ p.count }}</td>
                                                        <td class="px-3 py-2 text-right">{{ formatCurrency(p.total) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </Card>
                </section>
            </template>
        </div>
    </AdminLayout>
</template>
