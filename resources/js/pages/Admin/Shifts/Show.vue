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
import { ArrowLeft, Clock, DollarSign, ShoppingCart, TrendingUp, TrendingDown, AlertCircle, Wallet, CreditCard } from 'lucide-vue-next'

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

const props = defineProps<{
    shift: ShiftDetail
    orders: ShiftOrder[]
    payment_breakdown: PaymentBreakdown[]
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
            <div class="flex items-center justify-between gap-4">
                <Button variant="ghost" size="sm" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>
                <Button v-if="shift.status === 'open'" variant="destructive" @click="openCloseDialog">
                    <Clock class="mr-2 h-4 w-4" />
                    Tutup Shift
                </Button>
            </div>

            <!-- Shift Info -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Clock class="h-5 w-5 text-primary" />
                            Detail Shift
                        </CardTitle>
                        <Badge :variant="shift.status === 'open' ? 'default' : 'secondary'" class="text-sm">
                            {{ shift.status === 'open' ? '🟢 Masih Buka' : '🔴 Sudah Ditutup' }}
                        </Badge>
                    </div>
                    <CardDescription>
                        {{ shift.store_name }} · {{ shift.user_name }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="rounded-lg border p-4">
                            <p class="text-xs text-muted-foreground mb-1">Buka</p>
                            <p class="font-semibold text-sm">{{ shift.opened_at }}</p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-xs text-muted-foreground mb-1">Tutup</p>
                            <p class="font-semibold text-sm">{{ shift.closed_at ?? '— (Masih buka)' }}</p>
                        </div>
                        <div v-if="shift.scheduled_start" class="rounded-lg border p-4">
                            <p class="text-xs text-muted-foreground mb-1">Jadwal Shift</p>
                            <p class="font-semibold text-sm">{{ shift.scheduled_start }} — {{ shift.scheduled_end }}</p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-xs text-muted-foreground mb-1">Kas Awal</p>
                            <p class="font-semibold text-sm">{{ formatCurrency(shift.opening_cash) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Financial Summary (only if closed) -->
            <div v-if="shift.status === 'closed'" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                                <TrendingUp class="h-5 w-5 text-green-600" />
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Total Penjualan</p>
                                <p class="text-lg font-bold">{{ formatCurrency(shift.total_sales ?? 0) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                                <ShoppingCart class="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Total Order</p>
                                <p class="text-lg font-bold">{{ shift.total_orders ?? 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-orange-100 p-2 dark:bg-orange-900/30">
                                <TrendingDown class="h-5 w-5 text-orange-600" />
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Total Pengeluaran</p>
                                <p class="text-lg font-bold">{{ formatCurrency(shift.total_expenses ?? 0) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="rounded-lg p-2"
                                :class="(shift.cash_difference ?? 0) >= 0
                                    ? 'bg-green-100 dark:bg-green-900/30'
                                    : 'bg-red-100 dark:bg-red-900/30'"
                            >
                                <AlertCircle
                                    class="h-5 w-5"
                                    :class="(shift.cash_difference ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'"
                                />
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Selisih Kas</p>
                                <p
                                    class="text-lg font-bold"
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">Kas Awal</span>
                                <span class="font-medium">{{ formatCurrency(shift.opening_cash) }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">+ Penjualan Tunai</span>
                                <span class="font-medium text-green-600">{{ formatCurrency(shift.total_cash_sales ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">− Pengeluaran</span>
                                <span class="font-medium text-red-600">-{{ formatCurrency(shift.total_expenses ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b-2 border-foreground font-bold text-base">
                                <span>Kas Seharusnya</span>
                                <span>{{ formatCurrency(shift.expected_cash ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-muted-foreground">Kas Aktual (dihitung)</span>
                                <span class="font-bold text-lg">{{ formatCurrency(shift.actual_cash ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between py-2 rounded-lg bg-muted/50 px-3">
                                <span class="font-medium">Selisih</span>
                                <span
                                    class="font-bold text-lg"
                                    :class="(shift.cash_difference ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'"
                                >
                                    {{ (shift.cash_difference ?? 0) >= 0 ? '+' : '' }}{{ formatCurrency(shift.cash_difference ?? 0) }}
                                </span>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h4 class="font-medium text-sm text-muted-foreground mb-2">Penjualan Non-Tunai</h4>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-muted-foreground">Total Non-Tunai</span>
                                <span class="font-medium">{{ formatCurrency(shift.total_non_cash_sales ?? 0) }}</span>
                            </div>
                            <h4 class="font-medium text-sm text-muted-foreground mt-4 mb-2">Per Metode Pembayaran</h4>
                            <div
                                v-for="pb in payment_breakdown"
                                :key="pb.method"
                                class="flex justify-between py-2 border-b"
                            >
                                <span class="flex items-center gap-2">
                                    <CreditCard class="h-4 w-4 text-muted-foreground" />
                                    {{ paymentMethodLabels[pb.method] ?? pb.method }}
                                    <Badge variant="secondary" class="text-xs">{{ pb.count }}×</Badge>
                                </span>
                                <span class="font-medium">{{ formatCurrency(pb.total) }}</span>
                            </div>
                            <div v-if="shift.notes" class="mt-4 rounded-lg bg-muted/50 p-3">
                                <p class="text-xs text-muted-foreground mb-1">Catatan</p>
                                <p class="text-sm">{{ shift.notes }}</p>
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
                            class="flex items-center justify-between rounded-lg border p-3 cursor-pointer hover:bg-muted/50 transition-colors"
                            @click="viewOrder(order)"
                        >
                            <div>
                                <p class="font-mono text-sm font-medium">{{ order.order_code }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ order.paid_at }} · {{ paymentMethodLabels[order.payment_method] ?? order.payment_method }}
                                </p>
                            </div>
                            <span class="font-semibold">{{ formatCurrency(order.final_amount) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Close Shift Dialog -->
        <Dialog v-model:open="showCloseDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5 text-destructive" />
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
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCloseDialog = false">Batal</Button>
                        <Button type="submit" variant="destructive" :disabled="closeProcessing">
                            {{ closeProcessing ? 'Menutup...' : 'Tutup Shift' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Order Detail Dialog -->
        <Dialog v-model:open="showOrderDialog">
            <DialogContent class="max-w-md" v-if="selectedOrder">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ShoppingCart class="h-5 w-5" />
                        Detail Transaksi
                    </DialogTitle>
                    <DialogDescription>
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
                                    <td class="p-2">{{ item.product_name }}</td>
                                    <td class="p-2 text-center">{{ item.quantity }} {{ item.unit }}</td>
                                    <td class="p-2 text-right">{{ formatCurrency(item.subtotal) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-muted/30 font-bold border-t">
                                <tr>
                                    <td colspan="2" class="p-2">TOTAL</td>
                                    <td class="p-2 text-right">{{ formatCurrency(selectedOrder.final_amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showOrderDialog = false">Tutup</Button>
                    <Button @click="router.visit(`/admin/orders/${selectedOrder.id}`)">Buka Halaman Pesanan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
