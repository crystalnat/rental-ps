<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { formatCurrency } from '@/lib/utils'
import { ArrowLeft, RotateCcw, Package, AlertCircle } from 'lucide-vue-next'

interface OrderItemRow {
    id: number
    product_id: number
    product_name: string
    quantity: number
    unit: string
    unit_price: number
    discount_amount: number
    subtotal: number
    already_refunded: number
    max_refund: number
}

interface OrderData {
    id: number
    order_code: string
    final_amount: number
    payment_method: string
    store_name: string
    items: OrderItemRow[]
}

const props = defineProps<{
    order: OrderData
}>()

interface RefundItemForm {
    order_item_id: number
    product_name: string
    unit_price: number
    discount_amount: number
    max_refund: number
    quantity: number
    restock: boolean
    selected: boolean
}

const refundItems = ref<RefundItemForm[]>(
    props.order.items
        .filter(i => i.max_refund > 0)
        .map(i => ({
            order_item_id: i.id,
            product_name: i.product_name,
            unit_price: i.unit_price,
            discount_amount: i.discount_amount,
            max_refund: i.max_refund,
            quantity: i.max_refund,
            restock: true,
            selected: false,
        }))
)

const reason = ref('')
const refundMethod = ref('cash')
const processing = ref(false)

const selectedItems = computed(() => refundItems.value.filter(i => i.selected))

const totalRefund = computed(() =>
    selectedItems.value.reduce((sum, i) => {
        const effectivePrice = i.unit_price - i.discount_amount
        return sum + (effectivePrice * i.quantity)
    }, 0)
)

const canSubmit = computed(() => selectedItems.value.length > 0 && totalRefund.value > 0)

function toggleAll(checked: boolean) {
    refundItems.value.forEach(i => { i.selected = checked })
}

function submitRefund() {
    if (!canSubmit.value) return
    processing.value = true

    const items = selectedItems.value.map(i => ({
        order_item_id: i.order_item_id,
        quantity: i.quantity,
        restock: i.restock,
    }))

    router.post('/admin/refunds', {
        order_id: props.order.id,
        reason: reason.value || null,
        refund_method: refundMethod.value,
        items,
    }, {
        preserveScroll: true,
        onFinish: () => { processing.value = false },
    })
}

function goBack() {
    router.visit(`/admin/orders/${props.order.id}`)
}
</script>

<template>
    <AdminLayout :title="`Refund - ${order.order_code}`">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <RotateCcw class="h-5 w-5 text-primary" />
                        Proses Refund
                    </CardTitle>
                    <CardDescription>
                        Order {{ order.order_code }} · {{ order.store_name }} · Total Asli: {{ formatCurrency(order.final_amount) }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="refundItems.length === 0" class="py-12 text-center">
                        <AlertCircle class="mx-auto h-12 w-12 text-muted-foreground/50 mb-3" />
                        <p class="text-muted-foreground">Semua item order sudah di-refund.</p>
                        <Button variant="outline" class="mt-4" @click="goBack">Kembali</Button>
                    </div>

                    <div v-else class="space-y-6">
                        <!-- Select Items -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-sm">Pilih Item yang Di-refund</h3>
                                <Button variant="ghost" size="sm" @click="toggleAll(true)">Pilih Semua</Button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="item in refundItems"
                                    :key="item.order_item_id"
                                    :class="[
                                        'rounded-lg border p-4 transition-all',
                                        item.selected ? 'border-primary bg-primary/5' : 'hover:bg-muted/50'
                                    ]"
                                >
                                    <div class="flex items-start gap-3">
                                        <input
                                            v-model="item.selected"
                                            type="checkbox"
                                            class="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-col gap-1 mb-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                                                <span class="min-w-0 font-medium text-sm flex items-start gap-2">
                                                    <Package class="h-4 w-4 shrink-0 mt-0.5 text-muted-foreground" />
                                                    <span class="min-w-0 break-words">{{ item.product_name }}</span>
                                                </span>
                                                <span v-if="item.selected" class="font-semibold text-red-600 text-sm tabular-nums whitespace-nowrap sm:shrink-0">
                                                    -{{ formatCurrency((item.unit_price - item.discount_amount) * item.quantity) }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-muted-foreground break-words">
                                                Harga: {{ formatCurrency(item.unit_price) }}
                                                <span v-if="item.discount_amount > 0">
                                                    (diskon {{ formatCurrency(item.discount_amount) }}/pcs)
                                                </span>
                                                · Maks refund: {{ item.max_refund }}
                                            </p>

                                            <!-- Qty & Restock (shown when selected) -->
                                            <div v-if="item.selected" class="mt-3 flex flex-wrap items-center gap-4">
                                                <div class="flex items-center gap-2">
                                                    <Label class="text-xs">Qty:</Label>
                                                    <Input
                                                        v-model.number="item.quantity"
                                                        type="number"
                                                        :min="0.001"
                                                        :max="item.max_refund"
                                                        :step="1"
                                                        class="w-20 h-8 text-sm tabular-nums"
                                                    />
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <Switch
                                                        :checked="item.restock"
                                                        @update:checked="item.restock = $event"
                                                    />
                                                    <Label class="text-xs">Kembalikan ke stok</Label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reason & Method -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="reason">Alasan Refund</Label>
                                <Input
                                    id="reason"
                                    v-model="reason"
                                    placeholder="Contoh: Produk rusak, order salah, dll."
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="refund_method">Metode Pengembalian</Label>
                                <select
                                    id="refund_method"
                                    v-model="refundMethod"
                                    class="filter-select mt-1 flex h-9 w-full rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                                >
                                    <option value="cash">Tunai</option>
                                    <option value="original">Metode Pembayaran Asal</option>
                                </select>
                            </div>
                        </div>

                        <!-- Summary & Submit -->
                        <div class="flex flex-col gap-4 rounded-lg border-2 border-dashed p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm text-muted-foreground">Total Refund</p>
                                <p class="text-2xl font-bold text-red-600 tabular-nums whitespace-nowrap">
                                    -{{ formatCurrency(totalRefund) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ selectedItems.length }} item dipilih
                                </p>
                            </div>
                            <Button
                                :disabled="!canSubmit || processing"
                                variant="destructive"
                                size="lg"
                                class="w-full sm:w-auto"
                                @click="submitRefund"
                            >
                                <RotateCcw class="mr-2 h-4 w-4" />
                                {{ processing ? 'Memproses...' : 'Proses Refund' }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
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
    /* Panah digambar sebagai background, tanpa ruang ini teks panjang tertutup panah */
    padding-right: 2rem;
}
</style>
