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
import { formatCurrency } from '@/lib/utils'
import { ArrowLeft, Clock, ShoppingCart, TrendingUp, TrendingDown, AlertCircle, Wallet, CreditCard } from 'lucide-vue-next'

interface ShiftDetail {
    id: number
    store_name: string
    user_name: string
    scheduled_start: string | null
    scheduled_end: string | null
    opened_at: string
    closed_at: string | null
    status: 'open' | 'closed'
    opening_cash: number
    expected_cash: number | null
    actual_cash: number | null
    cash_difference: number | null
    total_sales: number | null
    total_orders: number | null
    total_cash_sales: number | null
    total_non_cash_sales: number | null
    total_expenses: number | null
    total_refunds: number | null
    notes: string | null
}

interface ShiftOrder {
    id: number
    order_code: string
    payment_method: string
    final_amount: number
    paid_at: string | null
    items: {
        product_name: string
        quantity: number
        unit: string
        subtotal: number
    }[]
}

interface PaymentBreakdown {
    method: string
    count: number
    total: number
}

interface CashMovement {
    time: string
    label: string
    ref: string | null
    type: 'in' | 'out'
    amount: number
    balance: number
}

const props = defineProps<{
    shift: ShiftDetail
    orders: ShiftOrder[]
    payment_breakdown: PaymentBreakdown[]
    cash_movements: CashMovement[]
}>()

const showCloseDialog = ref(false)
const closeForm = ref({
    actual_cash: '',
    notes: '',
})
const closeProcessing = ref(false)

const selectedOrder = ref<ShiftOrder | null>(null)
const showOrderDialog = ref(false)

function viewOrder(order: ShiftOrder) {
    selectedOrder.value = order
    showOrderDialog.value = true
}

function goBack() {
    router.visit('/admin/shifts')
}

function openCloseDialog() {
    closeForm.value = { actual_cash: '', notes: '' }
    showCloseDialog.value = true
}

function submitCloseShift() {
    closeProcessing.value = true
    showCloseDialog.value = false // Close immediately
    router.post(`/admin/shifts/${props.shift.id}/close`, {
        actual_cash: Number(closeForm.value.actual_cash) || 0,
        notes: closeForm.value.notes || null,
    }, {
        preserveScroll: true,
        onError: () => {
            showCloseDialog.value = true // Re-open on error
        },
        onFinish: () => {
            closeProcessing.value = false
        },
    })
}

const paymentMethodLabels: Record<string, string> = {
    cash: 'Tunai',
    qris: 'QRIS',
    bank_transfer: 'Transfer Bank',
    e_wallet: 'E-Wallet',
}
</script>

