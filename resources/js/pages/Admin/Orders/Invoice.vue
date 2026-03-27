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
    tax_rate: number
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

const paymentLabels: Record<string, string> = {
    cash: 'Tunai',
    qris: 'QRIS',
    bank_transfer: 'Transfer Bank',
    e_wallet: 'E-Wallet',
}

onMounted(() => {
    setTimeout(() => window.print(), 500)
})
</script>

<template>
    <div class="invoice-page">
        <div class="invoice">
            <!-- Header -->
            <div class="invoice-header">
                <div class="header-left">
                    <img v-if="store.logo" :src="store.logo" alt="Logo" class="invoice-logo" />
                    <div>
                        <h1 class="company-name">{{ store.name }}</h1>
                        <p v-if="store.address" class="company-info">{{ store.address }}</p>
                        <p v-if="store.city" class="company-info">{{ store.city }}</p>
                        <p v-if="store.phone" class="company-info">Telp: {{ store.phone }}</p>
                        <p v-if="store.email" class="company-info">Email: {{ store.email }}</p>
                    </div>
                </div>
                <div class="header-right">
                    <h2 class="invoice-title">FAKTUR</h2>
                    <div class="invoice-meta">
                        <div class="meta-row">
                            <span class="meta-label">No. Faktur</span>
                            <span class="meta-value">{{ order.order_code }}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Tanggal</span>
                            <span class="meta-value">{{ order.created_at }}</span>
                        </div>
                        <div v-if="order.paid_at" class="meta-row">
                            <span class="meta-label">Dibayar</span>
                            <span class="meta-value">{{ order.paid_at }}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Status</span>
                            <span class="meta-value" :class="order.payment_status === 'paid' ? 'status-paid' : 'status-unpaid'">
                                {{ order.payment_status === 'paid' ? 'LUNAS' : 'BELUM BAYAR' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="divider" />

            <!-- Customer & Order Info -->
            <div class="info-section">
                <div class="info-col">
                    <h3 class="section-label">Informasi Pesanan</h3>
                    <div class="info-grid">
                        <span class="label">Tipe</span>
                        <span>{{ typeLabels[order.type] ?? order.type }}</span>
                        <span v-if="order.table_name" class="label">Meja</span>
                        <span v-if="order.table_name">{{ order.table_name }}</span>
                        <span v-if="order.cashier_name" class="label">Kasir</span>
                        <span v-if="order.cashier_name">{{ order.cashier_name }}</span>
                    </div>
                </div>
                <div v-if="order.customer_name" class="info-col">
                    <h3 class="section-label">Pelanggan</h3>
                    <div class="info-grid">
                        <span class="label">Nama</span>
                        <span>{{ order.customer_name }}</span>
                        <span v-if="order.customer_phone" class="label">Telepon</span>
                        <span v-if="order.customer_phone">{{ order.customer_phone }}</span>
                        <span v-if="order.customer_email" class="label">Email</span>
                        <span v-if="order.customer_email">{{ order.customer_email }}</span>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-product">Produk</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-unit">Satuan</th>
                        <th class="col-price">Harga Satuan</th>
                        <th v-if="order.items.some(i => i.discount_amount > 0)" class="col-discount">Diskon</th>
                        <th class="col-subtotal">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, i) in order.items" :key="i">
                        <td class="col-no">{{ i + 1 }}</td>
                        <td class="col-product">{{ item.product_name }}</td>
                        <td class="col-qty">{{ item.quantity }}</td>
                        <td class="col-unit">{{ item.unit }}</td>
                        <td class="col-price">{{ formatRp(item.unit_price) }}</td>
                        <td v-if="order.items.some(i => i.discount_amount > 0)" class="col-discount">
                            {{ item.discount_amount > 0 ? formatRp(item.discount_amount * item.quantity) : '—' }}
                        </td>
                        <td class="col-subtotal">{{ formatRp(item.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Summary -->
            <div class="summary-section">
                <div class="summary-box">
                    <div class="sum-row">
                        <span>Subtotal</span>
                        <span>{{ formatRp(order.subtotal) }}</span>
                    </div>
                    <div v-if="order.discount_amount > 0" class="sum-row">
                        <span>Diskon Transaksi</span>
                        <span class="text-red">-{{ formatRp(order.discount_amount) }}</span>
                    </div>
                    <div v-if="order.tax_amount > 0" class="sum-row">
                        <span>Pajak ({{ order.tax_rate }}%)</span>
                        <span>{{ formatRp(order.tax_amount) }}</span>
                    </div>
                    <div class="sum-row sum-total">
                        <span>Total</span>
                        <span>{{ formatRp(order.final_amount) }}</span>
                    </div>
                    <div v-if="order.payment_method" class="sum-row">
                        <span>Metode Bayar</span>
                        <span>{{ paymentLabels[order.payment_method] ?? order.payment_method.replace(/_/g, ' ') }}</span>
                    </div>
                    <div v-if="order.cash_received" class="sum-row">
                        <span>Tunai Diterima</span>
                        <span>{{ formatRp(order.cash_received) }}</span>
                    </div>
                    <div v-if="order.change_amount && order.change_amount > 0" class="sum-row">
                        <span>Kembalian</span>
                        <span>{{ formatRp(order.change_amount) }}</span>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <p v-if="order.notes" class="order-notes">
                <strong>Catatan:</strong> {{ order.notes }}
            </p>

            <!-- Footer -->
            <div class="invoice-footer">
                <div class="signature-area">
                    <div class="sig-col">
                        <div class="sig-line" />
                        <p>Kasir</p>
                        <p class="sig-name">{{ order.cashier_name ?? '_______________' }}</p>
                    </div>
                    <div class="sig-col">
                        <div class="sig-line" />
                        <p>Pelanggan</p>
                        <p class="sig-name">{{ order.customer_name ?? '_______________' }}</p>
                    </div>
                </div>
                <p class="footer-note">Dokumen ini dicetak secara otomatis dan sah tanpa tanda tangan basah.</p>
            </div>
        </div>

        <!-- No-print: Actions -->
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
/* Invoice — A4 formal */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

.invoice-page {
    margin: 0;
    padding: 0;
    background: #e5e7eb;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: #111827;
}

.invoice {
    width: 210mm;
    max-width: 100vw;
    background: white;
    margin: 24px auto;
    padding: 20mm 18mm;
    box-shadow: 0 4px 24px rgba(0,0,0,0.12);
    font-size: 13px;
    line-height: 1.5;
}

/* Header */
.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 24px;
    margin-bottom: 16px;
}

.header-left {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.invoice-logo {
    width: 56px;
    height: 56px;
    object-fit: contain;
    border-radius: 8px;
}

.company-name {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 4px;
    color: #111827;
}

.company-info {
    margin: 0;
    font-size: 11px;
    color: #6b7280;
    line-height: 1.4;
}

.header-right {
    text-align: right;
    flex-shrink: 0;
}

.invoice-title {
    font-size: 28px;
    font-weight: 700;
    color: #059669;
    margin: 0 0 12px;
    letter-spacing: 2px;
}

.invoice-meta {
    font-size: 11px;
}

.meta-row {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin: 3px 0;
}

.meta-label {
    color: #6b7280;
}

.meta-value {
    font-weight: 600;
    min-width: 120px;
    text-align: right;
}

.status-paid {
    color: #059669;
}

.status-unpaid {
    color: #dc2626;
}

.divider {
    border-top: 2px solid #e5e7eb;
    margin: 16px 0;
}

/* Info Section */
.info-section {
    display: flex;
    gap: 48px;
    margin-bottom: 24px;
}

.section-label {
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 8px;
}

.info-grid {
    display: grid;
    grid-template-columns: 80px 1fr;
    gap: 4px 8px;
    font-size: 12px;
}

.info-grid .label {
    color: #6b7280;
}

/* Items Table */
.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    font-size: 12px;
}

.items-table thead {
    background: #f9fafb;
}

.items-table th {
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 11px;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 2px solid #e5e7eb;
}

.items-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.items-table tbody tr:last-child td {
    border-bottom: 2px solid #e5e7eb;
}

.col-no { width: 40px; text-align: center; }
.col-qty { text-align: center; }
.col-unit { text-align: center; }
.col-price, .col-discount, .col-subtotal { text-align: right; white-space: nowrap; }

.items-table th.col-no,
.items-table th.col-qty,
.items-table th.col-unit { text-align: center; }
.items-table th.col-price,
.items-table th.col-discount,
.items-table th.col-subtotal { text-align: right; }

/* Summary */
.summary-section {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 24px;
}

.summary-box {
    width: 280px;
    font-size: 12px;
}

.sum-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
}

.sum-total {
    font-size: 16px;
    font-weight: 700;
    border-top: 2px solid #111827;
    border-bottom: 2px solid #111827;
    margin: 4px 0;
    padding: 8px 0;
    color: #059669;
}

.text-red {
    color: #dc2626;
}

.order-notes {
    font-size: 11px;
    color: #6b7280;
    padding: 12px 16px;
    background: #f9fafb;
    border-radius: 6px;
    margin-bottom: 24px;
}

/* Footer */
.invoice-footer {
    margin-top: 40px;
}

.signature-area {
    display: flex;
    justify-content: space-between;
    gap: 80px;
    margin-bottom: 32px;
}

.sig-col {
    flex: 1;
    text-align: center;
}

.sig-line {
    height: 60px;
}

.sig-col p {
    margin: 0;
    font-size: 11px;
    color: #6b7280;
}

.sig-name {
    font-weight: 600;
    color: #111827 !important;
    margin-top: 4px !important;
    border-top: 1px solid #d1d5db;
    padding-top: 6px;
}

.footer-note {
    text-align: center;
    font-size: 10px;
    color: #9ca3af;
    font-style: italic;
    margin: 0;
}

/* No-print actions */
.no-print-actions {
    display: flex;
    gap: 12px;
    margin: 0 auto 40px;
}

.btn-back, .btn-print {
    padding: 10px 24px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 14px;
    cursor: pointer;
    background: white;
    transition: all 0.15s;
    font-family: 'Inter', sans-serif;
}

.btn-back:hover { background: #f3f4f6; }

.btn-print {
    background: #059669;
    color: white;
    border-color: #059669;
}
.btn-print:hover { background: #047857; }

/* Print styles */
@media print {
    @page {
        size: A4;
        margin: 10mm;
    }

    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .invoice-page {
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .invoice {
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    .no-print-actions {
        display: none !important;
    }
}
</style>
