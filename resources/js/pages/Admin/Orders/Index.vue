<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { TableHeadSortable } from '@/components/ui/table'
import { formatCurrency } from '@/lib/utils'
import {
    Receipt, ChevronLeft, ChevronRight, Search, Eye, Check,
    Trash2, Loader2,
} from 'lucide-vue-next'

interface Order {
    id: number
    order_code: string
    type: string
    status: string
    payment_status: string
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
        status: string
        payment_status: string
        payment_method: string
        search: string
        sort: string
        dir: string
    }
    payment_method_options: FilterOption[]
    status_options: FilterOption[]
    payment_status_options: FilterOption[]
    payment_methods?: { id: number; name: string; code: string; requires_cash_input: boolean }[]
}>()

const filterState = ref({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    type: props.filters.type ?? 'all',
    status: props.filters.status ?? 'all',
    payment_status: props.filters.payment_status ?? 'all',
    payment_method: props.filters.payment_method ?? 'all',
    search: props.filters.search ?? '',
})
watch(() => props.filters, (f) => {
    filterState.value.date_from = f.date_from ?? ''
    filterState.value.date_to = f.date_to ?? ''
    filterState.value.type = f.type ?? 'all'
    filterState.value.status = f.status ?? 'all'
    filterState.value.payment_status = f.payment_status ?? 'all'
    filterState.value.payment_method = f.payment_method ?? 'all'
    filterState.value.search = f.search ?? ''
}, { deep: true })

const typeLabels: Record<string, string> = {
    dine_in: 'Makan di Tempat',
    takeaway: 'Bungkus',
    walk_in: 'Pesan Langsung',
}

const typeBadgeClass: Record<string, string> = {
    dine_in: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
    takeaway: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
    walk_in: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800',
}

const statusBadgeClass: Record<string, string> = {
    pending: 'bg-muted text-muted-foreground border-muted-foreground/20',
    confirmed: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800',
    processing: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
    ready: 'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-950/50 dark:text-indigo-300 dark:border-indigo-800',
    done: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
    cancelled: 'bg-red-100 text-red-800 border-red-200 dark:bg-red-950/50 dark:text-red-300 dark:border-red-800',
}

const paymentLabels: Record<string, string> = {
    cash: 'Tunai',
    qris: 'QRIS',
    bank_transfer: 'Transfer Bank',
    e_wallet: 'E-Wallet',
}

const statusLabels: Record<string, string> = {
    pending: 'Pending',
    confirmed: 'Dikonfirmasi',
    processing: 'Diproses',
    ready: 'Siap',
    done: 'Selesai',
    cancelled: 'Batal',
}

const sortKey = computed(() => props.filters.sort ?? 'created_at')
const sortDir = computed(() => (props.filters.dir === 'asc' ? 'asc' : 'desc') as 'asc' | 'desc')

function changeStore(storeId: number) {
    router.get('/admin/orders', {
        store: storeId,
        ...buildQueryParams(),
    })
}

function buildQueryParams() {
    const p: Record<string, string | undefined> = {
        date_from: filterState.value.date_from || undefined,
        date_to: filterState.value.date_to || undefined,
        type: filterState.value.type !== 'all' ? filterState.value.type : undefined,
        status: filterState.value.status !== 'all' ? filterState.value.status : undefined,
        payment_status: filterState.value.payment_status !== 'all' ? filterState.value.payment_status : undefined,
        payment_method: filterState.value.payment_method !== 'all' ? filterState.value.payment_method : undefined,
        search: filterState.value.search || undefined,
        sort: props.filters.sort,
        dir: props.filters.dir,
    }
    return Object.fromEntries(Object.entries(p).filter(([, v]) => v != null && v !== ''))
}

function applyFilters() {
    router.get('/admin/orders', {
        store: props.store.id,
        ...buildQueryParams(),
    }, { preserveState: true })
}

function setSort(key: string) {
    const newDir = sortKey.value === key && sortDir.value === 'desc' ? 'asc' : 'desc'
    router.get('/admin/orders', {
        store: props.store.id,
        ...buildQueryParams(),
        sort: key,
        dir: newDir,
    }, { preserveState: true })
}

function goToPage(url: string | null) {
    if (url) router.visit(url)
}

function viewOrder(id: number) {
    router.visit(`/admin/orders/${id}`)
}

const hasActiveFilters = computed(() =>
    !!filterState.value.search ||
    filterState.value.type !== 'all' ||
    filterState.value.status !== 'all' ||
    filterState.value.payment_status !== 'all' ||
    filterState.value.payment_method !== 'all',
)

function clearFilters() {
    filterState.value.search = ''
    filterState.value.type = 'all'
    filterState.value.status = 'all'
    filterState.value.payment_status = 'all'
    filterState.value.payment_method = 'all'
    applyFilters()
}

