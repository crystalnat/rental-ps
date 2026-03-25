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
import { Receipt, ArrowLeft, CreditCard } from 'lucide-vue-next'

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
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Receipt class="h-5 w-5" />
                                {{ order.order_code }}
                            </CardTitle>
                            <CardDescription>
                                {{ order.store_name }} · {{ order.created_at }}
                            </CardDescription>
                        </div>
                        <Badge variant="outline">{{ typeLabels[order.type] ?? order.type }}</Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-sm text-muted-foreground">Kasir</p>
                            <p class="font-medium">{{ order.cashier_name ?? '—' }}</p>
                        </div>
                        <div v-if="order.table_name">
                            <p class="text-sm text-muted-foreground">Meja</p>
                            <p class="font-medium">{{ order.table_name }}</p>
                        </div>
                        <div v-if="order.customer_name">
                            <p class="text-sm text-muted-foreground">Pelanggan</p>
                            <p class="font-medium">{{ order.customer_name }}</p>
                            <p v-if="order.customer_phone || order.customer_email" class="text-xs text-muted-foreground mt-0.5">
                                {{ [order.customer_phone, order.customer_email].filter(Boolean).join(' · ') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Metode Pembayaran</p>
                            <p class="font-medium capitalize">{{ order.payment_method }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Waktu Bayar</p>
                            <p class="font-medium">{{ order.paid_at ?? '—' }}</p>
                        </div>
                    </div>

                    <!-- Items -->
                    <div>
                        <h4 class="mb-3 font-medium">Detail Pesanan</h4>
                        <div class="rounded-lg border">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/50 text-left">
                                        <th class="px-4 py-2 font-medium">Produk</th>
                                        <th class="px-4 py-2 font-medium text-center">Qty</th>
                                        <th class="px-4 py-2 font-medium text-right">Harga</th>
                                        <th class="px-4 py-2 font-medium text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, i) in order.items"
                                        :key="i"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-4 py-3">{{ item.product_name }}</td>
                                        <td class="px-4 py-3 text-center">{{ item.quantity }} {{ item.unit }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(item.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="rounded-lg border p-4">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Subtotal</span>
                                <span>{{ formatCurrency(order.subtotal) }}</span>
                            </div>
                            <div v-if="order.discount_amount > 0" class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Diskon</span>
                                <span>-{{ formatCurrency(order.discount_amount) }}</span>
                            </div>
                            <div v-if="order.tax_amount > 0" class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Pajak</span>
                                <span>{{ formatCurrency(order.tax_amount) }}</span>
                            </div>
                            <div class="flex justify-between border-t pt-2 font-semibold">
                                <span>Total</span>
                                <span>{{ formatCurrency(order.final_amount) }}</span>
                            </div>
                            <div v-if="order.cash_received" class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Tunai diterima</span>
                                <span>{{ formatCurrency(order.cash_received) }}</span>
                            </div>
                            <div v-if="order.change_amount" class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Kembalian</span>
                                <span>{{ formatCurrency(order.change_amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <p v-if="order.notes" class="text-sm text-muted-foreground">
                        <span class="font-medium">Catatan:</span> {{ order.notes }}
                    </p>

                    <!-- Terima Pembayaran (jika belum bayar) -->
                    <div
                        v-if="order.payment_status === 'unpaid' && payment_methods.length > 0"
                        class="rounded-lg border-2 border-amber-200 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/50"
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
                            <Button type="submit" :disabled="payProcessing">
                                {{ payProcessing ? 'Memproses...' : 'Konfirmasi Pembayaran' }}
                            </Button>
                        </form>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
