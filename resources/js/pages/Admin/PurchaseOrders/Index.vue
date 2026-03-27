<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import Pagination from '@/components/Pagination.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import TableToolbar from '@/components/TableToolbar.vue'
import { TableHeadSortable } from '@/components/ui/table'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { 
    FileText, Plus, Eye, XCircle, Trash2, 
    Clock, CheckCircle2, Warehouse, Pencil
} from 'lucide-vue-next'

interface PurchaseOrder {
    id: number
    po_number: string
    store: { name: string }
    supplier: { name: string }
    status: 'pending' | 'received' | 'cancelled'
    total_amount: number
    expected_date: string | null
    created_at: string
}

const props = defineProps<{
    purchaseOrders: {
        data: PurchaseOrder[]
        links: any[]
        total: number
    }
    stats: {
        total: number
        pending: number
        total_amount: number
    }
    filters: {
        search?: string
        sort?: string
        direction?: 'asc' | 'desc'
    }
}>()

const searchQuery = ref(props.filters.search || '')

function handleSearch(val: string) {
    router.get('/admin/purchase-orders', { ...props.filters, search: val }, { preserveState: true, replace: true })
}

function setSort(column: string) {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc'
    router.get('/admin/purchase-orders', { ...props.filters, sort: column, direction }, { preserveState: true })
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
}

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function getStatusProps(status: string) {
    switch (status) {
        case 'pending': return { variant: 'secondary', label: 'Menunggu' }
        case 'received': return { variant: 'success', label: 'Diterima' }
        case 'cancelled': return { variant: 'destructive', label: 'Dibatalkan' }
        default: return { variant: 'outline', label: status.toUpperCase() }
    }
}

const deleteTarget = ref<PurchaseOrder | null>(null)
const cancelTarget = ref<PurchaseOrder | null>(null)

function handleCancel() {
    if (!cancelTarget.value) return
    router.post(`/admin/purchase-orders/${cancelTarget.value.id}/cancel`, {}, {
        onSuccess: () => cancelTarget.value = null
    })
}

function handleDelete() {
    if (!deleteTarget.value) return
    router.delete(`/admin/purchase-orders/${deleteTarget.value.id}`, {
        onSuccess: () => deleteTarget.value = null
    })
}
</script>

<template>
    <Head title="Purchase Order" />

    <AdminLayout title="Purchase Order">
        <!-- Summary Units -->
        <div class="mb-6 grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-3">
            <StatCard variant="primary" class="p-3 md:p-5">
                <template #title>Total PO</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold">{{ stats.total }}</p>
                </template>
                <template #icon><FileText class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="warning" class="p-3 md:p-5">
                <template #title>Pending</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.pending }}</p>
                </template>
                <template #icon><Clock class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="success" class="p-3 md:p-5 hidden lg:block">
                <template #title>Total Nominal</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(stats.total_amount) }}</p>
                </template>
                <template #icon><CheckCircle2 class="h-5 w-5" /></template>
            </StatCard>
        </div>

        <!-- Table Toolbar -->
        <TableToolbar
            v-model="searchQuery"
            search-placeholder="Cari PO, supplier, atau cabang..."
            @update:model-value="handleSearch"
        >
            <template #filters>
                <Link href="/admin/purchase-orders/create">
                    <Button size="sm" class="h-9">
                        <Plus class="mr-1 h-4 w-4" />
                        Buat PO
                    </Button>
                </Link>
            </template>
        </TableToolbar>

        <!-- PO Table -->
        <Card variant="elevated" class="mt-4">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b bg-muted/30">
                                <TableHeadSortable
                                    label="No. PO"
                                    sort-key="po_number"
                                    :current-sort-key="filters.sort || null"
                                    :sort-dir="filters.direction || 'asc'"
                                    @sort="setSort"
                                />
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Supplier</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tujuan</th>
                                <TableHeadSortable
                                    label="Total"
                                    sort-key="total_amount"
                                    :current-sort-key="filters.sort || null"
                                    :sort-dir="filters.direction || 'asc'"
                                    align="right"
                                    @sort="setSort"
                                />
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="po in purchaseOrders.data" :key="po.id" class="border-b transition-colors hover:bg-muted/20">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-primary hover:underline cursor-pointer" @click="router.visit(`/admin/purchase-orders/${po.id}`)">
                                        {{ po.po_number }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        {{ formatDate(po.created_at) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm align-middle">
                                    {{ po.supplier.name }}
                                </td>
                                <td class="px-4 py-3 text-sm align-middle">
                                    <div class="flex items-center gap-1">
                                        <Warehouse class="h-3 w-3 text-muted-foreground" />
                                        {{ po.store.name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-medium tabular-nums">
                                    {{ formatCurrency(po.total_amount) }}
                                </td>
                                <td class="px-4 py-3 text-center align-middle">
                                    <Badge :variant="getStatusProps(po.status).variant as any">
                                        {{ getStatusProps(po.status).label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right align-middle">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="router.visit(`/admin/purchase-orders/${po.id}`)">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                        <template v-if="po.status === 'pending'">
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="router.visit(`/admin/purchase-orders/${po.id}/edit`)">
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0 text-destructive hover:bg-destructive/10" @click="deleteTarget = po">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="purchaseOrders.total === 0">
                                <td colspan="6" class="py-12 text-center text-muted-foreground">
                                    Belum ada data Purchase Order.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="purchaseOrders.total > 15" class="mt-4">
            <Pagination :links="purchaseOrders.links" />
        </div>

        <ConfirmDialog
            :open="!!cancelTarget"
            title="Batalkan Pesanan?"
            :description="'Apakah Anda yakin ingin membatalkan Purchase Order ' + cancelTarget?.po_number + '?'"
            variant="destructive"
            confirm-label="Ya, Batalkan"
            @update:open="(v) => !v && (cancelTarget = null)"
            @confirm="handleCancel"
        />

        <ConfirmDialog
            :open="!!deleteTarget"
            title="Hapus Data PO?"
            :description="'Hapus data ' + deleteTarget?.po_number + ' ini secara permanen?'"
            variant="destructive"
            confirm-label="Ya, Hapus"
            @update:open="(v) => !v && (deleteTarget = null)"
            @confirm="handleDelete"
        />

    </AdminLayout>
</template>
