<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { router } from '@inertiajs/vue3'
import { Bar, Doughnut, Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatCurrency } from '@/lib/utils'
import {
    BarChart3,
    TrendingUp,
    TrendingDown,
    Wallet,
    Package,
    Search,
    Store,
    ChevronDown,
    ShoppingBag,
    FileSpreadsheet,
    FileText,
    Printer,
} from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler)

interface StoreItem {
    id: number
    name: string
    slug: string
}

interface TopProduct {
    product_name: string
    total_qty: number
    total_amount: number
}

interface SalesByCashier {
    cashier_id: number | null
    cashier_name: string
    order_count: number
    total_amount: number
}

interface SalesByType {
    type: string
    label: string
    count: number
    total: number
}

interface SalesByPayment {
    method: string
    label: string
    count: number
    total: number
}

interface SegmentProduct {
    product_name: string
    category_name: string
    total_qty: number
    total_amount: number
    total_hpp: number
    gross_profit: number
}

interface SegmentOrderItem {
    order_code: string
    created_at: string
    store_name: string
    cashier_name: string | null
    category_name: string
    product_name: string
    quantity: number
    unit_price: number
    subtotal: number
}

interface SalesSegment {
    key: string
    label: string
    qty: number
    income: number
    hpp: number
    gross_profit: number
    order_count: number
    products: SegmentProduct[]
    orders: SegmentOrderItem[]
}

interface ExpenseCategoryDetail {
    category: string
    category_label: string
    total: number
    count: number
}

interface ExpenseRow {
    expense_date: string
    created_at: string
    store_name: string
    category: string
    category_label: string
    description: string | null
    amount: number
    created_by_name: string | null
    receipt_url: string | null
}

interface StoreReport {
    id: number
    name: string
    slug: string
    income: number
    hpp: number
    expenses: number
    order_count: number
    gross_profit: number
    net: number
    chart_sales: { labels: string[]; data: number[] }
    chart_expense_by_category: { labels: string[]; amounts: number[] }
    top_products: TopProduct[]
    sales_by_cashier: SalesByCashier[]
    sales_by_type: SalesByType[]
    sales_by_payment: SalesByPayment[]
}

interface OverallReport {
    income: number
    hpp: number
    expenses: number
    net: number
    gross_profit: number
    order_count: number
    chart_sales: { labels: string[]; data: number[] }
    chart_expense_by_category: { labels: string[]; amounts: number[] }
    chart_sales_by_store: { labels: string[]; data: number[] }
    chart_expense_by_store: { labels: string[]; data: number[] }
    top_products: TopProduct[]
    sales_by_cashier: SalesByCashier[]
    sales_by_type: SalesByType[]
    sales_by_payment: SalesByPayment[]
    expense_by_category_detail: ExpenseCategoryDetail[]
    expense_list: ExpenseRow[]
    segments: SalesSegment[]
    has_detail: boolean
    orders: Array<{
        order_code: string
        store_name: string
        cashier_name: string
        final_amount: number
        type: string
        payment_method: string
        created_at: string
    }>
}

const props = defineProps<{
    stores: StoreItem[]
    date_from: string
    date_to: string
    overall: OverallReport
    per_store: StoreReport[]
}>()

const filterState = ref({
    date_from: props.date_from ?? '',
    date_to: props.date_to ?? '',
})

const expandedStores = ref<Record<number, boolean>>(
    props.per_store?.length === 1 ? { [props.per_store[0].id]: true } : {},
)
function isStoreExpanded(id: number) {
    return expandedStores.value[id] ?? false
}

watch(() => [props.date_from, props.date_to], ([from, to]) => {
    filterState.value.date_from = (from as string) ?? ''
    filterState.value.date_to = (to as string) ?? ''
})

function makeChartSalesConfig(labels: string[], data: number[]) {
    return {
        labels,
        datasets: [{
            label: 'Penjualan',
            data,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: 'rgb(16, 185, 129)',
            borderWidth: 1,
        }],
    }
}

const isNarrow = useMediaQuery('(max-width: 640px)')

