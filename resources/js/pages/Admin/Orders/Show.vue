<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatCurrency } from '@/lib/utils'
import { Receipt, ArrowLeft, CreditCard, Printer, FileText, RotateCcw } from 'lucide-vue-next'

interface PaymentMethodItem {
    id: number
    name: string
    code: string
    requires_cash_input: boolean
}

interface OrderItem {
    product_name: string
    quantity: number
    unit: string
    unit_price: number
    subtotal: number
}

interface Order {
    id: number
    order_code: string
    type: string
    status: string
    payment_method: string
    payment_status: string
    subtotal: number
    discount_amount: number
    tax_amount: number
    final_amount: number
    cash_received: number | null
    change_amount: number | null
    notes: string | null
    table_name: string | null
    cashier_name: string | null
    customer_name: string | null
    customer_phone: string | null
    customer_email: string | null
    created_at: string
    paid_at: string | null
    store_name: string
    items: OrderItem[]
}

const props = defineProps<{
    order: Order
    payment_methods: PaymentMethodItem[]
}>()

const payForm = ref({
    payment_method: props.payment_methods[0]?.code ?? 'cash',
    cash_received: String(props.order.final_amount),
})
const payProcessing = ref(false)

const currentPaymentMethod = () =>
    props.payment_methods.find((pm) => pm.code === payForm.value.payment_method)
const requiresCashInput = () => currentPaymentMethod()?.requires_cash_input ?? payForm.value.payment_method === 'cash'

function submitPay() {
    payProcessing.value = true
    router.post(route('admin.orders.pay', props.order.id), {
        payment_method: payForm.value.payment_method,
        cash_received: requiresCashInput() ? payForm.value.cash_received : null,
    }, {
        preserveScroll: true,
        onFinish: () => { payProcessing.value = false },
    })
}

const typeLabels: Record<string, string> = {
    dine_in: 'Dine In',
    takeaway: 'Take Away',
    walk_in: 'Walk In',
}

function goBack() {
    router.visit(route('admin.orders.index'))
}
</script>