<template>
    <AdminLayout :title="`Shift - ${shift.user_name}`">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <Button variant="ghost" size="sm" class="self-start" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>
                <Button v-if="shift.status === 'open'" variant="destructive" class="w-full sm:w-auto" @click="openCloseDialog">
                    <Clock class="mr-2 h-4 w-4" />
                    Tutup Shift
                </Button>
            </div>

            <!-- Shift Info -->
            <Card>
                <CardHeader>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <CardTitle class="flex items-center gap-2">
                            <Clock class="h-5 w-5 shrink-0 text-primary" />
                            Detail Shift
                        </CardTitle>
                        <Badge :variant="shift.status === 'open' ? 'default' : 'secondary'" class="shrink-0 whitespace-nowrap text-sm">
                            {{ shift.status === 'open' ? 'Masih Buka' : 'Sudah Ditutup' }}
                        </Badge>
                    </div>
                    <CardDescription class="break-words">
                        {{ shift.store_name }} · {{ shift.user_name }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
                        <div class="min-w-0 rounded-lg border p-3 sm:p-4">
                            <p class="mb-1 text-xs text-muted-foreground">Buka</p>
                            <p class="text-sm font-semibold break-words">{{ shift.opened_at }}</p>
                        </div>
                        <div class="min-w-0 rounded-lg border p-3 sm:p-4">
                            <p class="mb-1 text-xs text-muted-foreground">Tutup</p>
                            <p class="text-sm font-semibold break-words">{{ shift.closed_at ?? '— (Masih buka)' }}</p>
                        </div>
                        <div v-if="shift.scheduled_start" class="min-w-0 rounded-lg border p-3 sm:p-4">
                            <p class="mb-1 text-xs text-muted-foreground">Jadwal Shift</p>
                            <p class="text-sm font-semibold break-words">{{ shift.scheduled_start }} — {{ shift.scheduled_end }}</p>
                        </div>
                        <div class="min-w-0 rounded-lg border p-3 sm:p-4">
                            <p class="mb-1 text-xs text-muted-foreground">Kas Awal</p>
                            <p class="text-sm font-semibold tabular-nums break-words">{{ formatCurrency(shift.opening_cash) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Financial Summary (only if closed) -->
            <div v-if="shift.status === 'closed'" class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-3">
                            <div class="shrink-0 rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                                <TrendingUp class="h-5 w-5 text-green-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-muted-foreground">Total Penjualan</p>
                                <p class="text-lg font-bold tabular-nums break-words">{{ formatCurrency(shift.total_sales ?? 0) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-3">
                            <div class="shrink-0 rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                                <ShoppingCart class="h-5 w-5 text-blue-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-muted-foreground">Total Order</p>
                                <p class="text-lg font-bold tabular-nums">{{ shift.total_orders ?? 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-3">
                            <div class="shrink-0 rounded-lg bg-orange-100 p-2 dark:bg-orange-900/30">
                                <TrendingDown class="h-5 w-5 text-orange-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-muted-foreground">Total Pengeluaran</p>
                                <p class="text-lg font-bold tabular-nums break-words">{{ formatCurrency(shift.total_expenses ?? 0) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="shrink-0 rounded-lg p-2"
                                :class="(shift.cash_difference ?? 0) >= 0
                                    ? 'bg-green-100 dark:bg-green-900/30'
                                    : 'bg-red-100 dark:bg-red-900/30'"
                            >
                                <AlertCircle
                                    class="h-5 w-5"
                                    :class="(shift.cash_difference ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'"
                                />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-muted-foreground">Selisih Kas</p>
                                <p
                                    class="text-lg font-bold tabular-nums break-words"
                                    :class="(shift.cash_difference ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'"
                                >
                                    {{ (shift.cash_difference ?? 0) >= 0 ? '+' : '' }}{{ formatCurrency(shift.cash_difference ?? 0) }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Cash Reconciliation (only if closed) -->
            <Card v-if="shift.status === 'closed'">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Wallet class="h-5 w-5 text-primary" />
                        Rekonsiliasi Kas
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="space-y-3 text-sm sm:text-base">
                            <div class="flex justify-between gap-3 border-b py-2">
                                <span class="min-w-0 text-muted-foreground">Kas Awal</span>
                                <span class="shrink-0 font-medium tabular-nums whitespace-nowrap">{{ formatCurrency(shift.opening_cash) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 border-b py-2">
                                <span class="min-w-0 text-muted-foreground">+ Penjualan Tunai</span>
                                <span class="shrink-0 font-medium text-green-600 tabular-nums whitespace-nowrap">{{ formatCurrency(shift.total_cash_sales ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 border-b py-2">
                                <span class="min-w-0 text-muted-foreground">− Pengeluaran</span>
                                <span class="shrink-0 font-medium text-red-600 tabular-nums whitespace-nowrap">-{{ formatCurrency(shift.total_expenses ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 border-b py-2">
                                <span class="min-w-0 text-muted-foreground">− Refund Tunai</span>
                                <span class="shrink-0 font-medium text-red-600 tabular-nums whitespace-nowrap">-{{ formatCurrency(shift.total_refunds ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 border-b-2 border-foreground py-2 text-base font-bold">
                                <span class="min-w-0">Kas Seharusnya</span>
                                <span class="shrink-0 tabular-nums whitespace-nowrap">{{ formatCurrency(shift.expected_cash ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 py-2">
                                <span class="min-w-0 text-muted-foreground">Kas Aktual (dihitung)</span>
                                <span class="shrink-0 text-lg font-bold tabular-nums whitespace-nowrap">{{ formatCurrency(shift.actual_cash ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 rounded-lg bg-muted/50 px-3 py-2">
                                <span class="min-w-0 font-medium">Selisih</span>
                                <span
                                    class="shrink-0 text-lg font-bold tabular-nums whitespace-nowrap"
                                    :class="(shift.cash_difference ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'"
                                >
                                    {{ (shift.cash_difference ?? 0) >= 0 ? '+' : '' }}{{ formatCurrency(shift.cash_difference ?? 0) }}
                                </span>
                            </div>
                        </div>
                        <div class="space-y-3 text-sm sm:text-base">
                            <h4 class="mb-2 text-sm font-medium text-muted-foreground">Penjualan Non-Tunai</h4>
                            <div class="flex justify-between gap-3 border-b py-2">
                                <span class="min-w-0 text-muted-foreground">Total Non-Tunai</span>
                                <span class="shrink-0 font-medium tabular-nums whitespace-nowrap">{{ formatCurrency(shift.total_non_cash_sales ?? 0) }}</span>
                            </div>
                            <h4 class="mb-2 mt-4 text-sm font-medium text-muted-foreground">Per Metode Pembayaran</h4>
                            <div
                                v-for="pb in payment_breakdown"
                                :key="pb.method"
                                class="flex justify-between gap-3 border-b py-2"
                            >
                                <span class="flex min-w-0 flex-wrap items-center gap-2">
                                    <CreditCard class="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <span class="min-w-0 break-words">{{ paymentMethodLabels[pb.method] ?? pb.method }}</span>
                                    <Badge variant="secondary" class="shrink-0 text-xs">{{ pb.count }}×</Badge>
                                </span>
                                <span class="shrink-0 font-medium tabular-nums whitespace-nowrap">{{ formatCurrency(pb.total) }}</span>
                            </div>
                            <div v-if="shift.notes" class="mt-4 rounded-lg bg-muted/50 p-3">
                                <p class="mb-1 text-xs text-muted-foreground">Catatan</p>
                                <p class="text-sm break-words">{{ shift.notes }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Mutasi Kas -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Wallet class="h-5 w-5 text-primary" />
                        Mutasi Kas
                    </CardTitle>
                    <CardDescription>Aliran uang tunai di laci: kas awal, penjualan tunai, pengeluaran, dan refund tunai.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="cash_movements.length === 0" class="py-8 text-center text-muted-foreground">
                        Belum ada mutasi kas.
                    </div>

                    <!-- Desktop: tabel -->
                    <div v-else class="hidden md:block">
                        <table class="w-full text-sm">
                            <thead class="border-b text-left text-muted-foreground">
                                <tr>
                                    <th class="py-2 pr-3 font-medium">Waktu</th>
                                    <th class="px-3 py-2 font-medium">Keterangan</th>
                                    <th class="px-3 py-2 text-right font-medium">Masuk</th>
                                    <th class="px-3 py-2 text-right font-medium">Keluar</th>
                                    <th class="py-2 pl-3 text-right font-medium">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(m, i) in cash_movements" :key="i" class="hover:bg-muted/40">
                                    <td class="py-2 pr-3 whitespace-nowrap text-muted-foreground">{{ m.time }}</td>
                                    <td class="px-3 py-2 break-words">
                                        {{ m.label }}
                                        <span v-if="m.ref" class="font-mono text-xs text-muted-foreground">· {{ m.ref }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium text-green-600 tabular-nums whitespace-nowrap">
                                        {{ m.type === 'in' ? formatCurrency(m.amount) : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium text-red-600 tabular-nums whitespace-nowrap">
                                        {{ m.type === 'out' ? '-' + formatCurrency(m.amount) : '—' }}
                                    </td>
                                    <td class="py-2 pl-3 text-right font-semibold tabular-nums whitespace-nowrap">{{ formatCurrency(m.balance) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Kartu vertikal untuk layar sempit -->
                    <div v-if="cash_movements.length > 0" class="space-y-2 md:hidden">
                        <div
                            v-for="(m, i) in cash_movements"
                            :key="i"
                            class="rounded-lg border p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ m.label }}</p>
                                    <p class="text-xs text-muted-foreground break-all">
                                        {{ m.time }}<span v-if="m.ref" class="font-mono"> · {{ m.ref }}</span>
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 text-sm font-semibold tabular-nums whitespace-nowrap"
                                    :class="m.type === 'in' ? 'text-green-600' : 'text-red-600'"
                                >
                                    {{ m.type === 'in' ? '+' : '-' }}{{ formatCurrency(m.amount) }}
                                </span>
                            </div>
                            <div class="mt-1 flex justify-between gap-3 text-xs text-muted-foreground">
                                <span>Saldo laci</span>
                                <span class="font-medium text-foreground tabular-nums whitespace-nowrap">{{ formatCurrency(m.balance) }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Orders in this Shift -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ShoppingCart class="h-5 w-5 text-primary" />
                        Transaksi dalam Shift
                    </CardTitle>
                    <CardDescription>{{ orders.length }} pesanan</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="orders.length === 0" class="py-8 text-center text-muted-foreground">
                        Belum ada transaksi.
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="order in orders"
                            :key="order.id"
                            class="flex cursor-pointer items-start justify-between gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
                            @click="viewOrder(order)"
                        >
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-medium break-all">{{ order.order_code }}</p>
                                <p class="text-xs text-muted-foreground break-words">
                                    {{ order.paid_at }} · {{ paymentMethodLabels[order.payment_method] ?? order.payment_method }}
                                </p>
                            </div>
                            <span class="shrink-0 font-semibold tabular-nums whitespace-nowrap">{{ formatCurrency(order.final_amount) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Close Shift Dialog -->
        <Dialog v-model:open="showCloseDialog">
            <DialogContent class="w-[95vw] max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5 shrink-0 text-destructive" />
                        Tutup Shift
                    </DialogTitle>
                    <DialogDescription>
                        Hitung uang tunai di laci kas, lalu masukkan jumlah aktual.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCloseShift">
                    <div>
                        <Label for="actual_cash">Kas Aktual (Rp)</Label>
                        <Input
                            id="actual_cash"
                            v-model="closeForm.actual_cash"
                            type="number"
                            min="0"
                            placeholder="Hitung jumlah uang tunai"
                            class="mt-1"
                            required
                        />
                        <p class="text-xs text-muted-foreground mt-1">
                            Hitung semua uang tunai yang ada di laci kas sekarang.
                        </p>
                    </div>
                    <div>
                        <Label for="close_notes">Catatan (opsional)</Label>
                        <Input
                            id="close_notes"
                            v-model="closeForm.notes"
                            placeholder="Catatan penutupan shift"
                            class="mt-1"
                        />
                    </div>
                    <DialogFooter class="flex-col gap-2 sm:flex-row">
                        <Button type="button" variant="outline" class="w-full sm:w-auto" @click="showCloseDialog = false">Batal</Button>
                        <Button type="submit" variant="destructive" class="w-full sm:w-auto" :disabled="closeProcessing">
                            {{ closeProcessing ? 'Menutup...' : 'Tutup Shift' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Order Detail Dialog -->
        <Dialog v-model:open="showOrderDialog">
            <DialogContent class="w-[95vw] max-w-md" v-if="selectedOrder">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ShoppingCart class="h-5 w-5 shrink-0" />
                        Detail Transaksi
                    </DialogTitle>
                    <DialogDescription class="break-words">
                        {{ selectedOrder.order_code }} · {{ selectedOrder.paid_at }} · {{ paymentMethodLabels[selectedOrder.payment_method] ?? selectedOrder.payment_method }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="rounded-lg border overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-muted">
                                <tr class="text-left font-medium">
                                    <th class="p-2">Item</th>
                                    <th class="p-2 text-center">Qty</th>
                                    <th class="p-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(item, idx) in selectedOrder.items" :key="idx">
                                    <td class="p-2 break-words">{{ item.product_name }}</td>
                                    <td class="p-2 text-center tabular-nums whitespace-nowrap">{{ item.quantity }} {{ item.unit }}</td>
                                    <td class="p-2 text-right tabular-nums whitespace-nowrap">{{ formatCurrency(item.subtotal) }}</td>
                                </tr>
                                <tr v-if="!selectedOrder.items.length">
                                    <td colspan="3" class="p-4 text-center text-muted-foreground">Belum ada item.</td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t bg-muted/30 font-bold">
                                <tr>
                                    <td colspan="2" class="p-2">TOTAL</td>
                                    <td class="p-2 text-right tabular-nums whitespace-nowrap">{{ formatCurrency(selectedOrder.final_amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="showOrderDialog = false">Tutup</Button>
                    <Button class="w-full sm:w-auto" @click="router.visit(`/admin/orders/${selectedOrder.id}`)">Buka Halaman Pesanan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
