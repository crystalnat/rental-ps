<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import TableToolbar from '@/components/TableToolbar.vue'
import Pagination from '@/components/Pagination.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { 
    UserCircle, Search, Plus, Pencil, Trash2, 
    History, Loader2, Eye, Contact, Mail, Phone, ShoppingBag
} from 'lucide-vue-next'
import { debounce } from 'lodash'

interface CustomerItem {
    id: number
    name: string
    email: string | null
    phone: string | null
    total_orders: number
    total_spent: number
}

interface HistoryCustomer {
    id: number
    name: string
    email: string | null
    phone: string | null
    total_orders: number
    total_spent: number
}

interface HistoryOrder {
    id: number
    order_code: string
    type: string
    status: string
    payment_status: string
    final_amount: number
    store_name: string
    cashier_name: string | null
    table_name: string | null
    created_at: string
}

const typeLabels: Record<string, string> = {
    dine_in: 'Dine In',
    takeaway: 'Take Away',
    walk_in: 'Walk In',
}

const props = defineProps<{
    customers: CustomerItem[]
    filters: { search: string }
}>()

const searchQuery = ref(props.filters.search ?? '')

// Live search consistency
watch(searchQuery, debounce((value: string) => {
    router.get('/admin/customers', { search: value }, {
        preserveState: true,
        replace: true,
    })
}, 300))

function formatCurrency(amount: number | string | null) {
    if (amount === null) return '-'
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(amount))
}

const processing = ref(false)
const formOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
    name: '',
    email: '',
    phone: '',
})

function openCreate() {
    editingId.value = null
    form.value = { name: '', email: '', phone: '' }
    formOpen.value = true
}

function openEdit(c: CustomerItem) {
    editingId.value = c.id
    form.value = {
        name: c.name,
        email: c.email ?? '',
        phone: c.phone ?? '',
    }
    formOpen.value = true
}

function submitForm() {
    if (!form.value.name.trim()) return
    processing.value = true
    
    // Pattern Categories: Close modal early
    formOpen.value = false
    
    const payload = {
        name: form.value.name.trim(),
        email: form.value.email.trim() || null,
        phone: form.value.phone.trim() || null,
    }

    if (editingId.value != null) {
        const id = editingId.value
        router.put(`/admin/customers/${id}`, payload, {
            preserveScroll: true,
            onError: () => {
                editingId.value = id
                formOpen.value = true
            },
            onFinish: () => {
                processing.value = false
            },
        })
    } else {
        router.post('/admin/customers', payload, {
            preserveScroll: true,
            onError: () => {
                formOpen.value = true
            },
            onFinish: () => {
                processing.value = false
            },
        })
    }
}

const deleteTarget = ref<CustomerItem | null>(null)

function handleDelete() {
    if (!deleteTarget.value) return
    const id = deleteTarget.value.id
    deleteTarget.value = null
    router.delete(`/admin/customers/${id}`, {
        preserveScroll: true,
    })
}

const historyOpen = ref(false)
const historyLoading = ref(false)
const historyPayload = ref<{ customer: HistoryCustomer; orders: HistoryOrder[] } | null>(null)
const historyError = ref('')

