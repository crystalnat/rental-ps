<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { formatCurrency, formatDateTime } from '@/lib/utils'
import { Clock, ArrowLeft, DollarSign, TrendingUp, TrendingDown, AlertCircle } from 'lucide-vue-next'

interface Shift {
    id: number
    user_name: string
    opened_at: string
    closed_at: string | null
    scheduled_start: string | null
    scheduled_end: string | null
    status: 'open' | 'closed'
    opening_cash: number
    total_sales: number | null
    total_orders: number | null
    cash_difference: number | null
}

interface StoreItem {
    id: number
    name: string
    slug: string
}

const props = defineProps<{
    stores: StoreItem[]
    store: StoreItem | null
    shifts: {
        data: Shift[]
        total: number
        current_page?: number
        last_page?: number
    }
    users?: { value: string; label: string }[]
    is_cashier?: boolean
    filters?: {
        status: string
        date_from: string | null
        date_to: string | null
        user_id: string
    }
}>()

const filterStatus = ref(props.filters?.status ?? 'all')
const filterDateFrom = ref(props.filters?.date_from ?? '')
const filterDateTo = ref(props.filters?.date_to ?? '')
const filterUserId = ref(props.filters?.user_id ?? 'all')

function applyFilters() {
    const params: Record<string, string> = {}
    if (props.store) params.store = String(props.store.id)
    if (filterStatus.value !== 'all') params.status = filterStatus.value
    if (filterDateFrom.value) params.date_from = filterDateFrom.value
    if (filterDateTo.value) params.date_to = filterDateTo.value
    if (filterUserId.value !== 'all') params.user_id = filterUserId.value
    router.get('/admin/shifts', params, { preserveState: true })
}

function changeStore(storeId: number) {
    router.get('/admin/shifts', { store: storeId })
}

function viewShift(shiftId: number) {
    router.visit(`/admin/shifts/${shiftId}`)
}

// Open Shift Dialog
const showOpenDialog = ref(false)
const openForm = ref({
    opening_cash: '',
    scheduled_start: '',
    scheduled_end: '',
})
const openProcessing = ref(false)

function openShiftDialog() {
    openForm.value = { opening_cash: '', scheduled_start: '', scheduled_end: '' }
    showOpenDialog.value = true
}

function submitOpenShift() {
    if (!props.store) return
    openProcessing.value = true
    showOpenDialog.value = false // Close immediately
    router.post('/admin/shifts/open', {
        store_id: props.store.id,
        opening_cash: Number(openForm.value.opening_cash) || 0,
        scheduled_start: openForm.value.scheduled_start || null,
        scheduled_end: openForm.value.scheduled_end || null,
    }, {
        preserveScroll: true,
        onError: () => {
            showOpenDialog.value = true // Re-open on error
        },
        onFinish: () => {
            openProcessing.value = false
        },
    })
}

const hasOpenShift = props.shifts?.data?.some(s => s.status === 'open') ?? false
</script>

