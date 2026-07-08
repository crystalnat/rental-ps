<script setup lang="ts">
import { ref, computed } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { Plus, Trash2, Printer, FileText, RotateCcw, Search, Save } from 'lucide-vue-next'

interface StoreInfo {
    name: string
    address: string | null
    city: string | null
    phone: string | null
    email: string | null
    logo: string | null
}

interface ProductOption {
    id: number
    name: string
    unit: string
    sell_price: number
}

const props = defineProps<{
    store: StoreInfo | null
    cashierName: string
    products: ProductOption[]
}>()

// ─── Form state ────────────────────────────────────────────────────────────────
interface LineItem {
    id: number
    product_id: number | null
    name: string
    qty: number
    unit: string
    price: number
}

let nextId = 1

// toISOString() memakai UTC (geser -7 jam di WIB). Ini kembalikan waktu LOKAL untuk input datetime-local & kode struk.
function localDateTime(d = new Date()): string {
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}

const form = ref({
    invoiceCode: 'MNL-' + localDateTime().slice(0, 10).replace(/-/g, '') + '-' + String(Math.floor(Math.random() * 9000) + 1000),
    date: localDateTime(),
    customerName: '',
    cashierName: props.cashierName,
    paymentMethod: 'Tunai',
    cashReceived: 0,
    discount: 0,
    notes: '',
    items: [{ id: nextId++, product_id: null, name: '', qty: 1, unit: 'pcs', price: 0 }] as LineItem[],
})

const paymentOptions = ['Tunai', 'QRIS', 'Transfer Bank', 'Kartu Debit', 'Kartu Kredit', 'Lainnya']

// ─── Computed ──────────────────────────────────────────────────────────────────
const subtotal = computed(() =>
    form.value.items.reduce((s, i) => s + i.qty * i.price, 0)
)
const finalAmount = computed(() => Math.max(0, subtotal.value - form.value.discount))
const changeAmount = computed(() =>
    form.value.paymentMethod === 'Tunai'
        ? Math.max(0, form.value.cashReceived - finalAmount.value)
        : 0
)
const formattedDate = computed(() => {
    try {
        return new Date(form.value.date).toLocaleString('id-ID', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        })
    } catch { return form.value.date }
})

// ─── Item helpers ──────────────────────────────────────────────────────────────
function addItem() {
    form.value.items.push({ id: nextId++, product_id: null, name: '', qty: 1, unit: 'pcs', price: 0 })
}

function removeItem(id: number) {
    if (form.value.items.length === 1) return
    form.value.items = form.value.items.filter(i => i.id !== id)
    closeDropdown()
}

// ─── Product autocomplete ───────────────────────────────────────────────────────
const activeDropdownId = ref<number | null>(null)
const searchQuery = ref<Record<number, string>>({})

function getFilteredProducts(itemId: number) {
    const q = (searchQuery.value[itemId] ?? '').toLowerCase()
    if (!q) return props.products.slice(0, 8)
    return props.products.filter(p => p.name.toLowerCase().includes(q)).slice(0, 8)
}

function openDropdown(itemId: number) {
    activeDropdownId.value = itemId
}

function closeDropdown() {
    activeDropdownId.value = null
}

function selectProduct(itemId: number, product: ProductOption) {
    const item = form.value.items.find(i => i.id === itemId)
    if (item) {
        item.product_id = product.id
        item.name       = product.name
        item.unit       = product.unit
        item.price      = product.sell_price
        searchQuery.value[itemId] = product.name
    }
    closeDropdown()
}

function onItemNameInput(itemId: number, value: string) {
    const item = form.value.items.find(i => i.id === itemId)
    if (item) {
        item.name = value
        item.product_id = null  // reset jika diketik manual
    }
    searchQuery.value[itemId] = value
    activeDropdownId.value = itemId
}

