<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Bar } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { TableHeadSortable } from '@/components/ui/table'
import { formatCurrency } from '@/lib/utils'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { BarChart3, ChevronLeft, ChevronRight, Search, Eye, Loader2, Printer } from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

interface Order {
    id: number
    order_code: string
    type: string
    status: string
    payment_method: string
    subtotal: number
    discount_amount: number
    final_amount: number
    item_count: number
    table_name: string | null
    cashier_name: string | null
    customer_name: string | null
    created_at: string
}

interface StoreItem {
    id: number
    name: string
    slug: string
}

interface PaginatedOrders {
    data: Order[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    prev_page_url: string | null
    next_page_url: string | null
}

interface FilterOption {
    value: string
    label: string
}

const props = defineProps<{
    store: StoreItem
    stores: StoreItem[]
    orders: PaginatedOrders
    filters: {
        date_from?: string
        date_to?: string
        type: string
        payment_method: string
        search: string
        sort: string
        dir: string
    }
    payment_method_options: FilterOption[]
    chart_top_products: { product_name: string; total_qty: number; total_amount: number }[]
}>()

const chartTopProductsData = computed(() => ({
    labels: props.chart_top_products.map((p) => p.product_name),
    datasets: [{
        label: 'Jumlah Terjual',
        data: props.chart_top_products.map((p) => p.total_qty),
        backgroundColor: 'rgba(59, 130, 246, 0.7)',
        borderColor: 'rgb(59, 130, 246)',
        borderWidth: 1,
    }],
}))

const chartTopProductsOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top' as const },
        tooltip: {
            callbacks: {
                label: (ctx: { raw: number; dataIndex: number }) => {
                    const p = props.chart_top_products[ctx.dataIndex]
                    return `${ctx.raw} unit · ${formatCurrency(p?.total_amount ?? 0)}`
                },
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
        },
    },
}

const filterState = ref({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    type: props.filters.type ?? 'all',
    payment_method: props.filters.payment_method ?? 'all',
    search: props.filters.search ?? '',
})
watch(() => props.filters, (f) => {
    filterState.value.date_from = f.date_from ?? ''
    filterState.value.date_to = f.date_to ?? ''
    filterState.value.type = f.type ?? 'all'
    filterState.value.payment_method = f.payment_method ?? 'all'
    filterState.value.search = f.search ?? ''
}, { deep: true })

const typeLabels: Record<string, string> = {
    dine_in: 'Dine In',
    takeaway: 'Take Away',
    walk_in: 'Walk In',
}

const typeBadgeClass: Record<string, string> = {
    dine_in: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
    takeaway: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
    walk_in: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800',
}

const paymentBadgeClass: Record<string, string> = {
    cash: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
    qris: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300',
    bank_transfer: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300',
    e_wallet: 'bg-violet-100 text-violet-800 dark:bg-violet-950/50 dark:text-violet-300',
}

const sortKey = computed(() => props.filters.sort ?? 'created_at')
const sortDir = computed(() => (props.filters.dir === 'asc' ? 'asc' : 'desc') as 'asc' | 'desc')

function changeStore(storeId: number) {
    router.get(route('admin.sales.index'), {
        store: storeId,
        ...buildQueryParams(),
    })
}

function buildQueryParams() {
    const p: Record<string, string | undefined> = {
        date_from: filterState.value.date_from || undefined,
        date_to: filterState.value.date_to || undefined,
        type: filterState.value.type !== 'all' ? filterState.value.type : undefined,
        payment_method: filterState.value.payment_method !== 'all' ? filterState.value.payment_method : undefined,
        search: filterState.value.search || undefined,
        sort: props.filters.sort,
        dir: props.filters.dir,
    }
    return Object.fromEntries(Object.entries(p).filter(([, v]) => v != null && v !== ''))
}

function applyFilters() {
    router.get(route('admin.sales.index'), {
        store: props.store.id,
        ...buildQueryParams(),
    }, { preserveState: true })
}

function setSort(key: string) {
    const newDir = sortKey.value === key && sortDir.value === 'desc' ? 'asc' : 'desc'
    router.get(route('admin.sales.index'), {
        store: props.store.id,
        ...buildQueryParams(),
        sort: key,
        dir: newDir,
    }, { preserveState: true })
}