<template>
    <AdminLayout title="Shift Kasir">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight flex items-center gap-2">
                        <Clock class="h-5 w-5 sm:h-6 sm:w-6 text-primary shrink-0" />
                        Shift Kasir
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Kelola buka/tutup shift dan rekonsiliasi kas
                    </p>
                </div>
                <div class="flex w-full sm:w-auto flex-col sm:flex-row sm:items-center gap-2">
                    <select
                        v-if="stores.length > 1"
                        :value="store?.id"
                        class="filter-select flex h-9 w-full sm:w-auto rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                        @change="changeStore(Number(($event.target as HTMLSelectElement).value))"
                    >
                        <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <Button v-if="store && !hasOpenShift" class="w-full sm:w-auto" @click="openShiftDialog">
                        <Clock class="mr-2 h-4 w-4" />
                        Buka Shift
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="p-3 sm:p-5">
                    <!-- Grid 2 kolom di HP agar filter tetap terbaca di layar 320px -->
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-end sm:gap-3">
                        <div class="sm:flex-1 sm:min-w-[120px]">
                            <Label class="text-xs text-muted-foreground">Status</Label>
                            <select
                                v-model="filterStatus"
                                class="filter-select mt-1 flex h-9 w-full rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                            >
                                <option value="all">Semua</option>
                                <option value="open">Buka</option>
                                <option value="closed">Tutup</option>
                            </select>
                        </div>
                        <div v-if="users && users.length > 1" class="sm:flex-1 sm:min-w-[140px]">
                            <Label class="text-xs text-muted-foreground">Kasir</Label>
                            <select
                                v-model="filterUserId"
                                class="filter-select mt-1 flex h-9 w-full rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                            >
                                <option value="all">Semua Kasir</option>
                                <option v-for="u in users" :key="u.value" :value="u.value">{{ u.label }}</option>
                            </select>
                        </div>
                        <div class="sm:flex-1 sm:min-w-[140px]">
                            <Label class="text-xs text-muted-foreground">Dari Tanggal</Label>
                            <Input v-model="filterDateFrom" type="date" class="mt-1 h-9 w-full" />
                        </div>
                        <div class="sm:flex-1 sm:min-w-[140px]">
                            <Label class="text-xs text-muted-foreground">Sampai Tanggal</Label>
                            <Input v-model="filterDateTo" type="date" class="mt-1 h-9 w-full" />
                        </div>
                        <div class="col-span-2 flex items-center gap-2 sm:shrink-0">
                            <Button
                                v-if="filterStatus !== 'all' || filterDateFrom || filterDateTo || filterUserId !== 'all'"
                                variant="ghost"
                                size="sm"
                                class="flex-1 text-muted-foreground sm:flex-none"
                                @click="filterStatus = 'all'; filterDateFrom = ''; filterDateTo = ''; filterUserId = 'all'; applyFilters()"
                            >
                                Reset
                            </Button>
                            <Button class="flex-1 sm:flex-none" @click="applyFilters">
                                Terapkan
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Shifts Table -->
            <Card>
                <CardHeader class="p-4 sm:p-6">
                    <CardTitle>Riwayat Shift</CardTitle>
                    <CardDescription v-if="store" class="break-words">
                        Toko: {{ store.name }} · Total: {{ shifts.total }} shift
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-3 pt-0 sm:p-6 sm:pt-0">
                    <div v-if="!store" class="py-12 text-center text-muted-foreground">
                        Pilih toko terlebih dahulu.
                    </div>
                    <div v-else-if="shifts.data.length === 0" class="py-12 text-center text-muted-foreground">
                        Belum ada data shift.
                    </div>

                    <!-- Desktop Table -->
                    <div v-else class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th v-if="!is_cashier" class="py-3 px-4 text-left font-medium text-muted-foreground">Kasir</th>
                                    <th class="hidden xl:table-cell py-3 px-4 text-left font-medium text-muted-foreground">Jadwal</th>
                                    <th class="py-3 px-4 text-left font-medium text-muted-foreground">Buka</th>
                                    <th class="hidden lg:table-cell py-3 px-4 text-left font-medium text-muted-foreground">Tutup</th>
                                    <th class="hidden xl:table-cell py-3 px-4 text-right font-medium text-muted-foreground">Kas Awal</th>
                                    <th class="py-3 px-4 text-right font-medium text-muted-foreground">Penjualan</th>
                                    <th class="hidden lg:table-cell py-3 px-4 text-center font-medium text-muted-foreground">Order</th>
                                    <th class="hidden lg:table-cell py-3 px-4 text-right font-medium text-muted-foreground">Selisih</th>
                                    <th class="py-3 px-4 text-center font-medium text-muted-foreground">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="shift in shifts.data"
                                    :key="shift.id"
                                    class="border-b last:border-0 cursor-pointer hover:bg-muted/50 transition-colors"
                                    @click="viewShift(shift.id)"
                                >
                                    <td v-if="!is_cashier" class="py-3 px-4 font-medium">
                                        {{ shift.user_name }}
                                        <!-- Ringkasan kolom yang disembunyikan agar info tetap ada di layar sempit -->
                                        <span v-if="shift.scheduled_start && shift.scheduled_end" class="mt-0.5 block text-xs font-normal text-muted-foreground xl:hidden">
                                            {{ shift.scheduled_start }} — {{ shift.scheduled_end }}
                                        </span>
                                    </td>
                                    <td class="hidden xl:table-cell py-3 px-4 text-xs text-muted-foreground">
                                        <span v-if="shift.scheduled_start && shift.scheduled_end">
                                            {{ shift.scheduled_start }} — {{ shift.scheduled_end }}
                                        </span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="py-3 px-4 text-xs">
                                        {{ shift.opened_at }}
                                        <span class="mt-0.5 block text-xs text-muted-foreground lg:hidden">
                                            Tutup: {{ shift.closed_at ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="hidden lg:table-cell py-3 px-4 text-xs">{{ shift.closed_at ?? '—' }}</td>
                                    <td class="hidden xl:table-cell py-3 px-4 text-right whitespace-nowrap tabular-nums">{{ formatCurrency(shift.opening_cash) }}</td>
                                    <td class="py-3 px-4 text-right font-medium whitespace-nowrap tabular-nums">
                                        {{ shift.total_sales !== null ? formatCurrency(shift.total_sales) : '—' }}
                                        <span class="mt-0.5 block text-xs font-normal text-muted-foreground lg:hidden">
                                            {{ shift.total_orders ?? 0 }} order
                                        </span>
                                    </td>
                                    <td class="hidden lg:table-cell py-3 px-4 text-center tabular-nums">{{ shift.total_orders ?? '—' }}</td>
                                    <td class="hidden lg:table-cell py-3 px-4 text-right whitespace-nowrap">
                                        <span
                                            v-if="shift.cash_difference !== null"
                                            :class="shift.cash_difference >= 0 ? 'text-green-600' : 'text-red-600'"
                                            class="font-medium"
                                        >
                                            {{ shift.cash_difference >= 0 ? '+' : '' }}{{ formatCurrency(shift.cash_difference) }}
                                        </span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <Badge :variant="shift.status === 'open' ? 'default' : 'secondary'">
                                            {{ shift.status === 'open' ? 'Buka' : 'Tutup' }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div v-if="shifts.data.length > 0" class="md:hidden space-y-3">
                        <button
                            v-for="shift in shifts.data"
                            :key="shift.id"
                            class="w-full text-left rounded-lg border p-3 hover:bg-muted/50 transition-colors"
                            @click="viewShift(shift.id)"
                        >
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <span class="font-medium min-w-0 break-words">{{ is_cashier ? shift.opened_at : shift.user_name }}</span>
                                <Badge :variant="shift.status === 'open' ? 'default' : 'secondary'" class="text-xs shrink-0">
                                    {{ shift.status === 'open' ? 'Buka' : 'Tutup' }}
                                </Badge>
                            </div>
                            <div class="grid grid-cols-1 gap-y-1 text-xs text-muted-foreground">
                                <span class="break-words">Buka: {{ shift.opened_at }}</span>
                                <span class="break-words">Tutup: {{ shift.closed_at ?? '—' }}</span>
                                <span v-if="shift.scheduled_start" class="break-words">Jadwal: {{ shift.scheduled_start }} — {{ shift.scheduled_end }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 mt-2 pt-2 border-t text-sm">
                                <span class="whitespace-nowrap">{{ shift.total_orders ?? 0 }} order</span>
                                <span class="font-semibold whitespace-nowrap tabular-nums">{{ shift.total_sales !== null ? formatCurrency(shift.total_sales) : '—' }}</span>
                            </div>
                            <div v-if="shift.cash_difference !== null" class="mt-1 text-xs text-right">
                                Selisih:
                                <span :class="shift.cash_difference >= 0 ? 'text-green-600' : 'text-red-600'" class="font-medium">
                                    {{ shift.cash_difference >= 0 ? '+' : '' }}{{ formatCurrency(shift.cash_difference) }}
                                </span>
                            </div>
                        </button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Open Shift Dialog -->
        <Dialog v-model:open="showOpenDialog">
            <DialogContent class="w-[95vw] max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5" />
                        Buka Shift Baru
                    </DialogTitle>
                    <DialogDescription>
                        Masukkan kas awal dan jadwal shift (opsional).
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitOpenShift">
                    <div>
                        <Label for="opening_cash">Kas Awal (Rp)</Label>
                        <Input
                            id="opening_cash"
                            v-model="openForm.opening_cash"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="mt-1"
                            required
                        />
                        <p class="text-xs text-muted-foreground mt-1">Jumlah uang tunai di laci kas saat ini.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="scheduled_start">Jadwal Mulai</Label>
                            <Input
                                id="scheduled_start"
                                v-model="openForm.scheduled_start"
                                type="time"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="scheduled_end">Jadwal Selesai</Label>
                            <Input
                                id="scheduled_end"
                                v-model="openForm.scheduled_end"
                                type="time"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Jadwal shift opsional. Contoh: 08:00 — 16:00
                    </p>
                    <DialogFooter class="flex-col-reverse gap-2 sm:flex-row">
                        <Button type="button" variant="outline" class="w-full sm:w-auto" @click="showOpenDialog = false">Batal</Button>
                        <Button type="submit" class="w-full sm:w-auto" :disabled="openProcessing">
                            {{ openProcessing ? 'Membuka...' : 'Buka Shift' }}
                        </Button>
                    </DialogFooter>
                </form>
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
