<script setup lang="ts">
import { onMounted } from 'vue'

interface OrderItem {
    product_name: string
    quantity: number
    unit: string
    unit_price: number
    discount_amount: number
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
    created_at: string
    paid_at: string | null
    items: OrderItem[]
}

interface StoreInfo {
    name: string
    address: string | null
    city: string | null
    phone: string | null
    email: string | null
    logo: string | null
}

const props = defineProps<{
    order: Order
    store: StoreInfo
}>()

function formatRp(amount: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)
}

const typeLabels: Record<string, string> = {
    dine_in: 'Dine In',
    takeaway: 'Take Away',
    walk_in: 'Walk In',
}

onMounted(() => {
    setTimeout(() => window.print(), 400)
})
</script>

<template>
    <div class="receipt-page">
        <div class="receipt">
            <!-- Header -->
            <div class="receipt-header">
                <img v-if="store.logo" :src="store.logo" alt="Logo" class="receipt-logo" />
                <h1 class="store-name">{{ store.name }}</h1>
                <p v-if="store.address" class="store-info">{{ store.address }}</p>
                <p v-if="store.city" class="store-info">{{ store.city }}</p>
                <p v-if="store.phone" class="store-info">Telp: {{ store.phone }}</p>
            </div>

            <div class="divider" />

            <!-- Order Info -->
            <div class="order-info">
                <div class="info-row">
                    <span>No. Order</span>
                    <span class="mono">{{ order.order_code }}</span>
                </div>
                <div class="info-row">
                    <span>Tanggal</span>
                    <span>{{ order.created_at }}</span>
                </div>
                <div v-if="order.cashier_name" class="info-row">
                    <span>Kasir</span>
                    <span>{{ order.cashier_name }}</span>
                </div>
                <div class="info-row">
                    <span>Tipe</span>
                    <span>{{ typeLabels[order.type] ?? order.type }}</span>
                </div>
                <div v-if="order.table_name" class="info-row">
                    <span>Meja</span>
                    <span>{{ order.table_name }}</span>
                </div>
                <div v-if="order.customer_name" class="info-row">
                    <span>Pelanggan</span>
                    <span>{{ order.customer_name }}</span>
                </div>
            </div>

            <div class="divider" />

            <!-- Items -->
            <div class="items">
                <div v-for="(item, i) in order.items" :key="i" class="item">
                    <div class="item-name">{{ item.product_name }}</div>
                    <div class="item-detail">
                        <span>{{ item.quantity }} {{ item.unit }} × {{ formatRp(item.unit_price) }}</span>
                        <span class="item-subtotal">{{ formatRp(item.subtotal) }}</span>
                    </div>
                    <div v-if="item.discount_amount > 0" class="item-discount">
                        Diskon: -{{ formatRp(item.discount_amount * item.quantity) }}
                    </div>
                </div>
            </div>

            <div class="divider" />

            <!-- Summary -->
            <div class="summary">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>{{ formatRp(order.subtotal) }}</span>
                </div>
                <div v-if="order.discount_amount > 0" class="summary-row">
                    <span>Diskon</span>
                    <span>-{{ formatRp(order.discount_amount) }}</span>
                </div>
                <div v-if="order.tax_amount > 0" class="summary-row">
                    <span>Pajak</span>
                    <span>{{ formatRp(order.tax_amount) }}</span>
                </div>
                <div class="divider-dashed" />
                <div class="summary-row total">
                    <span>TOTAL</span>
                    <span>{{ formatRp(order.final_amount) }}</span>
                </div>
                <div v-if="order.payment_method" class="summary-row">
                    <span>Bayar ({{ order.payment_method.replace(/_/g, ' ') }})</span>
                    <span>{{ formatRp(order.cash_received ?? order.final_amount) }}</span>
                </div>
                <div v-if="order.change_amount && order.change_amount > 0" class="summary-row">
                    <span>Kembalian</span>
                    <span>{{ formatRp(order.change_amount) }}</span>
                </div>
            </div>

            <div class="divider" />

            <!-- Footer -->
            <div class="receipt-footer">
                <p v-if="order.notes" class="notes">Catatan: {{ order.notes }}</p>
                <p class="thank-you">Terima Kasih</p>
                <p class="thank-you-sub">Selamat Menikmati ✨</p>
                <p v-if="order.paid_at" class="paid-at">Dibayar: {{ order.paid_at }}</p>
            </div>
        </div>

        <!-- No-print: Back button -->
        <div class="no-print-actions">
            <button class="btn-back" @click="() => { window.history.back() }">
                ← Kembali
            </button>
            <button class="btn-print" @click="() => { window.print() }">
                🖨️ Cetak Ulang
            </button>
        </div>
    </div>
</template>

<style>
/* Reset for receipt */
.receipt-page {
    margin: 0;
    padding: 0;
    background: #f5f5f5;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: 'Courier New', 'Consolas', monospace;
}

.receipt {
    width: 80mm;
    max-width: 100vw;
    background: white;
    padding: 10mm 5mm;
    margin: 20px auto;
    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
    font-size: 12px;
    line-height: 1.4;
    color: #000;
}

.receipt-header {
    text-align: center;
    margin-bottom: 4px;
}

.receipt-logo {
    width: 48px;
    height: 48px;
    object-fit: contain;
    margin: 0 auto 6px;
    display: block;
}

.store-name {
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 2px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.store-info {
    font-size: 10px;
    margin: 0;
    color: #333;
}

.divider {
    border-top: 1px solid #000;
    margin: 8px 0;
}

.divider-dashed {
    border-top: 1px dashed #666;
    margin: 6px 0;
}

.order-info {
    font-size: 11px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin: 2px 0;
}

.mono {
    font-weight: 700;
}

.items {
    font-size: 11px;
}

.item {
    margin-bottom: 6px;
}

.item-name {
    font-weight: 600;
}

.item-detail {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: #333;
}

.item-subtotal {
    font-weight: 600;
}

.item-discount {
    font-size: 9px;
    color: #888;
    font-style: italic;
}

.summary {
    font-size: 11px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin: 2px 0;
}

.summary-row.total {
    font-size: 14px;
    font-weight: 700;
    margin: 4px 0;
}

.receipt-footer {
    text-align: center;
    margin-top: 4px;
}

.notes {
    font-size: 9px;
    color: #666;
    margin-bottom: 6px;
    font-style: italic;
}

.thank-you {
    font-size: 14px;
    font-weight: 700;
    margin: 6px 0 2px;
}

.thank-you-sub {
    font-size: 10px;
    color: #666;
    margin: 0;
}

.paid-at {
    font-size: 9px;
    color: #888;
    margin-top: 6px;
}

.no-print-actions {
    display: flex;
    gap: 12px;
    margin: 16px auto 40px;
}

.btn-back, .btn-print {
    padding: 10px 24px;
    border-radius: 8px;
    border: 1px solid #ddd;
    font-size: 14px;
    cursor: pointer;
    background: white;
    transition: all 0.15s;
}

.btn-back:hover { background: #f0f0f0; }

.btn-print {
    background: #18181b;
    color: white;
    border-color: #18181b;
}
.btn-print:hover { background: #27272a; }

/* Print styles */
@media print {
    @page {
        size: 80mm auto;
        margin: 0;
    }

    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .receipt-page {
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .receipt {
        box-shadow: none !important;
        margin: 0 !important;
        padding: 4mm 3mm !important;
        width: 80mm !important;
    }

    .no-print-actions {
        display: none !important;
    }
}
</style>