const updatingOrderId = ref<number | null>(null)
const cancellingOrderId = ref<number | null>(null)

function updateStatus(order: Order, newStatus: string) {
    if (order.status === 'done' || order.status === 'cancelled') return
    updatingOrderId.value = order.id
    router.put(`/admin/orders/${order.id}`, { status: newStatus }, {
        preserveScroll: true,
        onFinish: () => { updatingOrderId.value = null },
    })
}

function cancelOrder(order: Order) {
    if (order.payment_status === 'paid') return
    const msg = order.status === 'cancelled'
        ? `Hapus pesanan ${order.order_code}?`
        : `Batalkan pesanan ${order.order_code}?`
    if (!confirm(msg)) return
    cancellingOrderId.value = order.id
    router.delete(`/admin/orders/${order.id}`, {
        preserveScroll: true,
        onFinish: () => { cancellingOrderId.value = null },
    })
}

function getPaymentLabel(code: string) {
    return paymentLabels[code] ?? code?.replace(/_/g, ' ') ?? '—'
}

const selectedPayOrder = ref<Order | null>(null)
const payForm = ref({ payment_method: '', cash_received: '' })
const payProcessing = ref(false)

function openPayModal(order: Order) {
    selectedPayOrder.value = order
    const pms = props.payment_methods ?? []
    payForm.value = {
        payment_method: pms[0]?.code ?? 'cash',
        cash_received: String(order.final_amount),
    }
}

function closePayModal() {
    selectedPayOrder.value = null
}

function getRequiresCashInput() {
    const pm = (props.payment_methods ?? []).find((p) => p.code === payForm.value.payment_method)
    return pm?.requires_cash_input ?? payForm.value.payment_method === 'cash'
}

function submitPayOrder() {
    const order = selectedPayOrder.value
    if (!order) return
    payProcessing.value = true
    router.post(`/admin/orders/${order.id}/pay`, {
        payment_method: payForm.value.payment_method,
        cash_received: getRequiresCashInput() ? payForm.value.cash_received : null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closePayModal()
        },
        onError: (errors) => {
            alert(Object.values(errors).flat().join('\n'))
        },
        onFinish: () => { payProcessing.value = false },
    })
}

function isOrderDone(order: Order) {
    return order.status === 'done' || order.status === 'cancelled'
}
</script>