function goToPage(url: string | null) {
    if (url) router.visit(url)
}

interface OrderDetail {
    id: number
    order_code: string
    type: string
    status: string
    payment_status: string
    payment_method: string | null
    subtotal: number
    discount_amount: number
    final_amount: number
    cash_received: number | null
    change_amount: number | null
    notes: string | null
    store_name: string | null
    table_name: string | null
    cashier_name: string | null
    customer_name: string | null
    customer_phone: string | null
    customer_email: string | null
    created_at: string
    is_rental: boolean
    rental_duration_minutes: number | null
    rental_started_at: string | null
    rental_end_at: string | null
    items: { product_name: string; quantity: number; unit: string; unit_price: number; subtotal: number }[]
}

const detailOrder = ref<OrderDetail | null>(null)
const detailLoading = ref(false)

async function viewOrder(id: number) {
    detailLoading.value = true
    detailOrder.value = null
    try {
        const res = await fetch(route('admin.orders.detail', id))
        if (!res.ok) throw new Error('Gagal memuat detail')
        detailOrder.value = await res.json()
    } catch {
        detailOrder.value = null
    } finally {
        detailLoading.value = false
    }
}

function fmtQty(q: number) {
    return q === Math.floor(q) ? String(Math.floor(q)) : String(q)
}

function formatDuration(minutes: number | null): string {
    if (!minutes) return ''
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return h > 0 ? `${h}j ${m}m` : `${m}m`
}

