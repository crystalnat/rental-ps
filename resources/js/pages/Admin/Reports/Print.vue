<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Bar, Doughnut } from 'vue-chartjs'
import {
    Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement,
    PointElement, LineElement, Title, Tooltip, Legend, Filler,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler)

// Label grafik disamakan dengan font dokumen agar hasil cetak terlihat satu kesatuan
ChartJS.defaults.font.family = "'Segoe UI', 'Helvetica Neue', Arial, sans-serif"
ChartJS.defaults.color = '#4b5563'

interface TopProduct { product_name: string; total_qty: number; total_amount: number }
interface SalesByCashier { cashier_id: number | null; cashier_name: string; order_count: number; total_amount: number }
interface SalesByType { type: string; label: string; count: number; total: number }
interface SalesByPayment { method: string; label: string; count: number; total: number }
interface ExpenseCategoryDetail { category: string; category_label: string; total: number; count: number }
interface ExpenseRow {
    expense_date: string; created_at: string; store_name: string
    category: string; category_label: string; description: string | null
    amount: number; created_by_name: string | null; receipt_url: string | null
}

interface StoreReport {
    id: number; name: string; slug: string; income: number; hpp: number; expenses: number
    order_count: number; gross_profit: number; net: number
    chart_sales: { labels: string[]; data: number[] }
    chart_expense_by_category: { labels: string[]; amounts: number[] }
    top_products: TopProduct[]; sales_by_cashier: SalesByCashier[]
    sales_by_type: SalesByType[]; sales_by_payment: SalesByPayment[]
}

interface OverallReport {
    income: number; hpp: number; expenses: number; net: number; gross_profit: number; order_count: number
    chart_sales: { labels: string[]; data: number[] }
    chart_expense_by_category: { labels: string[]; amounts: number[] }
    chart_sales_by_store: { labels: string[]; data: number[] }
    chart_expense_by_store: { labels: string[]; data: number[] }
    top_products: TopProduct[]; sales_by_cashier: SalesByCashier[]
    sales_by_type: SalesByType[]; sales_by_payment: SalesByPayment[]
    expense_by_category_detail: ExpenseCategoryDetail[]
    expense_list: ExpenseRow[]
    orders: Array<{ order_code: string; store_name: string; cashier_name: string; final_amount: number; type: string; payment_method: string; created_at: string }>
}

const props = defineProps<{
    date_from: string; date_to: string; overall: OverallReport; per_store: StoreReport[]
}>()

const fmt = (v: any) => {
    const n = Number(v); if (isNaN(n)) return 'Rp 0'
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n)
}

const barData = (labels: string[], data: number[], color = '#dc2626') => ({
    labels, datasets: [{ label: 'Data', data, backgroundColor: color + 'aa', borderColor: color, borderWidth: 1, borderRadius: 3 }]
})
const pieData = (labels: string[], data: number[]) => ({
    labels, datasets: [{ data, backgroundColor: ['#dc2626aa','#6b7280aa','#f59e0baa','#10b981aa','#8b5cf6aa','#64748baa','#f43f5eaa','#0ea5e9aa'], borderWidth: 0 }]
})

const chartOpt: any = {
    responsive: true, maintainAspectRatio: false, animation: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { font: { size: 7 } } }, x: { ticks: { font: { size: 7 }, maxRotation: 45 } } }
}
const pieOpt: any = {
    responsive: true, maintainAspectRatio: false, animation: false,
    plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 8, padding: 8, font: { size: 7, weight: 'bold' } } } }
}

const ordersByStore = (name: string) => props.overall.orders?.filter(o => o.store_name === name) || []

const marginPct = computed(() => {
    if (props.overall.income === 0) return '0%'
    return ((props.overall.gross_profit / props.overall.income) * 100).toFixed(1) + '%'
})

const ready = ref(false)