function resetForm() {
    form.value.invoiceCode = 'MNL-' + localDateTime().slice(0, 10).replace(/-/g, '') + '-' + String(Math.floor(Math.random() * 9000) + 1000)
    form.value.date = localDateTime()
    form.value.customerName = ''
    form.value.cashierName = props.cashierName
    form.value.paymentMethod = 'Tunai'
    form.value.cashReceived = 0
    form.value.discount = 0
    form.value.notes = ''
    nextId = 1
    form.value.items = [{ id: nextId++, product_id: null, name: '', qty: 1, unit: 'pcs', price: 0 }]
    searchQuery.value = {}
}

// ─── Save to DB ────────────────────────────────────────────────────────────────
const saving = ref(false)

function saveAndPrint() {
    const hasItems = form.value.items.some(i => i.name.trim() && i.price > 0)
    if (!hasItems) {
        alert('Isi minimal satu item dengan nama dan harga.')
        return
    }
    saving.value = true
    router.post('/admin/manual-invoice', {
        invoice_code:   form.value.invoiceCode,
        date:           form.value.date,
        customer_name:  form.value.customerName || null,
        cashier_name:   form.value.cashierName,
        payment_method: form.value.paymentMethod,
        cash_received:  form.value.paymentMethod === 'Tunai' ? form.value.cashReceived : null,
        discount:       form.value.discount,
        notes:          form.value.notes || null,
        items: form.value.items
            .filter(i => i.name.trim() && i.price > 0)
            .map(i => ({
                product_id: i.product_id,
                name:       i.name,
                qty:        i.qty,
                unit:       i.unit,
                price:      i.price,
            })),
    }, {
        onError: () => { saving.value = false },
    })
}

// ─── Formatters ────────────────────────────────────────────────────────────────
function formatRp(n: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n)
}

// ─── Print ─────────────────────────────────────────────────────────────────────
const printMode = ref<'receipt' | 'invoice'>('receipt')

function setPageSize(size: string) {
    let style = document.getElementById('dyn-page-size') as HTMLStyleElement | null
    if (!style) {
        style = document.createElement('style')
        style.id = 'dyn-page-size'
        document.head.appendChild(style)
    }
    style.textContent = `@media print { @page { size: ${size}; margin: 0; } }`
}

function printReceipt() {
    printMode.value = 'receipt'
    setPageSize('80mm auto')
    document.body.setAttribute('data-print', 'receipt')
    setTimeout(() => {
        window.print()
        setTimeout(() => document.body.removeAttribute('data-print'), 1000)
    }, 150)
}
function printInvoice() {
    printMode.value = 'invoice'
    setPageSize('A4')
    document.body.setAttribute('data-print', 'invoice')
    setTimeout(() => {
        window.print()
        setTimeout(() => document.body.removeAttribute('data-print'), 1000)
    }, 150)
}
</script>