<template>
    <AdminLayout :title="`Pesanan - ${store.name}`">
        <div class="space-y-6">
            <!-- Filters -->
            <Card>
                <CardHeader class="pb-4">
                    <CardTitle class="flex items-center gap-2">
                        <Receipt class="h-5 w-5" />
                        Pesanan
                    </CardTitle>
                    <CardDescription>
                        Pesanan dari meja (QR) yang menunggu pembayaran. Bukan dari transaksi kasir.
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
                        <div class="flex flex-1 min-w-[120px] flex-col gap-1.5">
                            <Label class="text-xs font-medium text-muted-foreground">Status</Label>
                            <select
                                v-model="filterState.status"
                                class="filter-select h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option v-for="opt in status_options" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-1 min-w-[120px] flex-col gap-1.5">
                            <Label class="text-xs font-medium text-muted-foreground">Bayar</Label>
                            <select
                                v-model="filterState.payment_status"
                                class="filter-select h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option v-for="opt in payment_status_options" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-1 min-w-[140px] flex-col gap-1.5">
                            <Label class="text-xs font-medium text-muted-foreground">Metode Bayar</Label>
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

            <!-- Orders Table -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Daftar Pesanan dari Meja</CardTitle>
                    <CardDescription v-if="orders.total > 0">
                        {{ orders.total }} pesanan menunggu
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
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Status</th>
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
                                    <th class="w-24 px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody :key="`page-${orders.current_page}`">
                                <tr
                                    v-for="order in (orders.data ?? [])"
                                    :key="order.id"
                                    class="border-b transition-colors last:border-0 hover:bg-muted/20"
                                >
                                    <td class="align-middle px-4 py-3">
                                        <span class="font-mono text-sm font-medium">{{ order.order_code }}</span>
                                    </td>
                                    <td class="align-middle px-4 py-3 text-sm text-muted-foreground">{{ order.created_at }}</td>
                                    <td class="align-middle whitespace-nowrap px-4 py-3">
                                        <span
                                            class="inline-flex shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
                                            :class="(typeBadgeClass ?? {})[order.type] ?? 'bg-muted text-muted-foreground'"
                                        >
                                            {{ (typeLabels ?? {})[order.type] ?? order.type }}
                                        </span>
                                    </td>
                                    <td class="align-middle px-4 py-3">
                                        <span
                                            v-if="order.payment_status !== 'paid' && order.status !== 'done' && order.status !== 'cancelled'"
                                            class="inline-flex rounded-full border border-red-200 bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 whitespace-nowrap dark:border-red-800 dark:bg-red-950/50 dark:text-red-300"
                                            title="Terima pembayaran dulu sebelum pesanan bisa diproses"
                                        >
                                            Belum Bayar
                                        </span>
                                        <select
                                            v-else-if="order.status !== 'done' && order.status !== 'cancelled'"
                                            :value="order.status"
                                            :disabled="updatingOrderId === order.id"
                                            class="h-7 min-w-[100px] rounded border border-input bg-background px-2 text-xs"
                                            @change="updateStatus(order, ($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="pending">Pending</option>
                                            <option value="confirmed">Dikonfirmasi</option>
                                            <option value="processing">Diproses</option>
                                            <option value="ready">Siap</option>
                                        </select>
                                        <span
                                            v-else
                                            class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
                                            :class="(statusBadgeClass ?? {})[order.status] ?? 'bg-muted text-muted-foreground'"
                                        >
                                            {{ (statusLabels ?? {})[order.status] ?? order.status }}
                                        </span>
                                    </td>
                                    <td class="align-middle px-4 py-3 text-muted-foreground">{{ order.table_name ?? '—' }}</td>
                                    <td class="align-middle px-4 py-3">{{ order.customer_name ?? '—' }}</td>
                                    <td class="align-middle px-4 py-3 text-muted-foreground">{{ order.cashier_name ?? '—' }}</td>
                                    <td class="align-middle px-4 py-3 text-center font-medium">{{ order.item_count }}</td>
                                    <td class="align-middle px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
                                            :class="order.payment_status === 'paid'
                                                ? 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                : 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300'"
                                        >
                                            {{ order.payment_status === 'paid' ? 'Lunas' : 'Belum' }}
                                        </span>
                                    </td>
                                    <td class="align-middle px-4 py-3 text-right font-semibold tabular-nums">{{ formatCurrency(order.final_amount) }}</td>
                                    <td class="align-middle px-4 py-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Button
                                                v-if="isOrderDone(order)"
                                                variant="outline"
                                                size="sm"
                                                class="h-8 w-8 shrink-0 p-0"
                                                title="Lihat detail"
                                                @click="viewOrder(order.id)"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-else
                                                variant="outline"
                                                size="sm"
                                                class="h-8 w-8 shrink-0 p-0 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700"
                                                title="Selesaikan pembayaran"
                                                :disabled="payProcessing"
                                                @click="openPayModal(order)"
                                            >
                                                <Check class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-if="order.payment_status !== 'paid'"
                                                variant="outline"
                                                size="sm"
                                                class="h-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                :disabled="cancellingOrderId === order.id"
                                                @click="cancelOrder(order)"
                                            >
                                                <Loader2 v-if="cancellingOrderId === order.id" class="h-3.5 w-3.5 animate-spin" />
                                                <Trash2 v-else class="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!(orders.data ?? []).length">
                                    <td colspan="11" class="px-4 py-12 text-center text-muted-foreground">
                                        Belum ada pesanan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (hanya di tabel) -->
                    <div
                        v-if="orders.total > 0"
                        class="flex items-center justify-between border-t px-4 py-3"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ orders.from }}–{{ orders.to }} dari {{ orders.total }} pesanan
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

            <!-- Modal Terima Pembayaran -->
            <Dialog :open="!!selectedPayOrder" @update:open="(v) => { if (!v) closePayModal() }">
                <DialogContent class="max-w-sm" v-if="selectedPayOrder">
                    <DialogHeader>
                        <DialogTitle>Selesaikan Pembayaran</DialogTitle>
                        <DialogDescription>
                            {{ selectedPayOrder.order_code }} · Meja {{ selectedPayOrder.table_name ?? '—' }} · {{ formatCurrency(selectedPayOrder.final_amount) }}
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitPayOrder">
                        <div>
                            <Label>Metode Pembayaran</Label>
                            <select
                                v-model="payForm.payment_method"
                                class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option
                                    v-for="pm in (payment_methods ?? [])"
                                    :key="pm.id"
                                    :value="pm.code"
                                >
                                    {{ pm.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="getRequiresCashInput()">
                            <Label for="pay_cash_received">Uang Diterima</Label>
                            <Input
                                id="pay_cash_received"
                                v-model="payForm.cash_received"
                                type="number"
                                min="0"
                                step="100"
                                class="mt-1.5"
                                :placeholder="formatCurrency(selectedPayOrder.final_amount)"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                Total: {{ formatCurrency(selectedPayOrder.final_amount) }}
                            </p>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closePayModal">
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                :disabled="payProcessing || (getRequiresCashInput() && (Number(payForm.cash_received) || 0) < selectedPayOrder.final_amount)"
                            >
                                {{ payProcessing ? 'Memproses...' : 'Selesaikan Pembayaran' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
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