<template>
    <AdminLayout :title="`Order ${order.order_code}`">
        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <Button variant="ghost" size="sm" class="self-start" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>
                <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center">
                    <a :href="route('admin.orders.receipt', order.id)" target="_blank" class="w-full sm:w-auto">
                        <Button variant="outline" size="sm" class="w-full sm:w-auto">
                            <Printer class="mr-2 h-4 w-4" />
                            Cetak Struk
                        </Button>
                    </a>
                    <a :href="route('admin.orders.invoice', order.id)" target="_blank" class="w-full sm:w-auto">
                        <Button variant="outline" size="sm" class="w-full sm:w-auto">
                            <FileText class="mr-2 h-4 w-4" />
                            Cetak Faktur
                        </Button>
                    </a>
                    <a v-if="order.payment_status === 'paid'" :href="`/admin/refunds/create/${order.id}`" class="w-full sm:w-auto">
                        <Button variant="outline" size="sm" class="w-full border-destructive/30 text-destructive hover:bg-destructive/10 sm:w-auto">
                            <RotateCcw class="mr-2 h-4 w-4" />
                            Refund
                        </Button>
                    </a>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex flex-wrap items-start justify-between gap-3 sm:items-center sm:gap-4">
                        <div class="min-w-0">
                            <CardTitle class="flex items-center gap-2 break-words">
                                <Receipt class="h-5 w-5 shrink-0" />
                                {{ order.order_code }}
                            </CardTitle>
                            <CardDescription class="break-words">
                                {{ order.store_name }} · {{ order.created_at }}
                            </CardDescription>
                        </div>
                        <Badge variant="outline" class="shrink-0 whitespace-nowrap">{{ typeLabels[order.type] ?? order.type }}</Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="min-w-0">
                            <p class="text-sm text-muted-foreground">Kasir</p>
                            <p class="font-medium break-words">{{ order.cashier_name ?? '—' }}</p>
                        </div>
                        <div v-if="order.table_name" class="min-w-0">
                            <p class="text-sm text-muted-foreground">Meja</p>
                            <p class="font-medium break-words">{{ order.table_name }}</p>
                        </div>
                        <div v-if="order.customer_name" class="min-w-0">
                            <p class="text-sm text-muted-foreground">Pelanggan</p>
                            <p class="font-medium break-words">{{ order.customer_name }}</p>
                            <p v-if="order.customer_phone || order.customer_email" class="mt-0.5 text-xs text-muted-foreground break-words">
                                {{ [order.customer_phone, order.customer_email].filter(Boolean).join(' · ') }}
                            </p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-muted-foreground">Metode Pembayaran</p>
                            <p class="font-medium capitalize break-words">{{ order.payment_method }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-muted-foreground">Waktu Bayar</p>
                            <p class="font-medium break-words">{{ order.paid_at ?? '—' }}</p>
                        </div>
                    </div>

                    <!-- Items -->
                    <div>
                        <h4 class="mb-3 font-medium">Detail Pesanan</h4>
                        <!-- Kartu vertikal untuk layar sempit -->
                        <div class="space-y-2 md:hidden">
                            <div
                                v-for="(item, i) in order.items"
                                :key="`card-${i}`"
                                class="rounded-lg border p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <p class="min-w-0 flex-1 text-sm font-medium break-words">{{ item.product_name }}</p>
                                    <p class="shrink-0 text-sm font-semibold tabular-nums whitespace-nowrap">
                                        {{ formatCurrency(item.subtotal) }}
                                    </p>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground tabular-nums">
                                    {{ item.quantity }} {{ item.unit }} × {{ formatCurrency(item.unit_price) }}
                                </p>
                            </div>
                            <p v-if="!order.items.length" class="rounded-lg border px-4 py-8 text-center text-sm text-muted-foreground">
                                Belum ada item.
                            </p>
                        </div>

                        <div class="hidden rounded-lg border md:block">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/50 text-left">
                                        <th class="px-4 py-2 font-medium">Produk</th>
                                        <th class="px-4 py-2 text-center font-medium">Qty</th>
                                        <th class="px-4 py-2 text-right font-medium">Harga</th>
                                        <th class="px-4 py-2 text-right font-medium">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, i) in order.items"
                                        :key="i"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-4 py-3 break-words">{{ item.product_name }}</td>
                                        <td class="px-4 py-3 text-center tabular-nums whitespace-nowrap">{{ item.quantity }} {{ item.unit }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="px-4 py-3 text-right font-medium tabular-nums whitespace-nowrap">{{ formatCurrency(item.subtotal) }}</td>
                                    </tr>
                                    <tr v-if="!order.items.length">
                                        <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                                            Belum ada item.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="rounded-lg border p-4">
                        <div class="space-y-2">
                            <div class="flex justify-between gap-3 text-sm">
                                <span class="text-muted-foreground">Subtotal</span>
                                <span class="tabular-nums whitespace-nowrap">{{ formatCurrency(order.subtotal) }}</span>
                            </div>
                            <div v-if="order.discount_amount > 0" class="flex justify-between gap-3 text-sm">
                                <span class="text-muted-foreground">Diskon</span>
                                <span class="tabular-nums whitespace-nowrap">-{{ formatCurrency(order.discount_amount) }}</span>
                            </div>
                            <div v-if="order.tax_amount > 0" class="flex justify-between gap-3 text-sm">
                                <span class="text-muted-foreground">Pajak</span>
                                <span class="tabular-nums whitespace-nowrap">{{ formatCurrency(order.tax_amount) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 border-t pt-2 font-semibold">
                                <span>Total</span>
                                <span class="tabular-nums whitespace-nowrap">{{ formatCurrency(order.final_amount) }}</span>
                            </div>
                            <div v-if="order.cash_received" class="flex justify-between gap-3 text-sm">
                                <span class="text-muted-foreground">Tunai diterima</span>
                                <span class="tabular-nums whitespace-nowrap">{{ formatCurrency(order.cash_received) }}</span>
                            </div>
                            <div v-if="order.change_amount" class="flex justify-between gap-3 text-sm">
                                <span class="text-muted-foreground">Kembalian</span>
                                <span class="tabular-nums whitespace-nowrap">{{ formatCurrency(order.change_amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <p v-if="order.notes" class="text-sm text-muted-foreground break-words">
                        <span class="font-medium">Catatan:</span> {{ order.notes }}
                    </p>

                    <!-- Terima Pembayaran (jika belum bayar) -->
                    <div
                        v-if="order.payment_status === 'unpaid' && payment_methods.length > 0"
                        class="rounded-lg border-2 border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/50 sm:p-5"
                    >
                        <h4 class="mb-3 flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-200">
                            <CreditCard class="h-5 w-5" />
                            Terima Pembayaran
                        </h4>
                        <form class="space-y-3" @submit.prevent="submitPay">
                            <div>
                                <Label>Metode Pembayaran</Label>
                                <select
                                    v-model="payForm.payment_method"
                                    class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option
                                        v-for="pm in payment_methods"
                                        :key="pm.id"
                                        :value="pm.code"
                                    >
                                        {{ pm.name }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="requiresCashInput()">
                                <Label for="cash_received">Uang Diterima</Label>
                                <Input
                                    id="cash_received"
                                    v-model="payForm.cash_received"
                                    type="number"
                                    min="0"
                                    step="100"
                                    class="mt-1.5"
                                    :placeholder="formatCurrency(order.final_amount)"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Total: {{ formatCurrency(order.final_amount) }}
                                </p>
                            </div>
                            <Button type="submit" class="w-full sm:w-auto" :disabled="payProcessing">
                                {{ payProcessing ? 'Memproses...' : 'Konfirmasi Pembayaran' }}
                            </Button>
                        </form>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
