<script setup lang="ts">
import { onMounted } from 'vue'

interface OrderItem {
    product_name: string
    quantity: number
    unit: string
    unit_price: number
    discount_amount: number
    subtotal: number
    modifiers?: Array<{
        name: string
        price_extra: number
    }>
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
    is_rental: boolean
    rental_duration_minutes: number | null
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

const itemsTotal = props.order.items.reduce((sum, i) => sum + i.subtotal, 0)
const rentalFee = props.order.is_rental ? Math.max(0, props.order.subtotal - itemsTotal) : 0

function formatDuration(minutes: number | null): string {
    if (!minutes) return ''
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return h > 0 ? `${h}j ${m}m` : `${m}m`
}

const typeLabels: Record<string, string> = {
    dine_in: 'Dine In',
    takeaway: 'Take Away',
    walk_in: 'Walk In',
}

// Cetak ditunda sampai logo selesai dimuat, kalau tidak logo keluar kosong di struk
function waitForImages(): Promise<unknown> {
    const images = Array.from(document.querySelectorAll('img'))
    return Promise.all(images.map(img => img.complete
        ? Promise.resolve()
        : new Promise(resolve => {
            img.addEventListener('load', resolve, { once: true })
            img.addEventListener('error', resolve, { once: true })
        })))
}

onMounted(() => {
    setTimeout(() => { waitForImages().then(() => window.print()) }, 400)
})

// window bukan global yang dikenali template Vue, pemanggilan harus lewat script
function goBack() {
    window.history.back()
}

function reprint() {
    window.print()
}
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
                <!-- Biaya Rental -->
                <div v-if="rentalFee > 0" class="item">
                    <div class="item-name">Biaya Rental{{ order.table_name ? ' - ' + order.table_name : '' }}</div>
                    <div class="item-detail">
                        <span v-if="order.rental_duration_minutes">Durasi: {{ formatDuration(order.rental_duration_minutes) }}</span>
                        <span v-else></span>
                        <span class="item-subtotal">{{ formatRp(rentalFee) }}</span>
                    </div>
                </div>
                <div v-for="(item, i) in order.items" :key="i" class="item">
                    <div class="item-name">{{ item.product_name }}</div>
                    <div v-if="item.modifiers && item.modifiers.length > 0" class="item-modifiers">
                        <div v-for="m in item.modifiers" :key="m.name" class="modifier-row">
                            + {{ m.name }}
                        </div>
                    </div>
                    <div class="item-detail">
                        <span>{{ item.quantity }} {{ item.unit }} x {{ formatRp(item.unit_price) }}</span>
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
                <p class="thank-you-sub">Selamat Menikmati</p>
                <p v-if="order.paid_at" class="paid-at">Dibayar: {{ order.paid_at }}</p>
            </div>
        </div>

        <!-- No-print: Back button -->
        <div class="no-print-actions">
            <button class="btn-back" type="button" @click="goBack">
                Kembali
            </button>
            <button class="btn-print" type="button" @click="reprint">
                Cetak Ulang
            </button>
        </div>
    </div>
</template>

<style>
/* Struk 80mm.
   Font sistem saja, dokumen cetak tidak boleh bergantung pada unduhan font.
   Serif untuk nama toko, sans untuk isi dan angka. */
.receipt-page {
    --font-heading: 'Georgia', 'Cambria', 'Times New Roman', serif;
    --font-body: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
    margin: 0;
    padding: 0;
    background: #f5f5f5;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: var(--font-body);
    font-variant-numeric: tabular-nums;
    -webkit-font-smoothing: antialiased;
}

/* Nominal pada struk wajib rata kolom */
.receipt, .receipt div, .receipt span, .receipt p { font-variant-numeric: tabular-nums; }

/* box-sizing wajib border-box: tanpa ini width 80mm + padding melebihi lebar
   kertas dan sisi kanan struk terpotong printer */
.receipt {
    box-sizing: border-box;
    width: 80mm;
    max-width: 100vw;
    background: white;
    padding: 8mm 5mm;
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
    font-family: var(--font-heading);
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 2px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.store-info {
    font-size: 10px;
    margin: 0;
    color: #1f2937;
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
    font-weight: 500;
}
.item-modifiers {
    font-size: 9px;
    color: #374151;
    padding-left: 10px;
    margin: 1px 0 2px;
}
.modifier-row {
    line-height: 1.2;
}

.item-detail {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: #1f2937;
}

.item-subtotal {
    font-weight: 500;
}

.item-discount {
    font-size: 9px;
    color: #4b5563;
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
    color: #4b5563;
    margin-bottom: 6px;
    font-style: italic;
}

.thank-you {
    font-family: var(--font-heading);
    font-size: 14px;
    font-weight: 700;
    margin: 6px 0 2px;
}

.thank-you-sub {
    font-size: 10px;
    color: #4b5563;
    margin: 0;
}

.paid-at {
    font-size: 9px;
    color: #4b5563;
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

    /* box-sizing border-box supaya 80mm sudah termasuk padding, tidak terpotong */
    .receipt {
        box-sizing: border-box !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 4mm 3mm !important;
        width: 80mm !important;
        max-width: 80mm !important;
    }

    .no-print-actions {
        display: none !important;
    }

    /* Struk gulungan biasanya satu halaman, tapi kalau item banyak jangan
       memotong di tengah satu item atau di tengah blok ringkasan */
    .item,
    .info-row,
    .summary-row,
    .summary,
    .receipt-header,
    .receipt-footer { page-break-inside: avoid; }

    /* Garis pemisah dan teks abu tetap tercetak */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