async function openHistory(c: CustomerItem) {
    historyError.value = ''
    historyOpen.value = true
    historyLoading.value = true
    historyPayload.value = null
    try {
        const res = await fetch(`/admin/customers/${c.id}/history`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        if (!res.ok) throw new Error('fetch failed')
        historyPayload.value = await res.json()
    } catch {
        historyError.value = 'Gagal memuat riwayat pesanan.'
    } finally {
        historyLoading.value = false
    }
}

function closeHistory() {
    historyOpen.value = false
    historyPayload.value = null
    historyError.value = ''
}

const totalOrders = computed(() => props.customers.reduce((s, c) => s + c.total_orders, 0))
</script>

<template>
    <Head title="Manajemen Pelanggan" />

    <AdminLayout title="Pelanggan">
        <!-- Summary Cards -->
        <div class="mb-6 grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-3">
            <StatCard variant="primary" class="p-3 md:p-5">
                <template #title>Total Pelanggan</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold">{{ customers.length }}</p>
                </template>
                <template #icon><UserCircle class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="success" class="p-3 md:p-5">
                <template #title>Total Transaksi</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ totalOrders }}</p>
                </template>
                <template #icon><ShoppingBag class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="warning" class="p-3 md:p-5 hidden lg:block">
                <template #title>Loyalitas</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold text-amber-600 dark:text-amber-400">Database</p>
                </template>
                <template #icon><Contact class="h-5 w-5" /></template>
            </StatCard>
        </div>

        <!-- Toolbar -->
        <TableToolbar
            v-model="searchQuery"
            search-placeholder="Cari nama, email, atau telepon..."
        >
            <template #filters>
                <Button size="sm" class="h-9" @click="openCreate">
                    <Plus class="mr-1 h-4 w-4" />
                    Tambah Pelanggan
                </Button>
            </template>
        </TableToolbar>

        <!-- Customers Table -->
        <Card variant="elevated" class="mt-4">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-muted/30 text-muted-foreground border-b text-xs font-semibold uppercase tracking-wide">
                            <tr>
                                <th class="h-11 px-4">Nama Pelanggan</th>
                                <th class="h-11 px-4">Informasi Kontak</th>
                                <th class="h-11 px-4 text-center">Total Order</th>
                                <th class="h-11 px-4 text-right">Total Belanja</th>
                                <th class="h-11 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-foreground">
                            <tr v-for="customer in customers" :key="customer.id" class="hover:bg-muted/10 transition-colors group">
                                <td class="p-4 align-middle">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <UserCircle class="h-5 w-5" />
                                        </div>
                                        <span class="font-bold cursor-pointer hover:underline" @click="openHistory(customer)">
                                            {{ customer.name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="flex flex-col gap-0.5 text-xs">
                                        <div v-if="customer.email" class="flex items-center gap-1.5 text-muted-foreground">
                                            <Mail class="h-3 w-3" /> {{ customer.email }}
                                        </div>
                                        <div v-if="customer.phone" class="flex items-center gap-1.5 text-muted-foreground">
                                            <Phone class="h-3 w-3" /> {{ customer.phone }}
                                        </div>
                                        <span v-if="!customer.email && !customer.phone" class="text-muted-foreground/40 italic">Tanpa Kontak</span>
                                    </div>
                                </td>
                                <td class="p-4 align-middle text-center tabular-nums font-medium">
                                    {{ customer.total_orders }}
                                </td>
                                <td class="p-4 align-middle text-right font-bold text-primary tabular-nums">
                                    {{ formatCurrency(customer.total_spent) }}
                                </td>
                                <td class="p-4 align-middle text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-primary" @click="openEdit(customer)" title="Edit">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-primary" @click="openHistory(customer)" title="Riwayat">
                                            <History class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-destructive" @click="deleteTarget = customer" title="Hapus">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="customers.length === 0">
                                <td colspan="5" class="py-20 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center">
                                        <UserCircle class="h-10 w-10 text-muted-foreground/20 mb-3" />
                                        <p class="font-medium text-sm">Belum ada pelanggan ditemukan</p>
                                        <p class="text-xs text-muted-foreground max-w-xs mx-auto mt-1">
                                            Halaman ini mencatat semua pelanggan yang pernah bertransaksi atau didaftarkan secara manual.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Form Dialog -->
        <Dialog :open="formOpen" @update:open="formOpen = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editingId != null ? 'Ubah Data Pelanggan' : 'Tambah Pelanggan Baru' }}</DialogTitle>
                    <DialogDescription>
                        Lengkapi informasi kontak pelanggan untuk database brand Anda.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4 py-2" @submit.prevent="submitForm">
                    <div class="space-y-2">
                        <Label for="cust-name">Nama Pelanggan <span class="text-destructive">*</span></Label>
                        <Input id="cust-name" v-model="form.name" placeholder="Misal: Budi Santoso" required class="text-foreground" />
                    </div>
                    <div class="space-y-2">
                        <Label for="cust-email">Email (Opsional)</Label>
                        <Input id="cust-email" v-model="form.email" type="email" placeholder="budi@email.com" class="text-foreground" />
                    </div>
                    <div class="space-y-2">
                        <Label for="cust-phone">Nomor Telepon (Opsional)</Label>
                        <Input id="cust-phone" v-model="form.phone" placeholder="08123456789" class="text-foreground" />
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="formOpen = false" :disabled="processing">Batal</Button>
                        <Button type="submit" :disabled="processing || !form.name.trim()">
                            <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                            {{ editingId != null ? 'Simpan Perubahan' : 'Tambah Pelanggan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- History Dialog -->
        <Dialog :open="historyOpen" @update:open="(v) => !v && closeHistory()">
            <DialogContent class="flex max-h-[90vh] max-w-4xl flex-col gap-0 overflow-hidden p-0">
                <div class="border-b px-6 py-5 bg-muted/20">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <History class="h-5 w-5 text-primary" />
                            Riwayat Pesanan Pelanggan
                        </DialogTitle>
                        <DialogDescription v-if="historyPayload" class="text-foreground font-medium text-base mt-2">
                            {{ historyPayload.customer.name }}
                            <span v-if="historyPayload.customer.phone" class="text-xs text-muted-foreground ml-2">({{ historyPayload.customer.phone }})</span>
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-6">
                    <div v-if="historyLoading" class="flex flex-col items-center justify-center py-20 text-muted-foreground gap-3">
                        <Loader2 class="h-8 w-8 animate-spin" />
                        <span class="text-xs italic">Memuat riwayat belanja...</span>
                    </div>
                    <p v-else-if="historyError" class="text-center py-10 text-sm text-destructive font-medium">{{ historyError }}</p>
                    
                    <template v-else-if="historyPayload">
                        <div class="mb-6 grid grid-cols-2 gap-4">
                            <div class="rounded-lg border bg-card p-4 shadow-sm">
                                <p class="text-[10px] uppercase font-black text-muted-foreground tracking-widest mb-1">Total Kunjungan</p>
                                <p class="text-2xl font-bold">{{ historyPayload.customer.total_orders }} <span class="text-sm font-normal text-muted-foreground ml-1">Order</span></p>
                            </div>
                            <div class="rounded-lg border bg-card p-4 shadow-sm">
                                <p class="text-[10px] uppercase font-black text-muted-foreground tracking-widest mb-1">Total Kontribusi</p>
                                <p class="text-2xl font-bold text-primary">{{ formatCurrency(historyPayload.customer.total_spent) }}</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50 border-b">
                                    <tr class="text-xs font-black uppercase text-muted-foreground tracking-wider">
                                        <th class="px-4 py-3 text-left">Nota</th>
                                        <th class="px-4 py-3 text-left">Waktu</th>
                                        <th class="px-4 py-3 text-left">Toko</th>
                                        <th class="px-4 py-3 text-center">Tipe</th>
                                        <th class="px-4 py-3 text-right">Total</th>
                                        <th class="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-foreground">
                                    <tr
                                        v-for="order in historyPayload.orders"
                                        :key="order.id"
                                        class="hover:bg-muted/30 transition-colors"
                                    >
                                        <td class="px-4 py-3 font-mono text-[11px] font-bold">{{ order.order_code }}</td>
                                        <td class="px-4 py-3 text-[11px] text-muted-foreground">
                                            {{ new Date(order.created_at).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                                        </td>
                                        <td class="px-4 py-3 text-[11px]">{{ order.store_name }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <Badge variant="outline" class="text-[10px] font-bold uppercase tracking-widest px-1.5 py-0 whitespace-nowrap">
                                                {{ typeLabels[order.type] ?? order.type }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ formatCurrency(order.final_amount) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <Button variant="ghost" size="icon" class="h-7 w-7 rounded-full" as-child>
                                                <Link :href="`/admin/orders/${order.id}`">
                                                    <Eye class="h-3.5 w-3.5" />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                    <tr v-if="historyPayload.orders.length === 0">
                                        <td colspan="6" class="px-4 py-16 text-center text-muted-foreground italic">
                                            Belum ada riwayat pesanan tercatat.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
                <DialogFooter class="px-6 py-4 border-t bg-muted/10">
                    <Button variant="outline" @click="closeHistory">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="!!deleteTarget"
            title="Hapus Pelanggan"
            :description="deleteTarget ? ('Hapus data pelanggan ' + deleteTarget.name + '? Catatan transaksi lama akan tetap tersimpan.') : ''"
            variant="destructive"
            confirm-label="Ya, Hapus"
            @update:open="(v) => !v && (deleteTarget = null)"
            @confirm="handleDelete"
        />
    </AdminLayout>
</template>