function printReceipt(order: OrderDetail) {
    const sep = '================================================='
    const sepThin = '---------------------------------------------------'
    const storeName = order.store_name ?? props.store.name

    let html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Struk ${order.order_code}</title>`
    html += `<style>
        @page { size: 80mm auto; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 80mm; padding: 5mm 3mm 6mm 3mm; color: #000; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .sep { font-size: 10px; letter-spacing: -0.5px; text-align: center; color: #444; }
        .sep-thin { font-size: 10px; letter-spacing: -0.5px; text-align: center; color: #888; }
        .store-name { font-size: 16px; font-weight: bold; }
        .store-info { font-size: 10px; color: #555; }
        .title { font-size: 11px; font-weight: bold; }
        .row { display: flex; justify-content: space-between; }
        .item-row { display: flex; gap: 4px; margin-bottom: 4px; }
        .item-qty { width: 24px; flex-shrink: 0; }
        .item-name { flex: 1; font-weight: bold; }
        .item-price { flex-shrink: 0; text-align: right; font-weight: bold; min-width: 70px; }
        .total-row { font-size: 14px; font-weight: bold; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .footer { font-size: 12px; font-weight: bold; }
        .footer-sub { font-size: 10px; color: #555; }
        @media print { body { width: 80mm; } }
    </style></head><body>`

    html += `<div class="sep">${sep}</div>`
    html += `<div class="center mt-1 mb-1"><span class="store-name">${storeName}</span></div>`
    html += `<div class="center mb-1"><span class="title">STRUK PEMBAYARAN</span></div>`
    html += `<div class="sep">${sep}</div>`
    html += `<div class="row mt-2 mb-2"><span>No. ${order.order_code}</span><span>${order.created_at}</span></div>`
    html += `<div class="sep-thin">${sepThin}</div>`
    html += `<div class="mt-1">`

    for (const item of order.items) {
        html += `<div class="item-row">`
        html += `<span class="item-qty">${fmtQty(item.quantity)}</span>`
        html += `<span class="item-name">${item.product_name}</span>`
        html += `<span class="item-price">${formatCurrency(item.subtotal)}</span>`
        html += `</div>`
    }

    html += `</div>`
    html += `<div class="sep-thin mt-1">${sepThin}</div>`

    if (order.discount_amount > 0) {
        html += `<div class="row mt-1"><span>Subtotal</span><span>${formatCurrency(order.subtotal)}</span></div>`
        html += `<div class="row"><span>Diskon</span><span>- ${formatCurrency(order.discount_amount)}</span></div>`
        html += `<div class="mt-1"></div>`
    }

    html += `<div class="row mt-1 total-row"><span>TOTAL</span><span>${formatCurrency(order.final_amount)}</span></div>`

    if (order.payment_method) {
        html += `<div class="row mt-2"><span>Metode</span><span>${getPaymentLabel(order.payment_method)}</span></div>`
    }
    if (order.cash_received != null) {
        html += `<div class="row"><span>Tunai</span><span>${formatCurrency(order.cash_received)}</span></div>`
    }
    if (order.change_amount != null && order.change_amount > 0) {
        html += `<div class="row"><span>Kembali</span><span>${formatCurrency(order.change_amount)}</span></div>`
    }

    html += `<div class="sep mt-2">${sep}</div>`
    html += `<div class="center mt-2"><span class="footer">Terima kasih</span></div>`
    html += `<div class="center mt-1"><span class="footer-sub">Selamat menikmati</span></div>`
    html += `<div class="sep mt-2">${sep}</div>`
    html += `</body></html>`

    const w = window.open('', '_blank', 'width=350,height=600')
    if (!w) return
    w.document.write(html)
    w.document.close()
    w.onload = () => {
        w.focus()
        w.print()
    }
}

const hasActiveFilters = computed(() =>
    !!filterState.value.search ||
    filterState.value.type !== 'all' ||
    filterState.value.payment_method !== 'all',
)

function clearFilters() {
    filterState.value.search = ''
    filterState.value.type = 'all'
    filterState.value.payment_method = 'all'
    applyFilters()
}

function getPaymentLabel(code: string) {
    const labels: Record<string, string> = {
        cash: 'Tunai',
        qris: 'QRIS',
        bank_transfer: 'Transfer Bank',
        e_wallet: 'E-Wallet',
    }
    return labels[code] ?? code?.replace(/_/g, ' ') ?? '—'
}
</script>

<template>
    <AdminLayout :title="`Riwayat Penjualan - ${store.name}`">
        <div class="space-y-6">
            <!-- Filters -->
            <Card>
                <CardHeader class="pb-4">
                    <CardTitle class="flex items-center gap-2">
                        <BarChart3 class="h-5 w-5" />
                        Riwayat Penjualan
                    </CardTitle>
                    <CardDescription>
                        Daftar transaksi penjualan yang sudah dibayar. Cari dan filter sesuai kebutuhan.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div v-if="stores.length > 1" class="flex flex-col gap-1.5">
                            <Label class="text-xs font-medium text-muted-foreground">Toko</Label>
                            <select
                                :value="store.id"
                                class="filter-select h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                @change="changeStore(Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5" :class="stores.length > 1 ? 'sm:col-start-2' : 'sm:col-span-2'">
                            <Label class="text-xs font-medium text-muted-foreground">Periode</Label>
                            <div class="flex items-center gap-2">
                                <Input v-model="filterState.date_from" type="date" class="h-9 flex-1 min-w-0" />
                                <span class="shrink-0 text-muted-foreground">s/d</span>
                                <Input v-model="filterState.date_to" type="date" class="h-9 flex-1 min-w-0" />
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="flex flex-1 min-w-[180px] flex-col gap-1.5">
                            <Label class="text-xs font-medium text-muted-foreground">Cari</Label>
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="filterState.search"
                                    placeholder="Kode, kasir, meja..."
                                    class="h-9 w-full pl-9"
                                    @keydown.enter="applyFilters"
                                />
                            </div>
                        </div>
                        <div class="flex flex-1 min-w-[120px] flex-col gap-1.5">
                            <Label class="text-xs font-medium text-muted-foreground">Tipe</Label>
                            <select
                                v-model="filterState.type"
                                class="filter-select h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="all">Semua</option>
                                <option value="dine_in">Dine In</option>
                                <option value="takeaway">Take Away</option>
                                <option value="walk_in">Walk In</option>
                            </select>
                        </div>
                        <div class="flex flex-1 min-w-[140px] flex-col gap-1.5">
                            <Label class="text-xs font-medium text-muted-foreground">Pembayaran</Label>
                            <select
                                v-model="filterState.payment_method"
                                class="filter-select h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option v-for="opt in payment_method_options" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex shrink-0 items-end gap-2">
                            <Button size="sm" @click="applyFilters">
                                <Search class="mr-2 h-4 w-4" />
                                Filter
                            </Button>
                            <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="clearFilters">
                                Reset
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Penjualan Terpopuler -->
            <Card>
                <CardHeader>
                    <CardTitle>Penjualan Terpopuler</CardTitle>
                    <CardDescription>
                        Produk dengan penjualan terbanyak dalam periode yang dipilih
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[320px]">
                        <Bar
                            v-if="chart_top_products.length > 0"
                            :data="chartTopProductsData"
                            :options="chartTopProductsOptions"
                        />
                        <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                            Tidak ada data penjualan
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Orders Table -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Daftar Transaksi</CardTitle>
                    <CardDescription v-if="orders.total > 0">
                        {{ orders.total }} transaksi
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-0 pt-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm [&_td]:align-middle">
                            <thead>
                                <tr class="border-b bg-muted/40 text-left">
                                    <TableHeadSortable
                                        label="Kode"
                                        sort-key="order_code"
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
                                    <TableHeadSortable
                                        label="Tipe"
                                        sort-key="type"
                                        :current-sort-key="sortKey"
                                        :sort-dir="sortDir"
                                        class-names="min-w-[88px]"
                                        @sort="setSort"
                                    />
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Meja</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Pelanggan</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Kasir</th>
                                    <TableHeadSortable
                                        label="Item"
                                        sort-key="item_count"
                                        :current-sort-key="sortKey"
                                        :sort-dir="sortDir"
                                        align="center"
                                        @sort="setSort"
                                    />
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Bayar</th>
                                    <TableHeadSortable
                                        label="Total"
                                        sort-key="final_amount"
                                        :current-sort-key="sortKey"
                                        :sort-dir="sortDir"
                                        align="right"
                                        @sort="setSort"
                                    />
                                    <th class="w-20 px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody :key="`page-${orders.current_page}`">
                                <tr
                                    v-for="order in (orders.data ?? [])"
                                    :key="order.id"
                                    class="border-b transition-colors last:border-0 hover:bg-muted/20"
                                >
                                    <td class="px-4 py-3">
                                        <span class="font-mono text-sm font-medium">{{ order.order_code }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-muted-foreground">{{ order.created_at }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            class="inline-flex shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
                                            :class="typeBadgeClass[order.type] ?? 'bg-muted text-muted-foreground'"
                                        >
                                            {{ typeLabels[order.type] ?? order.type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ order.table_name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ order.customer_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ order.cashier_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-center font-medium">{{ order.item_count }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                            :class="paymentBadgeClass[order.payment_method] ?? 'bg-muted text-muted-foreground'"
                                        >
                                            {{ getPaymentLabel(order.payment_method) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ formatCurrency(order.final_amount) }}</td>
                                    <td class="px-4 py-3">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-8 w-8 shrink-0 p-0"
                                            title="Lihat detail"
                                            @click="viewOrder(order.id)"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!(orders.data ?? []).length">
                                    <td colspan="10" class="px-4 py-12 text-center text-muted-foreground">
                                        Belum ada transaksi penjualan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="orders.total > 0"
                        class="flex items-center justify-between border-t px-4 py-3"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ orders.from }}–{{ orders.to }} dari {{ orders.total }} transaksi
                        </p>
                        <div v-if="orders.last_page > 1" class="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!orders.prev_page_url"
                                @click="goToPage(orders.prev_page_url)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!orders.next_page_url"
                                @click="goToPage(orders.next_page_url)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modal Detail Pesanan -->
        <Dialog :open="detailLoading || !!detailOrder" @update:open="(v) => { if (!v) { detailOrder = null; detailLoading = false } }">
            <DialogContent class="max-w-lg max-h-[85vh] overflow-y-auto">
                <div v-if="detailLoading" class="flex items-center justify-center py-12">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                </div>
                <template v-else-if="detailOrder">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            {{ detailOrder.order_code }}
                            <span
                                class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="typeBadgeClass[detailOrder.type] ?? 'bg-muted text-muted-foreground'"
                            >
                                {{ typeLabels[detailOrder.type] ?? detailOrder.type }}
                            </span>
                        </DialogTitle>
                        <DialogDescription>{{ detailOrder.created_at }}</DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-2">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-muted-foreground">Kasir</p>
                                <p class="font-medium">{{ detailOrder.cashier_name ?? '—' }}</p>
                            </div>
                            <div v-if="detailOrder.table_name">
                                <p class="text-muted-foreground">Meja</p>
                                <p class="font-medium">{{ detailOrder.table_name }}</p>
                            </div>
                            <div v-if="detailOrder.customer_name">
                                <p class="text-muted-foreground">Pelanggan</p>
                                <p class="font-medium">{{ detailOrder.customer_name }}</p>
                                <p v-if="detailOrder.customer_phone || detailOrder.customer_email" class="text-xs text-muted-foreground">
                                    {{ [detailOrder.customer_phone, detailOrder.customer_email].filter(Boolean).join(' · ') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Pembayaran</p>
                                <p class="font-medium capitalize">{{ getPaymentLabel(detailOrder.payment_status === 'paid' ? 'paid' : 'unpaid') }}</p>
                            </div>
                        </div>

                        <!-- Info Rental PS -->
                        <div v-if="detailOrder.is_rental" class="flex flex-wrap gap-x-5 gap-y-1 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-200">
                            <span class="font-semibold">Rental PS{{ detailOrder.table_name ? ' — ' + detailOrder.table_name : '' }}</span>
                            <span v-if="detailOrder.rental_duration_minutes">Durasi: <strong>{{ formatDuration(detailOrder.rental_duration_minutes) }}</strong></span>
                            <span v-if="detailOrder.rental_started_at">Mulai: <strong>{{ detailOrder.rental_started_at }}</strong></span>
                            <span v-if="detailOrder.rental_end_at">Selesai: <strong>{{ detailOrder.rental_end_at }}</strong></span>
                        </div>

                        <div class="rounded-lg border">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/50 text-left">
                                        <th class="px-3 py-2 font-medium">Produk</th>
                                        <th class="px-3 py-2 font-medium text-center">Qty</th>
                                        <th class="px-3 py-2 font-medium text-right">Harga</th>
                                        <th class="px-3 py-2 font-medium text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Baris biaya rental jika ada -->
                                    <tr v-if="detailOrder.is_rental && detailOrder.subtotal - detailOrder.items.reduce((s, i) => s + i.subtotal, 0) > 0" class="border-b bg-blue-50/50">
                                        <td class="px-3 py-2 font-medium text-blue-700">
                                            Biaya Rental{{ detailOrder.table_name ? ' — ' + detailOrder.table_name : '' }}
                                            <span v-if="detailOrder.rental_duration_minutes" class="ml-1 text-xs font-normal text-blue-500">({{ formatDuration(detailOrder.rental_duration_minutes) }})</span>
                                        </td>
                                        <td class="px-3 py-2 text-center text-muted-foreground">—</td>
                                        <td class="px-3 py-2 text-right text-muted-foreground">—</td>
                                        <td class="px-3 py-2 text-right font-medium text-blue-700">{{ formatCurrency(detailOrder.subtotal - detailOrder.items.reduce((s, i) => s + i.subtotal, 0)) }}</td>
                                    </tr>
                                    <tr
                                        v-for="(item, i) in detailOrder.items"
                                        :key="i"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-3 py-2">{{ item.product_name }}</td>
                                        <td class="px-3 py-2 text-center">{{ item.quantity }} {{ item.unit }}</td>
                                        <td class="px-3 py-2 text-right text-muted-foreground">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="px-3 py-2 text-right font-medium">{{ formatCurrency(item.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-lg border p-3 space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Subtotal</span>
                                <span>{{ formatCurrency(detailOrder.subtotal) }}</span>
                            </div>
                            <div v-if="detailOrder.discount_amount > 0" class="flex justify-between">
                                <span class="text-muted-foreground">Diskon</span>
                                <span>-{{ formatCurrency(detailOrder.discount_amount) }}</span>
                            </div>
                            <div class="flex justify-between border-t pt-1.5 font-semibold">
                                <span>Total</span>
                                <span>{{ formatCurrency(detailOrder.final_amount) }}</span>
                            </div>
                        </div>

                        <p v-if="detailOrder.notes" class="text-sm text-muted-foreground">
                            <span class="font-medium">Catatan:</span> {{ detailOrder.notes }}
                        </p>

                        <div class="flex justify-end border-t pt-4">
                            <Button @click="printReceipt(detailOrder!)">
                                <Printer class="mr-2 h-4 w-4" />
                                Cetak Struk
                            </Button>
                        </div>
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
