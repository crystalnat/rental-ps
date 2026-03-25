<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { formatCurrency } from '@/lib/utils'
import { UserCircle, Search, Plus, Pencil, Trash2, History, Loader2, Eye } from 'lucide-vue-next'

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

const filterState = ref({
    search: props.filters.search ?? '',
})

watch(() => props.filters, (f) => {
    filterState.value.search = f.search ?? ''
}, { deep: true })

function applyFilters() {
    router.get('/admin/customers', {
        search: filterState.value.search || undefined,
    }, { preserveState: true })
}

const hasActiveFilters = computed(() => !!filterState.value.search)

function clearFilters() {
    filterState.value.search = ''
    applyFilters()
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
    const payload = {
        name: form.value.name.trim(),
        email: form.value.email.trim() || undefined,
        phone: form.value.phone.trim() || undefined,
    }
    if (editingId.value != null) {
        const id = editingId.value
        formOpen.value = false
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
        formOpen.value = false
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
        historyPayload.value = null
    } finally {
        historyLoading.value = false
    }
}

function closeHistory() {
    historyOpen.value = false
    historyPayload.value = null
    historyError.value = ''
}
</script>

<template>
    <AdminLayout title="Pelanggan">
        <div class="space-y-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="grid flex-1 grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard variant="primary" class="border-none shadow-sm">
                        <template #title>Pelanggan</template>
                        <template #value>
                            <p class="text-xl md:text-2xl font-black tabular-nums">{{ customers.length }}</p>
                        </template>
                        <template #icon><UserCircle class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard variant="success" class="border-none shadow-sm">
                        <template #title>Total Transaksi</template>
                        <template #value>
                            <p class="text-xl md:text-2xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ customers.reduce((s, c) => s + c.total_orders, 0) }}</p>
                        </template>
                        <template #icon><History class="h-5 w-5" /></template>
                    </StatCard>
                    <div class="col-span-2 relative group hidden md:block">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground group-focus-within:text-primary transition-colors" />
                        <Input
                            v-model="filterState.search"
                            placeholder="Cari nama, email, atau telepon..."
                            class="h-full pl-10 border-none bg-muted/50 focus-visible:ring-1"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                </div>
                <Button class="h-11 md:h-12 font-bold px-8 shadow-lg shadow-primary/20" @click="openCreate">
                    <Plus class="mr-2 h-5 w-5" />
                    Tambah Pelanggan Baru
                </Button>
            </div>

            <!-- Mobile Search Only -->
            <div class="md:hidden relative group">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="filterState.search"
                    placeholder="Cari pelanggan..."
                    class="h-12 pl-10 border-none bg-muted/50 focus-visible:ring-1"
                    @keydown.enter="applyFilters"
                />
            </div>

            <!-- Table -->
            <Card class="border-none shadow-sm md:shadow-md overflow-hidden">
                <CardContent class="p-0">
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-muted/50 border-b">
                                    <th class="px-4 py-4 text-left font-black uppercase tracking-widest text-[10px] text-muted-foreground">Nama Pelanggan</th>
                                    <th class="px-4 py-4 text-left font-black uppercase tracking-widest text-[10px] text-muted-foreground">Kontak</th>
                                    <th class="px-4 py-4 text-center font-black uppercase tracking-widest text-[10px] text-muted-foreground">Order</th>
                                    <th class="px-4 py-4 text-right font-black uppercase tracking-widest text-[10px] text-muted-foreground">Total Belanja</th>
                                    <th class="px-4 py-4 text-right font-black uppercase tracking-widest text-[10px] text-muted-foreground">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="customer in customers" :key="customer.id" class="hover:bg-primary/5 transition-colors group">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-primary group-hover:underline cursor-pointer" @click="openHistory(customer)">
                                            {{ customer.name }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs space-y-0.5">
                                            <p v-if="customer.email">{{ customer.email }}</p>
                                            <p v-if="customer.phone" class="text-muted-foreground">{{ customer.phone }}</p>
                                            <p v-if="!customer.email && !customer.phone" class="italic text-muted-foreground/50">Tidak ada kontak</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center tabular-nums">{{ customer.total_orders }}</td>
                                    <td class="px-4 py-3 text-right font-black text-primary tabular-nums">{{ formatCurrency(customer.total_spent) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full" @click="openEdit(customer)">
                                                <Pencil class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full text-destructive hover:bg-destructive/10" @click="deleteTarget = customer">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full" @click="openHistory(customer)">
                                                <History class="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="md:hidden divide-y">
                        <div v-for="customer in customers" :key="customer.id" class="p-4 space-y-3 active:bg-muted/50 transition-colors" @click="openHistory(customer)">
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <h3 class="font-bold text-primary text-base">{{ customer.name }}</h3>
                                    <div class="text-xs space-y-1">
                                        <p v-if="customer.email" class="flex items-center gap-1.5"><Eye class="h-3 w-3" /> {{ customer.email }}</p>
                                        <p v-if="customer.phone" class="flex items-center gap-1.5"><Loader2 class="h-3 w-3" /> {{ customer.phone }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0" @click.stop>
                                    <Button variant="secondary" size="icon" class="h-9 w-9 rounded-full shadow-sm" @click="openEdit(customer)">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button variant="secondary" size="icon" class="h-9 w-9 rounded-full shadow-sm text-destructive" @click="deleteTarget = customer">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between border-t border-dashed pt-2">
                                <div class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Total Belanja ({{ customer.total_orders }} Order)</div>
                                <div class="font-black text-primary tabular-nums">{{ formatCurrency(customer.total_spent) }}</div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="customers.length === 0 && !hasActiveFilters"
                        class="flex flex-col items-center justify-center border-t px-4 py-16 text-center"
                    >
                        <UserCircle class="mb-3 h-12 w-12 text-muted-foreground/50" />
                        <p class="text-muted-foreground">Belum ada pelanggan tercatat</p>
                        <p class="mt-1 max-w-md text-sm text-muted-foreground">
                            Tambahkan pelanggan secara manual, atau pelanggan akan muncul otomatis saat order dengan data kontak diisi.
                        </p>
                        <Button type="button" class="mt-4" @click="openCreate">
                            <Plus class="mr-2 h-4 w-4" />
                            Tambah Pelanggan
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Form tambah / ubah -->
        <Dialog :open="formOpen" @update:open="(v) => { formOpen = v }">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editingId != null ? 'Ubah Pelanggan' : 'Tambah Pelanggan' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingId != null ? 'Perbarui nama dan kontak pelanggan.' : 'Catat pelanggan baru untuk brand Anda.' }}
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitForm">
                    <div>
                        <Label for="cust-name">Nama <span class="text-destructive">*</span></Label>
                        <Input
                            id="cust-name"
                            v-model="form.name"
                            class="mt-1.5"
                            placeholder="Nama lengkap"
                            required
                        />
                    </div>
                    <div>
                        <Label for="cust-email">Email</Label>
                        <Input
                            id="cust-email"
                            v-model="form.email"
                            type="email"
                            class="mt-1.5"
                            placeholder="opsional"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <Label for="cust-phone">Telepon</Label>
                        <Input
                            id="cust-phone"
                            v-model="form.phone"
                            class="mt-1.5"
                            placeholder="opsional"
                            autocomplete="off"
                        />
                    </div>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="formOpen = false">
                            Batal
                        </Button>
                        <Button type="submit" :disabled="processing || !form.name.trim()">
                            {{ processing ? 'Menyimpan...' : (editingId != null ? 'Simpan' : 'Tambah') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="!!deleteTarget"
            title="Hapus Pelanggan"
            :description="deleteTarget ? ('Hapus ' + deleteTarget.name + '? Data pesanan lama tetap ada di sistem.') : ''"
            variant="destructive"
            confirm-label="Ya, Hapus"
            @update:open="(v) => !v && (deleteTarget = null)"
            @confirm="handleDelete"
        />

        <!-- Riwayat pesanan -->
        <Dialog :open="historyOpen" @update:open="(v) => !v && closeHistory()">
            <DialogContent class="flex max-h-[90vh] max-w-3xl flex-col gap-0 overflow-hidden p-0">
                <div class="border-b px-6 py-4">
                    <DialogHeader>
                        <DialogTitle>Riwayat pesanan</DialogTitle>
                        <DialogDescription v-if="historyPayload">
                            {{ historyPayload.customer.name }}
                            <span v-if="historyPayload.customer.email || historyPayload.customer.phone" class="block text-xs">
                                <span v-if="historyPayload.customer.email">{{ historyPayload.customer.email }}</span>
                                <span v-if="historyPayload.customer.email && historyPayload.customer.phone"> · </span>
                                <span v-if="historyPayload.customer.phone">{{ historyPayload.customer.phone }}</span>
                            </span>
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    <div v-if="historyLoading" class="flex justify-center py-16">
                        <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                    </div>
                    <p v-else-if="historyError" class="text-center text-sm text-destructive">{{ historyError }}</p>
                    <template v-else-if="historyPayload">
                        <div class="mb-4 flex flex-wrap gap-2">
                            <Badge variant="outline">{{ historyPayload.customer.total_orders }} pesanan</Badge>
                            <Badge variant="secondary">{{ formatCurrency(historyPayload.customer.total_spent) }} total belanja</Badge>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm [&_td]:align-middle">
                                <thead>
                                    <tr class="border-b bg-muted/30">
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kode</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Waktu</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Toko</th>
                                        <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tipe</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Meja</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kasir</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total</th>
                                        <th class="w-20 px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="order in historyPayload.orders"
                                        :key="order.id"
                                        class="border-b transition-colors last:border-0 hover:bg-muted/20"
                                    >
                                        <td class="px-4 py-3 font-mono text-[10px] font-black">{{ order.order_code }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-xs text-muted-foreground">{{ order.created_at }}</td>
                                        <td class="px-4 py-3 text-xs">{{ order.store_name }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <span class="inline-flex rounded-full border bg-muted px-2 py-0.5 text-[10px] font-black uppercase tracking-widest">
                                                {{ typeLabels[order.type] ?? order.type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-muted-foreground">{{ order.table_name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-[10px] text-muted-foreground">{{ order.cashier_name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right font-black text-primary tabular-nums">{{ formatCurrency(order.final_amount) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full" as-child>
                                                <a :href="`/admin/orders/${order.id}`">
                                                    <Eye class="h-4 w-4" />
                                                </a>
                                            </Button>
                                        </td>
                                    </tr>
                                    <tr v-if="historyPayload.orders.length === 0">
                                        <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">
                                            Belum ada riwayat pesanan
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