// Cetak ditunda sampai gambar bukti selesai dimuat, kalau tidak thumbnail keluar kosong di PDF
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
    setTimeout(() => {
        ready.value = true
        setTimeout(() => { waitForImages().then(() => window.print()) }, 1500)
    }, 800)
})
</script>

<template>
    <Head title="Cetak Laporan" />
    <div id="rpt">

        <!-- ==================== SECTION 1: COVER + SUMMARY ==================== -->
        <table class="sec sec-break">
            <thead><tr><td class="sec-hdr-cell">
                <div class="sec-hdr">
                    <span class="sec-hdr-l">OFFICIAL REPORT</span>
                    <span class="sec-hdr-r">Periode {{ date_from }} s/d {{ date_to }}</span>
                </div>
            </td></tr></thead>
            <tfoot><tr><td class="sec-ftr-cell">
                <div class="sec-ftr">Dokumen Internal — Dicetak: {{ new Date().toLocaleDateString('id-ID') }}</div>
            </td></tr></tfoot>
            <tbody><tr><td class="sec-body-cell">

                <div class="cover">
                    <div>
                        <h1 class="cover-t">LAPORAN<br/>ANALITIK</h1>
                        <p class="cover-s">Kinerja Finansial &amp; Operasional Bisnis</p>
                    </div>
                    <div style="text-align:right">
                        <div class="cover-badge">PERIODE LAPORAN</div>
                        <p class="cover-dt">{{ date_from }} — {{ date_to }}</p>
                        <p class="cover-m">Dicetak: {{ new Date().toLocaleString('id-ID') }}</p>
                    </div>
                </div>

                <div class="g4 mb20">
                    <div class="kpi kpi-accent"><div class="kpi-l">Total Penjualan</div><div class="kpi-v cv-accent">{{ fmt(overall.income) }}</div><div class="kpi-n">{{ overall.order_count }} Transaksi</div></div>
                    <div class="kpi kpi-muted"><div class="kpi-l">HPP (Modal)</div><div class="kpi-v cv-muted">{{ fmt(overall.hpp) }}</div><div class="kpi-n">Cost of Goods Sold</div></div>
                    <div class="kpi kpi-dark"><div class="kpi-l">Pengeluaran</div><div class="kpi-v cv-dark">{{ fmt(overall.expenses) }}</div><div class="kpi-n">Biaya Operasional</div></div>
                    <div class="kpi kpi-result"><div class="kpi-l">Laba Bersih</div><div class="kpi-v" :class="overall.net >= 0 ? 'cv-green' : 'cv-accent'">{{ fmt(overall.net) }}</div><div class="kpi-n">Net Profit</div></div>
                </div>

                <div class="g2 mb20">
                    <div class="kpi kpi-accent" style="text-align:center">
                        <div class="kpi-l">Laba Kotor (Gross Profit)</div>
                        <div class="kpi-v cv-accent" style="font-size:26px">{{ fmt(overall.gross_profit) }}</div>
                        <div class="kpi-n">Pendapatan − HPP, sebelum biaya operasional</div>
                    </div>
                    <div class="kpi" style="text-align:center;border-color:#d1d5db">
                        <div class="kpi-l">Margin Kotor</div>
                        <div class="kpi-v" style="font-size:32px;color:#1e293b">{{ marginPct }}</div>
                        <div class="kpi-n">Dari total omzet penjualan</div>
                    </div>
                </div>

                <div class="g2 mb20" style="align-items:start">
                    <div>
                        <div class="sh">Ringkasan Tipe Penjualan</div>
                        <div class="g2" style="gap:8px">
                            <div v-for="t in overall.sales_by_type" :key="t.type" class="info-card">
                                <div class="info-lbl">{{ t.label }}</div>
                                <div class="info-val">{{ fmt(t.total) }}</div>
                                <div class="info-sub">{{ t.count }} Transaksi</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="sh">Ringkasan Pembayaran</div>
                        <div class="g2" style="gap:8px">
                            <div v-for="p in overall.sales_by_payment" :key="p.method" class="info-card">
                                <div class="info-lbl">{{ p.label }}</div>
                                <div class="info-val cv-green">{{ fmt(p.total) }}</div>
                                <div class="info-sub">{{ p.count }}x Digunakan</div>
                            </div>
                        </div>
                    </div>
                </div>

            </td></tr></tbody>
        </table>

        <!-- ==================== SECTION 2: CHARTS + BRANCHES ==================== -->
        <table class="sec sec-break">
            <thead><tr><td class="sec-hdr-cell">
                <div class="sec-hdr">
                    <span class="sec-hdr-l">OFFICIAL REPORT</span>
                    <span class="sec-hdr-r">Periode {{ date_from }} s/d {{ date_to }}</span>
                </div>
            </td></tr></thead>
            <tfoot><tr><td class="sec-ftr-cell">
                <div class="sec-ftr">Dokumen Internal — Dicetak: {{ new Date().toLocaleDateString('id-ID') }}</div>
            </td></tr></tfoot>
            <tbody><tr><td class="sec-body-cell">

                <div class="badge">GRAFIK</div>

                <div class="g2 mb20">
                    <div class="cht"><div class="cht-t">Tren Penjualan Harian</div><div class="cht-a"><Bar v-if="ready" :data="barData(overall.chart_sales.labels, overall.chart_sales.data)" :options="chartOpt" /></div></div>
                    <div class="cht"><div class="cht-t">Distribusi Pengeluaran</div><div class="cht-a"><Doughnut v-if="ready" :data="pieData(overall.chart_expense_by_category.labels, overall.chart_expense_by_category.amounts)" :options="pieOpt" /></div></div>
                </div>

            </td></tr></tbody>
        </table>

        <!-- ==================== SECTION 3: PRODUCTS + CASHIERS ==================== -->
        <table class="sec sec-break">
            <thead><tr><td class="sec-hdr-cell">
                <div class="sec-hdr">
                    <span class="sec-hdr-l">OFFICIAL REPORT</span>
                    <span class="sec-hdr-r">Periode {{ date_from }} s/d {{ date_to }}</span>
                </div>
            </td></tr></thead>
            <tfoot><tr><td class="sec-ftr-cell">
                <div class="sec-ftr">Dokumen Internal — Dicetak: {{ new Date().toLocaleDateString('id-ID') }}</div>
            </td></tr></tfoot>
            <tbody><tr><td class="sec-body-cell">

                <div class="badge">PRODUK, KASIR &amp; PEMBAYARAN</div>

                <div class="g2 mb20" style="align-items:start">
                    <div>
                        <div class="sh">Top Produk Terlaris</div>
                        <table class="tb tbs">
                            <thead><tr><th class="tal">Nama Produk</th><th style="text-align:center">Qty</th><th>Total</th></tr></thead>
                            <tbody>
                                <tr v-for="(p,i) in overall.top_products" :key="i">
                                    <td class="tal bld">{{ p.product_name }}</td>
                                    <td style="text-align:center;font-weight:800;color:#dc2626">{{ p.total_qty }}</td>
                                    <td>{{ fmt(p.total_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <div class="sh">Penjualan per Kasir</div>
                        <table class="tb tbs mb16">
                            <thead><tr><th class="tal">Kasir</th><th style="text-align:center">Tx</th><th>Total</th></tr></thead>
                            <tbody>
                                <tr v-for="(c,i) in overall.sales_by_cashier" :key="i">
                                    <td class="tal bld up">{{ c.cashier_name }}</td>
                                    <td style="text-align:center">{{ c.order_count }}</td>
                                    <td class="cv-accent bld">{{ fmt(c.total_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="sh">Metode Pembayaran</div>
                        <table class="tb tbs">
                            <thead><tr><th class="tal">Metode</th><th style="text-align:center">Freq</th><th>Nominal</th></tr></thead>
                            <tbody>
                                <tr v-for="(p,i) in overall.sales_by_payment" :key="i">
                                    <td class="tal bld up">{{ p.label }}</td>
                                    <td style="text-align:center">{{ p.count }}x</td>
                                    <td class="cv-green bld">{{ fmt(p.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="sh">Rincian Kategori Pengeluaran</div>
                <table class="tb tbs">
                    <thead><tr><th class="tal">Kategori Biaya</th><th style="text-align:center">Jumlah</th><th>Total</th></tr></thead>
                    <tbody>
                        <tr v-for="(e,i) in overall.expense_by_category_detail" :key="i">
                            <td class="tal bld up">{{ e.category_label }}</td>
                            <td style="text-align:center">{{ e.count }}</td>
                            <td class="cv-accent bld">{{ fmt(e.total) }}</td>
                        </tr>
                        <tr v-if="!overall.expense_by_category_detail?.length"><td colspan="3" class="empty">Tidak ada data.</td></tr>
                    </tbody>
                </table>

            </td></tr></tbody>
        </table>

        <!-- ==================== SECTION 4: TRANSACTION LOG ==================== -->
        <table class="sec sec-break">
            <thead><tr><td class="sec-hdr-cell">
                <div class="sec-hdr">
                    <span class="sec-hdr-l">OFFICIAL REPORT</span>
                    <span class="sec-hdr-r">Periode {{ date_from }} s/d {{ date_to }}</span>
                </div>
            </td></tr></thead>
            <tfoot><tr><td class="sec-ftr-cell">
                <div class="sec-ftr">Dokumen Internal — Dicetak: {{ new Date().toLocaleDateString('id-ID') }}</div>
            </td></tr></tfoot>
            <tbody><tr><td class="sec-body-cell">

                <div class="badge badge-dark">DAFTAR TRANSAKSI DETAIL — GABUNGAN</div>

                <table class="tb tbs">
                    <thead><tr>
                        <th class="tal">Waktu</th><th class="tal">Kode Order</th><th class="tal">Cabang</th><th class="tal">Kasir</th><th class="tal">Metode</th><th>Total</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(o,i) in overall.orders" :key="'go-'+i">
                            <td class="tal mono faded">{{ o.created_at }}</td>
                            <td class="tal bld cv-accent up" style="letter-spacing:-0.3px">{{ o.order_code }}</td>
                            <td class="tal bld up">{{ o.store_name }}</td>
                            <td class="tal">{{ o.cashier_name || '—' }}</td>
                            <td class="tal up faded" style="font-size:7px">{{ o.payment_method }}</td>
                            <td class="bld">{{ fmt(o.final_amount) }}</td>
                        </tr>
                        <tr v-if="!overall.orders?.length"><td colspan="6" class="empty">Tidak ada transaksi.</td></tr>
                    </tbody>
                </table>

            </td></tr></tbody>
        </table>

        <!-- ==================== SECTION 5: EXPENSE LOG ==================== -->
        <table class="sec sec-break">
            <thead><tr><td class="sec-hdr-cell">
                <div class="sec-hdr">
                    <span class="sec-hdr-l">OFFICIAL REPORT</span>
                    <span class="sec-hdr-r">Periode {{ date_from }} s/d {{ date_to }}</span>
                </div>
            </td></tr></thead>
            <tfoot><tr><td class="sec-ftr-cell">
                <div class="sec-ftr">Dokumen Internal — Dicetak: {{ new Date().toLocaleDateString('id-ID') }}</div>
            </td></tr></tfoot>
            <tbody><tr><td class="sec-body-cell">

                <div class="badge badge-dark">DAFTAR PENGELUARAN DETAIL — GABUNGAN</div>

                <table class="tb tbs">
                    <thead><tr>
                        <th class="tal">Tanggal</th><th class="tal">Cabang</th><th class="tal">Kategori</th>
                        <th class="tal">Keterangan</th><th class="tal">Dicatat Oleh</th><th>Jumlah</th><th>Bukti</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(e,i) in overall.expense_list" :key="'ex-'+i">
                            <td class="tal mono faded">{{ e.expense_date }}</td>
                            <td class="tal bld up">{{ e.store_name }}</td>
                            <td class="tal up">{{ e.category_label }}</td>
                            <td class="tal">{{ e.description || '—' }}</td>
                            <td class="tal faded">{{ e.created_by_name || '—' }}</td>
                            <td class="bld cv-accent">{{ fmt(e.amount) }}</td>
                            <td>
                                <img v-if="e.receipt_url" :src="e.receipt_url" class="rcpt" alt="Bukti" />
                                <span v-else class="faded">—</span>
                            </td>
                        </tr>
                        <tr v-if="!overall.expense_list?.length"><td colspan="7" class="empty">Tidak ada pengeluaran.</td></tr>
                        <tr v-else class="ttl-row">
                            <td class="tal bld up" colspan="5">Total Pengeluaran</td>
                            <td class="bld cv-accent">{{ fmt(overall.expenses) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

            </td></tr></tbody>
        </table>

        <!-- ==================== SIGNATURE PAGE ==================== -->
        <table class="sec sec-break">
            <thead><tr><td class="sec-hdr-cell">
                <div class="sec-hdr">
                    <span class="sec-hdr-l">OFFICIAL REPORT</span>
                    <span class="sec-hdr-r">Periode {{ date_from }} s/d {{ date_to }}</span>
                </div>
            </td></tr></thead>
            <tfoot><tr><td class="sec-ftr-cell">
                <div class="sec-ftr">Dokumen sah tanpa materai · &copy; 2026 Sistem POS</div>
            </td></tr></tfoot>
            <tbody><tr><td class="sec-body-cell">

                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:500px">
                    <p class="sig-title">Lembar Pengesahan</p>
                    <div style="display:flex;gap:200px">
                        <div style="text-align:center">
                            <p class="sig-l">Disiapkan Oleh</p>
                            <div class="sig-line"></div>
                            <p class="sig-n">( Administrasi )</p>
                        </div>
                        <div style="text-align:center">
                            <p class="sig-l">Disetujui Oleh</p>
                            <div class="sig-line"></div>
                            <p class="sig-n">( Pimpinan )</p>
                        </div>
                    </div>
                </div>

            </td></tr></tbody>
        </table>

    </div>
</template>

<style>
/* ==============================================
   POS REPORT — RED / GRAY / WHITE THEME
   Menggunakan display:table agar header & footer
   otomatis repeat di setiap halaman print
   ============================================== */

/* Font dipakai yang sudah terpasang di sistem: dokumen cetak tidak boleh
   bergantung pada unduhan font, kalau gagal muat hasil cetak berubah bentuk.
   Serif untuk judul (formal, sesuai laporan resmi), sans untuk angka dan tabel. */
:root {
    --font-heading: 'Georgia', 'Cambria', 'Times New Roman', serif;
    --font-body: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
}

#rpt {
    font-family: var(--font-body);
    color: #1e293b;
    font-size: 10px;
    background: #f1f5f9;
    font-variant-numeric: tabular-nums;
    -webkit-font-smoothing: antialiased;
}

/* Angka pada laporan keuangan wajib rata kolom, jadi tabular-nums dipaksa global */
#rpt table, #rpt td, #rpt th { font-variant-numeric: tabular-nums; }

/* --- SECTIONS = A4 pages, kini pakai display:table --- */
.sec {
    width: 210mm;
    display: table;
    table-layout: fixed;
    border-collapse: collapse;
    background: white;
    box-shadow: 0 2px 20px rgba(0,0,0,.1), 0 0 1px rgba(0,0,0,.12);
    box-sizing: border-box;
    margin: 24px auto;
}

/* Sel header (thead) */
.sec > thead { display: table-header-group; }
.sec > thead > tr > td.sec-hdr-cell {
    padding: 12mm 16mm 0 16mm;
    display: block;
}

/* Sel footer (tfoot) */
.sec > tfoot { display: table-footer-group; }
.sec > tfoot > tr > td.sec-ftr-cell {
    padding: 0 16mm 10mm 16mm;
    display: block;
}

/* Sel konten (tbody) */
.sec > tbody { display: table-row-group; }
.sec > tbody > tr > td.sec-body-cell {
    padding: 8mm 16mm;
    display: block;
}

/* --- HEADER (in-flow, part of content) --- */
.sec-hdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 4px solid #dc2626;
    padding-bottom: 12px;
    margin-bottom: 24px;
}
.sec-hdr-l { font-family: var(--font-heading); font-size: 13px; font-weight: 700; color: #dc2626; letter-spacing: 0.5px; }
.sec-hdr-r { font-size: 8.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }

/* --- FOOTER (in-flow, part of content) --- */
.sec-ftr {
    margin-top: 28px;
    padding-top: 10px;
    border-top: 2px solid #e5e7eb;
    font-size: 8px;
    font-weight: 600;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-align: center;
}

/* --- COVER --- */
.cover { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 6px solid #dc2626; padding-bottom: 28px; margin-bottom: 32px; }
.cover-t { font-family: var(--font-heading); font-size: 36px; font-weight: 700; color: #dc2626; letter-spacing: -0.5px; line-height: 1.1; }
.cover-s { font-size: 10px; color: #4b5563; font-weight: 600; text-transform: uppercase; letter-spacing: 2.5px; margin-top: 12px; border-left: 4px solid #dc2626; padding-left: 10px; }
.cover-badge { background: #dc2626; color: white; padding: 7px 18px; font-weight: 700; font-size: 11px; letter-spacing: 1px; border-radius: 4px; display: inline-block; margin-bottom: 8px; }
.cover-dt { font-family: var(--font-heading); font-size: 20px; font-weight: 700; color: #1e293b; letter-spacing: 0; }
.cover-m { font-size: 9px; color: #6b7280; font-weight: 600; margin-top: 4px; }

/* --- KPI --- */
.kpi { border: 2px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; }
.kpi-accent { border-color: #fecaca; background: #fef2f2; }
.kpi-muted { border-color: #e5e7eb; background: #f9fafb; }
.kpi-dark { border-color: #d1d5db; background: #f3f4f6; }
.kpi-result { border-color: #a7f3d0; background: #ecfdf5; }
.kpi-l { font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: #6b7280; margin-bottom: 5px; }
.kpi-v { font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: -0.2px; }
.kpi-n { font-size: 7px; font-weight: 500; color: #9ca3af; margin-top: 4px; }

/* --- COLORS --- */
.cv-accent { color: #dc2626; }
.cv-muted { color: #6b7280; }
.cv-dark { color: #374151; }
.cv-green { color: #059669; }

/* --- INFO CARDS --- */
.info-card { border: 2px solid #f3f4f6; border-radius: 8px; padding: 8px 10px; background: #fafafa; }
.info-lbl { font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: #6b7280; margin-bottom: 3px; }
.info-val { font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; }
.info-sub { font-size: 7px; font-weight: 500; color: #9ca3af; margin-top: 2px; }

/* --- CHARTS --- */
.cht { border: 2px dashed #e5e7eb; border-radius: 12px; padding: 10px; background: #fafafa; }
.cht-t { font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #6b7280; text-align: center; margin-bottom: 8px; }
.cht-a { height: 180px; }
.cht-a-s { height: 140px; }

/* --- BADGE TITLES --- */
.badge { font-family: var(--font-heading); font-size: 12px; font-weight: 700; color: white; background: #dc2626; display: inline-block; padding: 5px 14px; border-radius: 4px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
.badge-dark { background: #374151; }

/* --- SUB HEADS --- */
.sh { font-size: 8.5px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 1.5px; border-bottom: 1.5px solid #d1d5db; padding-bottom: 5px; margin-bottom: 8px; }

/* --- TABLES --- */
.tb { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; }
.tb thead tr { background: #374151; color: #f9fafb; }
.tb thead th { padding: 7px 10px; font-size: 7px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; text-align: right; border: 1px solid #4b5563; }
.tb tbody td { padding: 6px 10px; border: 1px solid #e5e7eb; font-weight: 500; text-align: right; font-variant-numeric: tabular-nums; }
.tb tbody tr:nth-child(even) { background: #f9fafb; }
.tb-total td { background: #dc2626 !important; color: white !important; font-weight: 700 !important; }
.tbs thead th { padding: 4px 8px; }
.tbs tbody td { padding: 4px 8px; font-size: 8.5px; line-height: 1.4; }

/* --- STORE HEADER --- */
.store-hd { border-bottom: 5px solid #374151; padding-bottom: 14px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
.store-pre { font-size: 8.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 2.5px; }
.store-nm { font-family: var(--font-heading); font-size: 22px; font-weight: 700; color: #dc2626; letter-spacing: 0; margin-top: 4px; }
.store-id { font-size: 8.5px; font-weight: 600; color: #9ca3af; }

/* --- SIGNATURE --- */
.sig-title { font-family: var(--font-heading); font-size: 13px; font-weight: 700; color: #4b5563; text-transform: uppercase; letter-spacing: 5px; margin-bottom: 100px; }
.sig-l { font-size: 8.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 2.5px; margin-bottom: 100px; }
.sig-line { border-bottom: 1.5px solid #374151; width: 180px; margin: 0 auto 10px; }
.sig-n { font-family: var(--font-heading); font-size: 10px; font-weight: 700; color: #374151; }

/* --- GRIDS --- */
.g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.g3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
.g4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; }

/* --- UTILS --- */
.tal { text-align: left !important; }
.bld { font-weight: 900 !important; }
.up { text-transform: uppercase; }
.mono { font-variant-numeric: tabular-nums; }
.faded { color: #9ca3af; font-style: italic; }
.mb16 { margin-bottom: 16px; }
.mb20 { margin-bottom: 20px; }
.empty { text-align: center !important; color: #d1d5db; font-style: italic; padding: 20px !important; }
.ttl-row td { background: #f8fafc; border-top: 2px solid #cbd5e1; }
/* Thumbnail bukti dibuat kecil agar satu baris pengeluaran tetap muat di lebar A4 */
.rcpt { display: block; margin: 0 auto; width: 46px; height: 46px; object-fit: cover; border: 1px solid #e2e8f0; border-radius: 3px; }

/* ==============================================
   PRINT — display:table menjamin header & footer
   otomatis muncul di setiap halaman tanpa perlu
   fixed positioning. Konten tidak akan menimpa.
   ============================================== */
@media print {
    @page {
        size: A4;
        margin: 0;
    }

    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    #rpt {
        background: white !important;
        padding: 0;
    }

    /* Table sec otomatis handle page break dengan header/footer repeat */
    .sec {
        width: 100%;
        margin: 0;
        box-shadow: none;
        max-width: none;
        box-sizing: border-box;
        page-break-inside: auto;
    }

    /* Pisah antar section (Cover, Grafik, Produk, dst.) */
    .sec-break {
        page-break-after: always;
    }
    .sec-break:last-child {
        page-break-after: avoid;
    }

    /* Header & footer repeat otomatis karena display:table-header/footer-group */
    .sec > thead { display: table-header-group; }
    .sec > tfoot { display: table-footer-group; }
    .sec > tbody { display: table-row-group; }

    /* Tabel transaksi: izinkan break di dalam, tapi tidak di tengah satu baris */
    .tb { page-break-inside: auto; }
    .tb thead { display: table-header-group; }
    .tb tbody tr { page-break-inside: avoid; }

    /* Elemen yang tidak boleh terpotong */
    .cht { page-break-inside: avoid; }
    .kpi { page-break-inside: avoid; }
    .g2, .g3, .g4 { page-break-inside: avoid; }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}

@media screen {
    #rpt { padding: 24px 0; }
}
</style>    