// Label rupiah penuh memakan lebar area grafik di layar ponsel
function shortCurrency(value: number): string {
    if (Math.abs(value) >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} jt`
    if (Math.abs(value) >= 1_000) return `${Math.round(value / 1_000)} rb`
    return String(value)
}

const chartSalesOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx: any) => formatCurrency(ctx.raw),
            },
        },
    },
    scales: {
        x: {
            ticks: {
                font: { size: isNarrow.value ? 9 : 11 },
                maxRotation: isNarrow.value ? 60 : 0,
                autoSkip: true,
                maxTicksLimit: isNarrow.value ? 8 : 24,
            },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: {
                font: { size: isNarrow.value ? 9 : 11 },
                callback: (v: any) => (isNarrow.value ? shortCurrency(v) : formatCurrency(v)),
            },
        },
    },
}))

function makeChartExpenseConfig(labels: string[], amounts: number[]) {
    const colors = [
        'rgba(239, 68, 68, 0.8)', 'rgba(249, 115, 22, 0.8)', 'rgba(234, 179, 8, 0.8)',
        'rgba(34, 197, 94, 0.8)', 'rgba(59, 130, 246, 0.8)', 'rgba(139, 92, 246, 0.8)',
    ]
    return {
        labels,
        datasets: [{
            data: amounts,
            backgroundColor: colors.slice(0, labels.length),
            borderWidth: 0,
        }],
    }
}

// Legenda di samping memakan hampir separuh lebar layar ponsel, jadi dipindah ke bawah
const chartExpenseOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: (isNarrow.value ? 'bottom' : 'right') as 'bottom' | 'right',
            labels: { boxWidth: 12, padding: 10, font: { size: isNarrow.value ? 10 : 12 } },
        },
        tooltip: {
            callbacks: {
                label: (ctx: any) =>
                    `${ctx.label}: ${formatCurrency(ctx.raw)}`,
            },
        },
    },
}))

function makeChartByStoreConfig(labels: string[], data: number[], color: string) {
    return {
        labels,
        datasets: [{
            label: 'Total',
            data,
            backgroundColor: color,
            borderWidth: 1,
        }],
    }
}

const isLoading = ref(false)

// preserveState menjaga input tanggal tidak di-remount, supaya date picker tidak tertutup paksa
function applyFilters() {
    if (filterState.value.date_from === props.date_from && filterState.value.date_to === props.date_to) {
        return
    }
    router.get('/admin/reports', {
        date_from: filterState.value.date_from || undefined,
        date_to: filterState.value.date_to || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { isLoading.value = true },
        onFinish: () => { isLoading.value = false },
    })
}

function viewCashier(cashierId: number | null) {
    if (cashierId) router.visit(`/admin/users/${cashierId}`)
}

function toggleStore(storeId: number) {
    expandedStores.value = { ...expandedStores.value, [storeId]: !expandedStores.value[storeId] }
}

const hasData = computed(() =>
    props.overall.order_count > 0 ||
    props.overall.expenses > 0 ||
    props.per_store.some(s => s.order_count > 0 || s.expenses > 0),
)



function exportXlsx() {
    withDetail(() => withXlsx(buildXlsx))
}

function exportSegmentXlsx(segmentKey: string) {
    withDetail(() => withXlsx(() => buildSegmentXlsx(segmentKey)))
}

// Rincian per item tidak ikut saat halaman dimuat, jadi diambil dulu sebelum menyusun file
function withDetail(callback: () => void) {
    if (props.overall.has_detail) {
        callback()
        return
    }
    isLoading.value = true
    router.reload({
        data: {
            date_from: filterState.value.date_from || undefined,
            date_to: filterState.value.date_to || undefined,
            detail: 1,
        },
        only: ['overall'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => callback(),
        onFinish: () => { isLoading.value = false },
    })
}

// xlsx-js-style: fork SheetJS dengan dukungan styling, API-nya identik
function withXlsx(callback: () => void) {
    if (!(window as any).XLSX) {
        const script = document.createElement('script')
        script.src = 'https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js'
        script.onload = () => callback()
        document.head.appendChild(script)
    } else {
        callback()
    }
}

const SHEET_ACCENTS: Record<string, string> = {
    default: '166534',
    rental: '1D4ED8',
    fnb: 'B45309',
    expense: 'B91C1C',
}

// Excel butuh URL absolut, sedangkan backend mengirim path relatif dari storage
function absoluteUrl(url: string): string {
    return url.startsWith('http') ? url : `${window.location.origin}${url}`
}

const CURRENCY_FORMAT = '"Rp"#,##0'
const THIN_BORDER = { style: 'thin', color: { rgb: 'D9D9D9' } }

interface SheetStyleOptions {
    accent?: string
    titleRows?: number[]
    headerRows?: number[]
    boldRows?: number[]
    currencyColumns?: number[]
}

/**
 * Susun worksheet sekaligus styling-nya. Struktur kolom dan baris tidak diubah,
 * hanya tampilan: warna header, format rupiah, lebar kolom, dan garis tabel.
 */
function makeStyledSheet(rows: any[][], options: SheetStyleOptions = {}) {
    const XLSX = (window as any).XLSX
    const accent = options.accent ?? SHEET_ACCENTS.default
    const titleRows = options.titleRows ?? []
    const headerRows = options.headerRows ?? []
    const boldRows = options.boldRows ?? []
    const currencyColumns = options.currencyColumns ?? []

    const ws = XLSX.utils.aoa_to_sheet(rows)
    const columnCount = Math.max(...rows.map(r => r.length), 1)

    ws['!cols'] = Array.from({ length: columnCount }, (_, col) => {
        const longest = rows.reduce((max, row) => {
            const value = row[col]
            const length = value === null || value === undefined ? 0 : String(value).length
            return Math.max(max, length)
        }, 10)
        return { wch: Math.min(longest + 4, 42) }
    })

    rows.forEach((row, rowIndex) => {
        for (let col = 0; col < columnCount; col++) {
            const address = XLSX.utils.encode_cell({ r: rowIndex, c: col })
            const cell = ws[address]
            if (!cell) continue

            if (titleRows.includes(rowIndex)) {
                cell.s = { font: { bold: true, sz: 13, color: { rgb: accent } } }
                continue
            }

            if (headerRows.includes(rowIndex)) {
                cell.s = {
                    font: { bold: true, color: { rgb: 'FFFFFF' } },
                    fill: { fgColor: { rgb: accent } },
                    alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
                    border: { top: THIN_BORDER, bottom: THIN_BORDER, left: THIN_BORDER, right: THIN_BORDER },
                }
                continue
            }

            const isNumeric = typeof cell.v === 'number'
            cell.s = {
                font: { bold: boldRows.includes(rowIndex) },
                fill: boldRows.includes(rowIndex) ? { fgColor: { rgb: 'F2F2F2' } } : undefined,
                alignment: { horizontal: isNumeric ? 'right' : 'left', vertical: 'center' },
                border: { top: THIN_BORDER, bottom: THIN_BORDER, left: THIN_BORDER, right: THIN_BORDER },
            }
            if (isNumeric && currencyColumns.includes(col)) {
                cell.z = CURRENCY_FORMAT
            } else if (isNumeric) {
                cell.z = '#,##0'
            }
        }
    })

    // Header dibekukan agar tetap terlihat saat menggulir daftar transaksi yang panjang
    const firstHeaderRow = headerRows.length ? Math.min(...headerRows) : -1
    if (firstHeaderRow >= 0) {
        ws['!freeze'] = { xSplit: 0, ySplit: firstHeaderRow + 1 }
        // Autofilter hanya untuk sheet berisi satu tabel, bukan sheet multi-bagian
        if (headerRows.length === 1) {
            ws['!autofilter'] = {
                ref: XLSX.utils.encode_range(
                    { r: firstHeaderRow, c: 0 },
                    { r: rows.length - 1, c: columnCount - 1 },
                ),
            }
        }
    }

    return ws
}

function buildSegmentXlsx(segmentKey: string) {
    const segment = props.overall.segments?.find(s => s.key === segmentKey)
    if (!segment) return

    const XLSX = (window as any).XLSX
    const wb = XLSX.utils.book_new()

    const summaryData: any[][] = [
        [`LAPORAN ${segment.label.toUpperCase()}`, `Periode: ${props.date_from} s/d ${props.date_to}`],
        [""],
        ["Total Omzet", segment.income],
        ["Total HPP (Modal)", segment.hpp],
        ["Laba Kotor", segment.gross_profit],
        ["Item Terjual (Qty)", segment.qty],
        ["Jumlah Transaksi", segment.order_count],
    ]
    const accent = SHEET_ACCENTS[segment.key] ?? SHEET_ACCENTS.default
    XLSX.utils.book_append_sheet(wb, makeStyledSheet(summaryData, {
        accent,
        titleRows: [0],
        boldRows: [2, 3, 4],
        currencyColumns: [1],
    }), "Ringkasan")

    const productData: any[][] = [["Nama Produk", "Kategori", "Terjual (Qty)", "Omzet", "HPP", "Laba Kotor"]]
    segment.products.forEach(p => productData.push([
        p.product_name, p.category_name, p.total_qty, p.total_amount, p.total_hpp, p.gross_profit,
    ]))
    XLSX.utils.book_append_sheet(wb, makeStyledSheet(productData, {
        accent,
        headerRows: [0],
        currencyColumns: [3, 4, 5],
    }), "Kinerja Produk")

    const detailData: any[][] = [["Kode Order", "Tanggal & Waktu", "Toko", "Kasir", "Kategori", "Produk", "Qty", "Harga Satuan", "Subtotal"]]
    segment.orders.forEach(o => detailData.push([
        o.order_code, o.created_at, o.store_name, o.cashier_name || '-', o.category_name,
        o.product_name, o.quantity, o.unit_price, o.subtotal,
    ]))
    XLSX.utils.book_append_sheet(wb, makeStyledSheet(detailData, {
        accent,
        headerRows: [0],
        currencyColumns: [7, 8],
    }), "Rincian Penjualan")

    XLSX.writeFile(wb, `laporan-${segmentKey}-${props.date_from}-sd-${props.date_to}.xlsx`)
}

function buildXlsx() {
    const XLSX = (window as any).XLSX
    const wb = XLSX.utils.book_new()
    
    // 1. Ringkasan Keseluruhan & Per Toko
    const ws1_data: any[][] = [
        ["LAPORAN RINGKASAN CABANG", `Periode: ${props.date_from} s/d ${props.date_to}`],
        [""],
        ["Nama Toko", "Pendapatan", "HPP (Modal)", "Laba Kotor", "Pengeluaran", "Laba Bersih", "Total Transaksi"],
        ["Total Keseluruhan", props.overall.income, props.overall.hpp, props.overall.gross_profit, props.overall.expenses, props.overall.net, props.overall.order_count]
    ]
    props.per_store.forEach(s => ws1_data.push([s.name, s.income, s.hpp, s.gross_profit, s.expenses, s.net, s.order_count]))
    const ws1 = makeStyledSheet(ws1_data, {
        titleRows: [0],
        headerRows: [2],
        boldRows: [3],
        currencyColumns: [1, 2, 3, 4, 5],
    })
    XLSX.utils.book_append_sheet(wb, ws1, "Ringkasan Toko")

    // 2. Penjualan Harian (Daily Sales)
    const wsDaily_data: any[][] = [["Tanggal", "Total Penjualan"]]
    props.overall.chart_sales.labels.forEach((label, i) => {
        wsDaily_data.push([label, props.overall.chart_sales.data[i]])
    })
    const wsDaily = makeStyledSheet(wsDaily_data, { headerRows: [0], currencyColumns: [1] })
    XLSX.utils.book_append_sheet(wb, wsDaily, "Penjualan Harian")

    // 3. Kinerja Produk (Top Products)
    const ws2_data: any[][] = [["Nama Produk", "Terjual (Qty)", "Total Omzet"]]
    props.overall.top_products.forEach(p => ws2_data.push([p.product_name, p.total_qty, p.total_amount]))
    const ws2 = makeStyledSheet(ws2_data, { headerRows: [0], currencyColumns: [2] })
    XLSX.utils.book_append_sheet(wb, ws2, "Kinerja Produk")

    // 4. Breakdown Tipe & Pembayaran
    const wsBreakdown_data: any[][] = [
        ["RINGKASAN TIPE ORDER"],
        ["Tipe", "Total Transaksi", "Total Pendapatan"],
    ]
    props.overall.sales_by_type.forEach(t => wsBreakdown_data.push([t.label, t.count, t.total]))
    wsBreakdown_data.push([""])
    const paymentTitleRow = wsBreakdown_data.length
    wsBreakdown_data.push(["RINGKASAN METODE PEMBAYARAN"])
    wsBreakdown_data.push(["Metode", "Total Transaksi", "Total Pendapatan"])
    props.overall.sales_by_payment.forEach(p => wsBreakdown_data.push([p.label, p.count, p.total]))

    const wsBreakdown = makeStyledSheet(wsBreakdown_data, {
        titleRows: [0, paymentTitleRow],
        headerRows: [1, paymentTitleRow + 1],
        currencyColumns: [2],
    })
    XLSX.utils.book_append_sheet(wb, wsBreakdown, "Rincian Transaksi")

    // 5. Daftar Transaksi (Itemized Orders with Date/Time)
    const wsOrders_data: any[][] = [
        ["DAFTAR TRANSAKSI DETAIL", `Periode: ${props.date_from} s/d ${props.date_to}`],
        [""],
        ["Kode Order", "Tanggal & Waktu", "Toko", "Kasir", "Tipe", "Metode", "Total"],
    ]
    props.overall.orders.forEach(o => {
        wsOrders_data.push([
            o.order_code,
            o.created_at,
            o.store_name,
            o.cashier_name || '—',
            o.type,
            o.payment_method,
            o.final_amount
        ])
    })
    const wsOrders = makeStyledSheet(wsOrders_data, {
        titleRows: [0],
        headerRows: [2],
        currencyColumns: [6],
    })
    XLSX.utils.book_append_sheet(wb, wsOrders, "Daftar Transaksi")

    // 6. Rekap Pengeluaran per Kategori
    const wsExpenseRecap_data: any[][] = [
        ["REKAP PENGELUARAN PER KATEGORI", `Periode: ${props.date_from} s/d ${props.date_to}`],
        [""],
        ["Kategori", "Jumlah Entri", "Total Pengeluaran", "Porsi (%)"],
    ]
    const expenseTotal = props.overall.expenses || 0
    props.overall.expense_by_category_detail.forEach(c => {
        wsExpenseRecap_data.push([
            c.category_label,
            c.count,
            c.total,
            expenseTotal ? `${(Math.round((c.total / expenseTotal) * 1000) / 10).toFixed(1)}%` : '0%',
        ])
    })
    const expenseRecapTotalRow = wsExpenseRecap_data.length
    wsExpenseRecap_data.push([
        "Total Keseluruhan",
        props.overall.expense_by_category_detail.reduce((sum, c) => sum + c.count, 0),
        expenseTotal,
        expenseTotal ? '100.0%' : '0%',
    ])
    const wsExpenseRecap = makeStyledSheet(wsExpenseRecap_data, {
        accent: SHEET_ACCENTS.expense,
        titleRows: [0],
        headerRows: [2],
        boldRows: [expenseRecapTotalRow],
        currencyColumns: [2],
    })
    XLSX.utils.book_append_sheet(wb, wsExpenseRecap, "Rekap Pengeluaran")

    // 7. Daftar Pengeluaran per baris, disusun sama seperti daftar transaksi
    const wsExpenses_data: any[][] = [
        ["DAFTAR PENGELUARAN DETAIL", `Periode: ${props.date_from} s/d ${props.date_to}`],
        [""],
        ["Tanggal", "Waktu Input", "Toko", "Kategori", "Keterangan", "Dicatat Oleh", "Jumlah", "Bukti"],
    ]
    props.overall.expense_list.forEach(e => {
        wsExpenses_data.push([
            e.expense_date,
            e.created_at,
            e.store_name,
            e.category_label,
            e.description || '-',
            e.created_by_name || '-',
            e.amount,
            e.receipt_url ? 'Lihat Bukti' : '-',
        ])
    })
    const expenseTotalRow = wsExpenses_data.length
    wsExpenses_data.push(["Total", "", "", "", "", "", expenseTotal, ""])
    const wsExpenses = makeStyledSheet(wsExpenses_data, {
        accent: SHEET_ACCENTS.expense,
        titleRows: [0],
        headerRows: [2],
        boldRows: [expenseTotalRow],
        currencyColumns: [6],
    })
    // Excel tidak bisa menyisipkan gambar lewat SheetJS, jadi bukti dipasang sebagai tautan ke file aslinya
    const RECEIPT_COLUMN = 7
    const FIRST_EXPENSE_ROW = 3
    props.overall.expense_list.forEach((e, i) => {
        if (!e.receipt_url) return
        const address = XLSX.utils.encode_cell({ r: FIRST_EXPENSE_ROW + i, c: RECEIPT_COLUMN })
        const cell = wsExpenses[address]
        if (!cell) return
        cell.l = { Target: absoluteUrl(e.receipt_url), Tooltip: 'Buka bukti pengeluaran' }
        cell.s = { ...cell.s, font: { color: { rgb: '0563C1' }, underline: true } }
    })
    XLSX.utils.book_append_sheet(wb, wsExpenses, "Daftar Pengeluaran")

    XLSX.writeFile(wb, `laporan-pos-lengkap-${new Date().toISOString().slice(0, 10)}.xlsx`)
}

function exportPdf() {
    const params = new URLSearchParams({
        date_from: filterState.value.date_from || '',
        date_to: filterState.value.date_to || ''
    }).toString()
    window.open(`/admin/reports/print?${params}`, '_blank')
}
</script>

<template>
    <AdminLayout title="Laporan">
        <div class="space-y-8">
            <!-- Filters -->
            <Card variant="elevated" class="overflow-hidden border-none shadow-sm md:shadow-md">
                <CardHeader class="p-4 md:p-6">
                    <CardTitle class="flex items-center gap-2 text-lg md:text-xl font-bold">
                        <BarChart3 class="h-5 w-5 text-primary" />
                        Laporan Analitas
                    </CardTitle>
                    <CardDescription class="text-xs md:text-sm">
                        Pantau performa penjualan, pengeluaran, dan laba rugi seluruh cabang toko Anda.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-4 md:p-6 pt-0">
                    <div class="flex flex-col lg:flex-row lg:items-end gap-4">
                        <div class="w-full lg:w-auto space-y-1.5">
                            <Label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                Periode Laporan
                                <span v-if="isLoading" class="ml-2 normal-case text-primary">memuat...</span>
                            </Label>
                            <!-- Dua input tanggal berdampingan tidak muat di layar 320px, jadi ditumpuk dulu -->
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Input v-model="filterState.date_from" type="date" class="h-10 w-full min-w-0 font-medium sm:flex-1 lg:w-36" @change="applyFilters" />
                                <span class="hidden font-black text-muted-foreground sm:inline">/</span>
                                <Input v-model="filterState.date_to" type="date" class="h-10 w-full min-w-0 font-medium sm:flex-1 lg:w-36" @change="applyFilters" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end lg:ml-auto">
                            <div class="min-w-0 space-y-1.5" v-if="hasData">
                                <Label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Unduh Excel</Label>
                                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                                    <Button variant="outline" class="h-10 w-full min-w-0 justify-center border-green-200 bg-green-50 px-3 text-green-700 hover:bg-green-100 dark:border-green-800 dark:bg-green-950/30 dark:text-green-400 dark:hover:bg-green-900/50 sm:w-auto" @click="exportXlsx" title="Export Excel lengkap">
                                        <FileSpreadsheet class="mr-2 h-4 w-4 shrink-0" /> <span class="truncate">Lengkap</span>
                                    </Button>

                                    <Button
                                        v-for="segment in overall.segments"
                                        :key="segment.key"
                                        variant="outline"
                                        class="h-10 w-full min-w-0 justify-center border-green-200 bg-green-50 px-3 text-green-700 hover:bg-green-100 dark:border-green-800 dark:bg-green-950/30 dark:text-green-400 dark:hover:bg-green-900/50 sm:w-auto"
                                        :title="`Export Excel ${segment.label}`"
                                        @click="exportSegmentXlsx(segment.key)"
                                    >
                                        <FileSpreadsheet class="mr-2 h-4 w-4 shrink-0" /> <span class="truncate">{{ segment.label }}</span>
                                    </Button>
                                </div>
                            </div>

                            <div class="space-y-1.5" v-if="hasData">
                                <Label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Cetak</Label>
                                <Button variant="outline" class="h-10 w-full justify-center border-red-200 bg-red-50 px-3 text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/50 sm:w-auto" @click="exportPdf" title="Export PDF">
                                    <Printer class="mr-2 h-4 w-4 shrink-0" /> PDF
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <template v-if="!hasData">
                <Card variant="elevated">
                    <CardContent class="flex flex-col items-center justify-center py-16 text-center">
                        <BarChart3 class="mb-3 h-12 w-12 text-muted-foreground/50" />
                        <p class="text-muted-foreground">Tidak ada data dalam periode ini</p>
                    </CardContent>
                </Card>
            </template>

            <template v-else>
                <!-- ========== RINGKASAN KESELURUHAN ========== -->
                <section class="space-y-4 md:space-y-6">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h2 class="text-lg md:text-xl font-black tracking-tight text-primary">RINGKASAN KESELURUHAN</h2>
                    </div>

                    <!-- Overall Summary Cards -->
                    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
                        <StatCard variant="primary" class="border-none shadow-sm">
                            <template #title>Penjualan</template>
                            <template #value>
                                <p class="text-lg sm:text-2xl lg:text-3xl font-black break-words text-emerald-600 dark:text-emerald-400 tabular-nums">{{ formatCurrency(overall.income) }}</p>
                            </template>
                            <template #subtitle>{{ overall.order_count }} Transaksi</template>
                            <template #icon><TrendingUp class="h-6 w-6" /></template>
                        </StatCard>
                        <StatCard variant="primary" class="border-none shadow-sm">
                            <template #title>Total HPP</template>
                            <template #value>
                                <p class="text-lg sm:text-2xl lg:text-3xl font-black break-words text-orange-600 dark:text-orange-400 tabular-nums">{{ formatCurrency(overall.hpp) }}</p>
                            </template>
                            <template #subtitle>Modal Barang Terjual</template>
                            <template #icon><ShoppingBag class="h-6 w-6 text-orange-500" /></template>
                        </StatCard>
                        <StatCard variant="destructive" class="border-none shadow-sm">
                            <template #title>Pengeluaran</template>
                            <template #value>
                                <p class="text-lg sm:text-2xl lg:text-3xl font-black break-words text-destructive tabular-nums">{{ formatCurrency(overall.expenses) }}</p>
                            </template>
                            <template #subtitle>Operasional & Inventaris</template>
                            <template #icon><TrendingDown class="h-6 w-6" /></template>
                        </StatCard>
                        <StatCard variant="primary" class="border-none shadow-sm">
                            <template #title>Laba Kotor</template>
                            <template #value>
                                <p class="text-lg sm:text-2xl lg:text-3xl font-black break-words text-primary tabular-nums">{{ formatCurrency(overall.gross_profit) }}</p>
                            </template>
                            <template #subtitle>Sebelum Pengeluaran</template>
                            <template #icon><Package class="h-6 w-6" /></template>
                        </StatCard>
                        <StatCard :variant="overall.net >= 0 ? 'success' : 'destructive'" class="border-none shadow-sm ring-2" :class="overall.net >= 0 ? 'ring-emerald-500/20' : 'ring-destructive/20'">
                            <template #title>Laba Bersih</template>
                            <template #value>
                                <p class="text-lg sm:text-2xl lg:text-3xl font-black break-words tabular-nums" :class="overall.net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'">
                                    {{ formatCurrency(overall.net) }}
                                </p>
                            </template>
                            <template #subtitle>Profit Akhir Periode</template>
                            <template #icon><Wallet class="h-6 w-6" /></template>
                        </StatCard>
                    </div>
                    <!-- Overall Charts -->
                    <div class="grid gap-4 sm:gap-6 md:grid-cols-2">
                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-2">
                                <CardTitle class="text-base md:text-lg font-bold">Penjualan Harian</CardTitle>
                                <CardDescription class="text-xs">Statistik gabungan seluruh cabang</CardDescription>
                            </CardHeader>
                            <CardContent class="p-4 md:p-6 pt-0">
                                <div class="h-[220px] w-full sm:h-[260px] lg:h-[280px]">
                                    <Bar
                                        v-if="overall.chart_sales.labels.length > 0"
                                        :data="makeChartSalesConfig(overall.chart_sales.labels, overall.chart_sales.data)"
                                        :options="chartSalesOptions"
                                    />
                                    <div v-else class="flex h-full items-center justify-center text-muted-foreground italic">Tidak ada data penjualan</div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-2">
                                <CardTitle class="text-base md:text-lg font-bold">Pengeluaran per Kategori</CardTitle>
                                <CardDescription class="text-xs">Distribusi biaya operasional</CardDescription>
                            </CardHeader>
                            <CardContent class="p-4 md:p-6 pt-0">
                                <div class="h-[220px] w-full sm:h-[260px] lg:h-[280px]">
                                    <Doughnut
                                        v-if="overall.chart_expense_by_category.labels.length > 0"
                                        :data="makeChartExpenseConfig(overall.chart_expense_by_category.labels, overall.chart_expense_by_category.amounts)"
                                        :options="chartExpenseOptions"
                                    />
                                    <div v-else class="flex h-full items-center justify-center text-muted-foreground italic">Tidak ada data pengeluaran</div>
                                </div>
                            </CardContent>
                        </Card>

                    </div>

                    <!-- Overall Tables -->
                    <div class="grid gap-4 sm:gap-6 md:grid-cols-2">
                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-4">
                                <CardTitle class="text-base md:text-lg font-black">Produk Terlaris</CardTitle>
                                <CardDescription class="text-xs">Top 20 produk paling laku</CardDescription>
                            </CardHeader>
                            <CardContent class="p-0">
                                <!-- Di bawah md tabel diganti daftar kartu supaya tidak perlu digeser horizontal -->
                                <div class="max-h-[400px] divide-y overflow-y-auto md:hidden">
                                    <div v-for="(p, i) in overall.top_products" :key="i" class="flex items-start justify-between gap-3 px-4 py-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="break-words text-sm font-bold">{{ p.product_name }}</p>
                                            <p class="text-[11px] text-muted-foreground tabular-nums">{{ p.total_qty }} terjual</p>
                                        </div>
                                        <span class="shrink-0 whitespace-nowrap text-sm font-black tabular-nums text-primary">
                                            {{ formatCurrency(p.total_amount) }}
                                        </span>
                                    </div>
                                    <p v-if="overall.top_products.length === 0" class="px-4 py-12 text-center italic text-muted-foreground">
                                        Belum ada data penjualan
                                    </p>
                                </div>
                                <div class="hidden max-h-[400px] overflow-y-auto md:block">
                                    <table class="w-full text-xs md:text-sm">
                                        <thead class="sticky top-0 bg-muted/90 backdrop-blur-sm shadow-sm z-10">
                                            <tr class="text-left text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                                <th class="px-4 py-3">Nama Produk</th>
                                                <th class="px-4 py-3 text-center">Qty</th>
                                                <th class="px-4 py-3 text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr v-for="(p, i) in overall.top_products" :key="i" class="hover:bg-primary/5 transition-colors">
                                                <td class="px-4 py-3 font-bold">{{ p.product_name }}</td>
                                                <td class="px-4 py-3 text-center font-medium tabular-nums">{{ p.total_qty }}</td>
                                                <td class="px-4 py-3 text-right font-black text-primary tabular-nums">{{ formatCurrency(p.total_amount) }}</td>
                                            </tr>
                                            <tr v-if="overall.top_products.length === 0">
                                                <td colspan="3" class="px-4 py-12 text-center text-muted-foreground italic">Belum ada data penjualan</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                        <Card variant="elevated" class="overflow-hidden border-none shadow-sm">
                            <CardHeader class="p-4 md:p-6 pb-4">
                                <CardTitle class="text-base md:text-lg font-black">Penjualan per Kasir</CardTitle>
                                <CardDescription class="text-xs">Statistik performa kasir</CardDescription>
                            </CardHeader>
                            <CardContent class="p-0">
                                <!-- Di bawah md tabel diganti daftar kartu supaya tidak perlu digeser horizontal -->
                                <div class="max-h-[400px] divide-y overflow-y-auto md:hidden">
                                    <div v-for="(c, i) in overall.sales_by_cashier" :key="i" class="flex items-start justify-between gap-3 px-4 py-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="break-words text-sm font-bold">{{ c.cashier_name }}</p>
                                            <p class="text-[11px] text-muted-foreground tabular-nums">{{ c.order_count }} transaksi</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1">
                                            <span class="whitespace-nowrap text-sm font-black tabular-nums text-primary">
                                                {{ formatCurrency(c.total_amount) }}
                                            </span>
                                            <Button v-if="c.cashier_id" variant="ghost" size="icon" class="h-8 w-8 rounded-full" @click="viewCashier(c.cashier_id)">
                                                <Search class="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                    <p v-if="overall.sales_by_cashier.length === 0" class="px-4 py-12 text-center italic text-muted-foreground">
                                        Belum ada data kasir
                                    </p>
                                </div>
                                <div class="hidden max-h-[400px] overflow-y-auto md:block">
                                    <table class="w-full text-xs md:text-sm">
                                        <thead class="sticky top-0 bg-muted/90 backdrop-blur-sm shadow-sm z-10">
                                            <tr class="text-left text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                                <th class="px-4 py-3">Nama Kasir</th>
                                                <th class="px-4 py-3 text-center">Tx</th>
                                                <th class="px-4 py-3 text-right">Total</th>
                                                <th class="w-12"><span class="sr-only">Aksi</span></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr v-for="(c, i) in overall.sales_by_cashier" :key="i" class="hover:bg-primary/5 transition-colors">
                                                <td class="px-4 py-3 font-bold">{{ c.cashier_name }}</td>
                                                <td class="px-4 py-3 text-center font-medium tabular-nums">{{ c.order_count }}</td>
                                                <td class="px-4 py-3 text-right font-black text-primary tabular-nums">{{ formatCurrency(c.total_amount) }}</td>
                                                <td class="px-4 py-3 text-center">
                                                    <Button v-if="c.cashier_id" variant="ghost" size="icon" class="h-8 w-8 rounded-full" @click="viewCashier(c.cashier_id)">
                                                        <Search class="h-3.5 w-3.5" />
                                                    </Button>
                                                </td>
                                            </tr>
                                            <tr v-if="overall.sales_by_cashier.length === 0">
                                                <td colspan="4" class="px-4 py-12 text-center text-muted-foreground italic">Belum ada data kasir</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="grid gap-4 sm:gap-6 md:grid-cols-2">
                        <Card variant="elevated">
                            <CardHeader>
                                <CardTitle>Penjualan per Tipe Order</CardTitle>
                                <CardDescription>Dine In, Take Away, Walk In</CardDescription>
                            </CardHeader>
                            <CardContent class="p-0">
                                <!-- Di bawah md tabel diganti daftar kartu supaya tidak perlu digeser horizontal -->
                                <div class="divide-y md:hidden">
                                    <div v-for="(t, i) in overall.sales_by_type" :key="i" class="flex items-start justify-between gap-3 px-4 py-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="break-words text-sm font-medium">{{ t.label }}</p>
                                            <p class="text-[11px] text-muted-foreground tabular-nums">{{ t.count }} transaksi</p>
                                        </div>
                                        <span class="shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums">{{ formatCurrency(t.total) }}</span>
                                    </div>
                                    <p v-if="overall.sales_by_type.length === 0" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data</p>
                                </div>
                                <table class="hidden w-full text-sm md:table">
                                    <thead>
                                        <tr class="border-b bg-muted/50 text-left">
                                            <th class="px-4 py-3 font-medium text-muted-foreground">Tipe</th>
                                            <th class="px-4 py-3 font-medium text-muted-foreground text-center">Transaksi</th>
                                            <th class="px-4 py-3 font-medium text-muted-foreground text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(t, i) in overall.sales_by_type" :key="i" class="border-b hover:bg-muted/30">
                                            <td class="px-4 py-3 font-medium">{{ t.label }}</td>
                                            <td class="px-4 py-3 text-center">{{ t.count }}</td>
                                            <td class="px-4 py-3 text-right">{{ formatCurrency(t.total) }}</td>
                                        </tr>
                                        <tr v-if="overall.sales_by_type.length === 0">
                                            <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                        <Card variant="elevated">
                            <CardHeader>
                                <CardTitle>Penjualan per Metode Pembayaran</CardTitle>
                                <CardDescription>Tunai, QRIS, Transfer, E-Wallet</CardDescription>
                            </CardHeader>
                            <CardContent class="p-0">
                                <!-- Di bawah md tabel diganti daftar kartu supaya tidak perlu digeser horizontal -->
                                <div class="divide-y md:hidden">
                                    <div v-for="(p, i) in overall.sales_by_payment" :key="i" class="flex items-start justify-between gap-3 px-4 py-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="break-words text-sm font-medium">{{ p.label }}</p>
                                            <p class="text-[11px] text-muted-foreground tabular-nums">{{ p.count }} transaksi</p>
                                        </div>
                                        <span class="shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums">{{ formatCurrency(p.total) }}</span>
                                    </div>
                                    <p v-if="overall.sales_by_payment.length === 0" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data</p>
                                </div>
                                <table class="hidden w-full text-sm md:table">
                                    <thead>
                                        <tr class="border-b bg-muted/50 text-left">
                                            <th class="px-4 py-3 font-medium text-muted-foreground">Metode</th>
                                            <th class="px-4 py-3 font-medium text-muted-foreground text-center">Transaksi</th>
                                            <th class="px-4 py-3 font-medium text-muted-foreground text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(p, i) in overall.sales_by_payment" :key="i" class="border-b hover:bg-muted/30">
                                            <td class="px-4 py-3 font-medium">{{ p.label }}</td>
                                            <td class="px-4 py-3 text-center">{{ p.count }}</td>
                                            <td class="px-4 py-3 text-right">{{ formatCurrency(p.total) }}</td>
                                        </tr>
                                        <tr v-if="overall.sales_by_payment.length === 0">
                                            <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </div>

                    <Card variant="elevated">
                        <CardHeader>
                            <CardTitle>Detail Pengeluaran per Kategori</CardTitle>
                            <CardDescription>Jumlah transaksi dan total per kategori</CardDescription>
                        </CardHeader>
                        <CardContent class="p-0">
                            <!-- Di bawah md tabel diganti daftar kartu supaya tidak perlu digeser horizontal -->
                            <div class="divide-y md:hidden">
                                <div v-for="(e, i) in overall.expense_by_category_detail" :key="i" class="flex items-start justify-between gap-3 px-4 py-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="break-words text-sm font-medium">{{ e.category_label }}</p>
                                        <p class="text-[11px] text-muted-foreground tabular-nums">{{ e.count }} transaksi</p>
                                    </div>
                                    <span class="shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums">{{ formatCurrency(e.total) }}</span>
                                </div>
                                <p v-if="overall.expense_by_category_detail.length === 0" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data</p>
                            </div>
                            <table class="hidden w-full text-sm md:table">
                                <thead>
                                    <tr class="border-b bg-muted/50 text-left">
                                        <th class="px-4 py-3 font-medium text-muted-foreground">Kategori</th>
                                        <th class="px-4 py-3 font-medium text-muted-foreground text-center">Jumlah Transaksi</th>
                                        <th class="px-4 py-3 font-medium text-muted-foreground text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(e, i) in overall.expense_by_category_detail" :key="i" class="border-b hover:bg-muted/30">
                                        <td class="px-4 py-3 font-medium">{{ e.category_label }}</td>
                                        <td class="px-4 py-3 text-center">{{ e.count }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatCurrency(e.total) }}</td>
                                    </tr>
                                    <tr v-if="overall.expense_by_category_detail.length === 0">
                                        <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data</td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </section>

            </template>
        </div>
    </AdminLayout>
</template>