<template>
    <Head title="Struk Manual" />
    <AdminLayout>
        <div class="flex h-full flex-col gap-0">
            <!-- Header -->
            <div class="no-print flex items-center justify-between border-b px-6 py-4">
                <div>
                    <h1 class="text-lg font-bold">Struk Manual</h1>
                    <p class="text-xs text-muted-foreground">Buat struk manual — tersimpan otomatis ke riwayat penjualan</p>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium hover:bg-muted"
                        @click="resetForm"
                    >
                        <RotateCcw class="h-4 w-4" />
                        Reset
                    </button>
                    <button
                        type="button"
                        :disabled="saving"
                        class="flex items-center gap-1.5 rounded-lg border bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-700 disabled:opacity-50"
                        @click="saveAndPrint"
                    >
                        <Save class="h-4 w-4" />
                        {{ saving ? 'Menyimpan...' : 'Simpan & Cetak Struk' }}
                    </button>
                </div>
            </div>

            <!-- Body: Form + Preview -->
            <div class="no-print flex min-h-0 flex-1 overflow-hidden">
                <!-- Left: Form -->
                <div class="w-[420px] shrink-0 overflow-y-auto border-r bg-muted/20 p-5">
                    <div class="space-y-5">
                        <!-- Info dasar -->
                        <div class="space-y-3">
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Info Struk</h2>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="col-span-2 space-y-1">
                                    <label class="text-xs font-medium">No. Struk</label>
                                    <input v-model="form.invoiceCode" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" />
                                </div>
                                <div class="col-span-2 space-y-1">
                                    <label class="text-xs font-medium">Tanggal & Waktu</label>
                                    <input v-model="form.date" type="datetime-local" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium">Kasir</label>
                                    <input v-model="form.cashierName" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium">Pelanggan (opsional)</label>
                                    <input v-model="form.customerName" placeholder="Nama pelanggan" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" />
                                </div>
                            </div>
                        </div>

                        <!-- Items -->
                        <div class="space-y-3">
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Item</h2>
                            <div class="space-y-2">
                                <div
                                    v-for="item in form.items"
                                    :key="item.id"
                                    class="rounded-xl border bg-background p-3 space-y-2"
                                >
                                    <!-- Name with product autocomplete -->
                                    <div class="relative flex gap-2">
                                        <div class="relative flex-1">
                                            <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                            <input
                                                :value="item.name"
                                                placeholder="Cari / ketik nama item..."
                                                class="w-full rounded-lg border py-1.5 pl-8 pr-3 text-sm"
                                                @input="onItemNameInput(item.id, ($event.target as HTMLInputElement).value)"
                                                @focus="openDropdown(item.id)"
                                                @blur="() => window.setTimeout(closeDropdown, 150)"
                                            />
                                            <!-- Dropdown -->
                                            <div
                                                v-if="activeDropdownId === item.id && getFilteredProducts(item.id).length"
                                                class="absolute left-0 top-full z-50 mt-1 w-full overflow-hidden rounded-xl border bg-popover shadow-lg"
                                            >
                                                <button
                                                    v-for="p in getFilteredProducts(item.id)"
                                                    :key="p.id"
                                                    type="button"
                                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent"
                                                    @mousedown.prevent="selectProduct(item.id, p)"
                                                >
                                                    <span class="font-medium">{{ p.name }}</span>
                                                    <span class="ml-2 shrink-0 text-xs text-muted-foreground">{{ formatRp(p.sell_price) }} / {{ p.unit }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="rounded-lg border p-1.5 text-destructive hover:bg-destructive/10"
                                            :disabled="form.items.length === 1"
                                            @click="removeItem(item.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="space-y-0.5">
                                            <label class="text-[10px] text-muted-foreground">Qty</label>
                                            <input
                                                v-model.number="item.qty"
                                                type="number" min="0.01" step="0.01"
                                                class="w-full rounded-lg border px-2 py-1.5 text-sm"
                                            />
                                        </div>
                                        <div class="space-y-0.5">
                                            <label class="text-[10px] text-muted-foreground">Satuan</label>
                                            <input
                                                v-model="item.unit"
                                                placeholder="pcs"
                                                class="w-full rounded-lg border px-2 py-1.5 text-sm"
                                            />
                                        </div>
                                        <div class="space-y-0.5">
                                            <label class="text-[10px] text-muted-foreground">Harga</label>
                                            <input
                                                v-model.number="item.price"
                                                type="number" min="0"
                                                class="w-full rounded-lg border px-2 py-1.5 text-sm"
                                            />
                                        </div>
                                    </div>
                                    <div class="text-right text-xs font-semibold text-primary">
                                        {{ formatRp(item.qty * item.price) }}
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed py-2 text-sm text-muted-foreground hover:border-primary hover:text-primary"
                                @click="addItem"
                            >
                                <Plus class="h-4 w-4" />
                                Tambah Item
                            </button>
                        </div>

                        <!-- Pembayaran -->
                        <div class="space-y-3">
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pembayaran</h2>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="col-span-2 space-y-1">
                                    <label class="text-xs font-medium">Metode Bayar</label>
                                    <select v-model="form.paymentMethod" class="w-full rounded-lg border bg-background px-3 py-2 text-sm">
                                        <option v-for="opt in paymentOptions" :key="opt" :value="opt">{{ opt }}</option>
                                    </select>
                                </div>
                                <div class="col-span-2 space-y-1">
                                    <label class="text-xs font-medium">Diskon</label>
                                    <input v-model.number="form.discount" type="number" min="0" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" />
                                </div>
                                <div v-if="form.paymentMethod === 'Tunai'" class="col-span-2 space-y-1">
                                    <label class="text-xs font-medium">Uang Diterima</label>
                                    <input v-model.number="form.cashReceived" type="number" min="0" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" />
                                </div>
                                <div class="col-span-2 space-y-1">
                                    <label class="text-xs font-medium">Catatan (opsional)</label>
                                    <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border bg-background px-3 py-2 text-sm resize-none" />
                                </div>
                            </div>
                        </div>

                        <!-- Total Summary -->
                        <div class="rounded-xl border bg-background p-4 space-y-1.5 text-sm">
                            <div class="flex justify-between text-muted-foreground">
                                <span>Subtotal</span>
                                <span>{{ formatRp(subtotal) }}</span>
                            </div>
                            <div v-if="form.discount > 0" class="flex justify-between text-muted-foreground">
                                <span>Diskon</span>
                                <span class="text-destructive">-{{ formatRp(form.discount) }}</span>
                            </div>
                            <div class="flex justify-between border-t pt-1.5 font-bold">
                                <span>Total</span>
                                <span class="text-primary">{{ formatRp(finalAmount) }}</span>
                            </div>
                            <div v-if="form.paymentMethod === 'Tunai' && form.cashReceived > 0" class="flex justify-between text-muted-foreground text-xs">
                                <span>Kembalian</span>
                                <span>{{ formatRp(changeAmount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Live Preview -->
                <div class="flex flex-1 items-start justify-center overflow-y-auto bg-slate-100 p-8 dark:bg-slate-900">
                    <!-- Receipt Preview (always visible, acts as print source too) -->
                    <div class="receipt-preview shadow-lg">
                        <!-- Header -->
                        <div style="text-align:center; margin-bottom:6px;">
                            <img v-if="store?.logo" :src="store.logo" style="width:48px;height:48px;object-fit:contain;margin:0 auto 6px;display:block;" />
                            <div style="font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">{{ store?.name ?? 'Nama Toko' }}</div>
                            <div v-if="store?.address" style="font-size:10px;color:#333;">{{ store.address }}</div>
                            <div v-if="store?.city" style="font-size:10px;color:#333;">{{ store.city }}</div>
                            <div v-if="store?.phone" style="font-size:10px;color:#333;">Telp: {{ store.phone }}</div>
                        </div>

                        <div class="rp-divider" />

                        <!-- Order Info -->
                        <div style="font-size:11px;">
                            <div class="rp-row"><span>No. Struk</span><span style="font-weight:700;">{{ form.invoiceCode }}</span></div>
                            <div class="rp-row"><span>Tanggal</span><span>{{ formattedDate }}</span></div>
                            <div v-if="form.cashierName" class="rp-row"><span>Kasir</span><span>{{ form.cashierName }}</span></div>
                            <div v-if="form.customerName" class="rp-row"><span>Pelanggan</span><span>{{ form.customerName }}</span></div>
                            <div class="rp-row"><span>Bayar</span><span>{{ form.paymentMethod }}</span></div>
                        </div>

                        <div class="rp-divider" />

                        <!-- Items -->
                        <div style="font-size:11px;">
                            <template v-for="item in form.items" :key="item.id">
                                <div v-if="item.name || item.price > 0" style="margin-bottom:6px;">
                                    <div style="font-weight:600;">{{ item.name || '(item)' }}</div>
                                    <div class="rp-row" style="font-size:10px;color:#333;">
                                        <span>{{ item.qty }} {{ item.unit }} × {{ formatRp(item.price) }}</span>
                                        <span style="font-weight:600;">{{ formatRp(item.qty * item.price) }}</span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="rp-divider" />

                        <!-- Summary -->
                        <div style="font-size:11px;">
                            <div class="rp-row"><span>Subtotal</span><span>{{ formatRp(subtotal) }}</span></div>
                            <div v-if="form.discount > 0" class="rp-row"><span>Diskon</span><span>-{{ formatRp(form.discount) }}</span></div>
                            <div class="rp-divider-dashed" />
                            <div class="rp-row" style="font-size:14px;font-weight:700;margin:4px 0;">
                                <span>TOTAL</span>
                                <span>{{ formatRp(finalAmount) }}</span>
                            </div>
                            <div v-if="form.paymentMethod === 'Tunai' && form.cashReceived > 0" class="rp-row">
                                <span>Tunai</span><span>{{ formatRp(form.cashReceived) }}</span>
                            </div>
                            <div v-if="form.paymentMethod === 'Tunai' && changeAmount > 0" class="rp-row">
                                <span>Kembalian</span><span>{{ formatRp(changeAmount) }}</span>
                            </div>
                            <div v-else-if="form.paymentMethod !== 'Tunai'" class="rp-row">
                                <span>{{ form.paymentMethod }}</span><span>{{ formatRp(finalAmount) }}</span>
                            </div>
                        </div>

                        <div class="rp-divider" />

                        <!-- Footer -->
                        <div style="text-align:center;margin-top:4px;font-size:11px;">
                            <p v-if="form.notes" style="font-size:9px;color:#666;font-style:italic;margin-bottom:6px;">Catatan: {{ form.notes }}</p>
                            <p style="font-size:14px;font-weight:700;margin:6px 0 2px;">Terima Kasih</p>
                            <p style="font-size:10px;color:#666;margin:0;">Selamat Menikmati ✨</p>
                            <p style="font-size:9px;color:#888;margin-top:6px;">*Struk dibuat secara manual*</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── PRINT AREA: Receipt (80mm) ─────────────────────────────────── -->
            <div v-if="printMode === 'receipt'" class="print-only receipt-print">
                <div class="receipt">
                    <div style="text-align:center; margin-bottom:6px;">
                        <img v-if="store?.logo" :src="store.logo" style="width:48px;height:48px;object-fit:contain;margin:0 auto 6px;display:block;" />
                        <div style="font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">{{ store?.name ?? 'Nama Toko' }}</div>
                        <div v-if="store?.address" style="font-size:10px;color:#333;">{{ store.address }}</div>
                        <div v-if="store?.city" style="font-size:10px;color:#333;">{{ store.city }}</div>
                        <div v-if="store?.phone" style="font-size:10px;color:#333;">Telp: {{ store.phone }}</div>
                    </div>
                    <div class="rp-divider" />
                    <div style="font-size:11px;">
                        <div class="rp-row"><span>No. Struk</span><span style="font-weight:700;">{{ form.invoiceCode }}</span></div>
                        <div class="rp-row"><span>Tanggal</span><span>{{ formattedDate }}</span></div>
                        <div v-if="form.cashierName" class="rp-row"><span>Kasir</span><span>{{ form.cashierName }}</span></div>
                        <div v-if="form.customerName" class="rp-row"><span>Pelanggan</span><span>{{ form.customerName }}</span></div>
                        <div class="rp-row"><span>Bayar</span><span>{{ form.paymentMethod }}</span></div>
                    </div>
                    <div class="rp-divider" />
                    <div style="font-size:11px;">
                        <template v-for="item in form.items" :key="item.id">
                            <div v-if="item.name || item.price > 0" style="margin-bottom:6px;">
                                <div style="font-weight:600;">{{ item.name || '(item)' }}</div>
                                <div class="rp-row" style="font-size:10px;color:#333;">
                                    <span>{{ item.qty }} {{ item.unit }} × {{ formatRp(item.price) }}</span>
                                    <span style="font-weight:600;">{{ formatRp(item.qty * item.price) }}</span>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="rp-divider" />
                    <div style="font-size:11px;">
                        <div class="rp-row"><span>Subtotal</span><span>{{ formatRp(subtotal) }}</span></div>
                        <div v-if="form.discount > 0" class="rp-row"><span>Diskon</span><span>-{{ formatRp(form.discount) }}</span></div>
                        <div class="rp-divider-dashed" />
                        <div class="rp-row" style="font-size:14px;font-weight:700;margin:4px 0;"><span>TOTAL</span><span>{{ formatRp(finalAmount) }}</span></div>
                        <div v-if="form.paymentMethod === 'Tunai' && form.cashReceived > 0" class="rp-row"><span>Tunai</span><span>{{ formatRp(form.cashReceived) }}</span></div>
                        <div v-if="form.paymentMethod === 'Tunai' && changeAmount > 0" class="rp-row"><span>Kembalian</span><span>{{ formatRp(changeAmount) }}</span></div>
                        <div v-else-if="form.paymentMethod !== 'Tunai'" class="rp-row"><span>{{ form.paymentMethod }}</span><span>{{ formatRp(finalAmount) }}</span></div>
                    </div>
                    <div class="rp-divider" />
                    <div style="text-align:center;margin-top:4px;">
                        <p v-if="form.notes" style="font-size:9px;color:#666;font-style:italic;margin-bottom:6px;">Catatan: {{ form.notes }}</p>
                        <p style="font-size:14px;font-weight:700;margin:6px 0 2px;">Terima Kasih</p>
                        <p style="font-size:10px;color:#666;margin:0;">Selamat Menikmati ✨</p>
                        <p style="font-size:9px;color:#888;margin-top:6px;">*Struk dibuat secara manual*</p>
                    </div>
                </div>
            </div>

            <!-- ─── PRINT AREA: Invoice A4 ──────────────────────────────────────── -->
            <div v-if="printMode === 'invoice'" class="print-only invoice-print">
                <div class="invoice-page">
                    <!-- Invoice Header -->
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;">
                        <div>
                            <img v-if="store?.logo" :src="store.logo" style="height:56px;object-fit:contain;margin-bottom:8px;display:block;" />
                            <div style="font-size:20px;font-weight:700;">{{ store?.name ?? 'Nama Toko' }}</div>
                            <div v-if="store?.address" style="font-size:12px;color:#555;margin-top:2px;">{{ store.address }}</div>
                            <div v-if="store?.city" style="font-size:12px;color:#555;">{{ store.city }}</div>
                            <div v-if="store?.phone" style="font-size:12px;color:#555;">Telp: {{ store.phone }}</div>
                            <div v-if="store?.email" style="font-size:12px;color:#555;">{{ store.email }}</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:28px;font-weight:800;color:#16a34a;letter-spacing:-1px;">FAKTUR</div>
                            <div style="font-size:13px;font-weight:600;margin-top:6px;">No. {{ form.invoiceCode }}</div>
                            <div style="font-size:12px;color:#555;margin-top:2px;">Tanggal: {{ formattedDate }}</div>
                            <div v-if="form.cashierName" style="font-size:12px;color:#555;">Kasir: {{ form.cashierName }}</div>
                            <div v-if="form.customerName" style="font-size:12px;font-weight:600;margin-top:4px;">Kepada: {{ form.customerName }}</div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px;">
                        <thead>
                            <tr style="background:#16a34a;color:white;">
                                <th style="padding:8px 10px;text-align:left;border-radius:4px 0 0 0;">No</th>
                                <th style="padding:8px 10px;text-align:left;">Nama Item</th>
                                <th style="padding:8px 10px;text-align:right;">Qty</th>
                                <th style="padding:8px 10px;text-align:left;">Sat.</th>
                                <th style="padding:8px 10px;text-align:right;">Harga Satuan</th>
                                <th style="padding:8px 10px;text-align:right;border-radius:0 4px 0 0;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(item, idx) in form.items" :key="item.id">
                                <tr v-if="item.name || item.price > 0" :style="{ background: idx % 2 === 0 ? '#f9fafb' : 'white' }">
                                    <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;">{{ idx + 1 }}</td>
                                    <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;font-weight:500;">{{ item.name || '(item)' }}</td>
                                    <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:right;">{{ item.qty }}</td>
                                    <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;">{{ item.unit }}</td>
                                    <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:right;">{{ formatRp(item.price) }}</td>
                                    <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:600;">{{ formatRp(item.qty * item.price) }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <!-- Summary Box -->
                    <div style="display:flex;justify-content:flex-end;margin-bottom:32px;">
                        <div style="width:260px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:13px;">
                            <div style="display:flex;justify-content:space-between;padding:8px 14px;background:#f9fafb;">
                                <span>Subtotal</span><span>{{ formatRp(subtotal) }}</span>
                            </div>
                            <div v-if="form.discount > 0" style="display:flex;justify-content:space-between;padding:8px 14px;">
                                <span>Diskon</span><span style="color:#dc2626;">-{{ formatRp(form.discount) }}</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:#16a34a;color:white;font-weight:700;font-size:15px;">
                                <span>TOTAL</span><span>{{ formatRp(finalAmount) }}</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:8px 14px;background:#f9fafb;">
                                <span>Metode Bayar</span><span style="font-weight:600;">{{ form.paymentMethod }}</span>
                            </div>
                            <div v-if="form.paymentMethod === 'Tunai' && form.cashReceived > 0" style="display:flex;justify-content:space-between;padding:8px 14px;">
                                <span>Uang Diterima</span><span>{{ formatRp(form.cashReceived) }}</span>
                            </div>
                            <div v-if="form.paymentMethod === 'Tunai' && changeAmount > 0" style="display:flex;justify-content:space-between;padding:8px 14px;">
                                <span>Kembalian</span><span>{{ formatRp(changeAmount) }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="form.notes" style="margin-bottom:24px;font-size:12px;color:#555;">
                        <strong>Catatan:</strong> {{ form.notes }}
                    </div>

                    <!-- Signatures -->
                    <div style="display:flex;justify-content:space-between;margin-top:40px;font-size:12px;">
                        <div style="text-align:center;width:160px;">
                            <div style="border-bottom:1px solid #333;margin-bottom:4px;height:48px;"></div>
                            <div style="font-weight:600;">Kasir</div>
                            <div style="color:#555;">{{ form.cashierName }}</div>
                        </div>
                        <div style="text-align:center;width:160px;">
                            <div style="border-bottom:1px solid #333;margin-bottom:4px;height:48px;"></div>
                            <div style="font-weight:600;">Pelanggan</div>
                            <div style="color:#555;">{{ form.customerName || '_______________' }}</div>
                        </div>
                    </div>

                    <div style="margin-top:32px;text-align:center;font-size:10px;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:12px;">
                        Dokumen ini dibuat secara manual — *Struk Manual*
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
/* ─── Live Preview (receipt card shown on screen) ─────────────────────────── */
.receipt-preview {
    width: 80mm;
    background: white;
    padding: 10mm 5mm;
    font-family: 'Courier New', 'Consolas', monospace;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
    border-radius: 4px;
}

.rp-divider {
    border-top: 1px solid #000;
    margin: 8px 0;
}
.rp-divider-dashed {
    border-top: 1px dashed #666;
    margin: 6px 0;
}
.rp-row {
    display: flex;
    justify-content: space-between;
    margin: 2px 0;
}

/* ─── Print: hide form, show only print area ──────────────────────────────── */
.print-only {
    display: none;
}

/* ─── Receipt print (80mm) ────────────────────────────────────────────────── */
.receipt {
    width: 80mm;
    font-family: 'Courier New', 'Consolas', monospace;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
    padding: 4mm 3mm;
}

/* ─── Invoice print (A4) ──────────────────────────────────────────────────── */
.invoice-page {
    width: 210mm;
    min-height: 270mm;
    padding: 20mm 18mm;
    font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
    color: #111;
    font-size: 13px;
    line-height: 1.5;
}
</style>

<style>
@media print {
    /* Hide all page chrome */
    body[data-print] * {
        visibility: hidden !important;
    }

    /* Show only the active print block */
    body[data-print="receipt"] .receipt-print,
    body[data-print="receipt"] .receipt-print * {
        visibility: visible !important;
    }
    body[data-print="receipt"] .receipt-print {
        display: block !important;
        position: fixed !important;
        top: 0; left: 0;
    }

    body[data-print="invoice"] .invoice-print,
    body[data-print="invoice"] .invoice-print * {
        visibility: visible !important;
    }
    body[data-print="invoice"] .invoice-print {
        display: block !important;
        position: fixed !important;
        top: 0; left: 0;
    }

    @page { margin: 0; }
}
</style>
