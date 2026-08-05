<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { ArrowLeft, CheckCircle2, XCircle, Box, Pencil, Calendar, MapPin, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { ref } from 'vue'

interface PurchaseOrderItem {
    id: number
    product: { id: number; name: string; sku: string | null; unit: string }
    quantity: string | number
    buy_price: string | number
    subtotal: string | number
}

interface PurchaseOrder {
    id: number
    po_number: string
    store_id: number
    supplier_id: number
    status: 'pending' | 'received' | 'cancelled'
    total_amount: number
    notes: string | null
    expected_date: string | null
    created_at: string
    received_at: string | null
    creator: { id: number; name: string } | null
    store: { id: number; name: string; address: string | null }
    supplier: { id: number; name: string; phone: string | null; email: string | null }
    items: PurchaseOrderItem[]
}

const props = defineProps<{
    purchaseOrder: PurchaseOrder
}>()

function formatCurrency(value: string | number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value))
}

function formatDate(dateStr: string | null, includeTime = false) {
    if (!dateStr) return '-'
    const opts: Intl.DateTimeFormatOptions = { 
        year: 'numeric', month: 'short', day: 'numeric',
        ...(includeTime && { hour: '2-digit', minute: '2-digit' })
    }
    return new Date(dateStr).toLocaleDateString('id-ID', opts)
}

function getStatusLabel(status: string) {
    switch (status) {
        case 'pending': return 'MENUNGGU'
        case 'received': return 'DITERIMA'
        case 'cancelled': return 'DIBATALKAN'
        default: return status.toUpperCase()
    }
}

function getStatusColor(status: string) {
    switch (status) {
        case 'pending': return 'secondary'
        case 'received': return 'success'
        case 'cancelled': return 'destructive'
        default: return 'outline'
    }
}

const showReceiveDialog = ref(false)
const showCancelDialog = ref(false)
const processing = ref(false)

const actionForm = useForm({})

function receivePO() {
    processing.value = true
    showReceiveDialog.value = false // Sesuai pattern Categories: Tutup modal dulu
    
    actionForm.post(`/admin/purchase-orders/${props.purchaseOrder.id}/receive`, {
        preserveScroll: true,
        onError: () => {
             // Jika error, baru buka lagi modalnya
            showReceiveDialog.value = true 
        },
        onFinish: () => {
            processing.value = false
        }
    })
}

function cancelPO() {
    processing.value = true
    showCancelDialog.value = false // Sesuai pattern Categories
    
    actionForm.post(`/admin/purchase-orders/${props.purchaseOrder.id}/cancel`, {
        preserveScroll: true,
        onError: () => {
            showCancelDialog.value = true
        },
        onFinish: () => {
            processing.value = false
        }
    })
}

function goBack() {
    router.visit('/admin/purchase-orders')
}
</script>

<template>
    <Head :title="`Detail PO ${purchaseOrder.po_number}`" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header Interna -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <Button variant="ghost" size="sm" @click="goBack" class="self-start px-0 text-foreground hover:bg-transparent">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>

                <div v-if="purchaseOrder.status === 'pending'" class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
                    <Button variant="outline" size="sm" class="w-full sm:w-auto" @click="router.visit(`/admin/purchase-orders/${purchaseOrder.id}/edit`)" :disabled="processing">
                        <Pencil class="mr-2 h-4 w-4" /> Edit
                    </Button>
                    <Button variant="destructive" size="sm" class="w-full sm:w-auto" @click="showCancelDialog = true" :disabled="processing">
                        <XCircle class="mr-2 h-4 w-4" /> Batal
                    </Button>
                    <Button size="sm" @click="showReceiveDialog = true" class="w-full bg-green-600 text-white hover:bg-green-700 sm:w-auto" :disabled="processing">
                        <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                        <CheckCircle2 v-else class="mr-2 h-4 w-4" />
                        Terima Barang
                    </Button>
                </div>
            </div>

            <!-- Title & Status -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-foreground break-words sm:text-2xl">PO: {{ purchaseOrder.po_number }}</h1>
                    <p class="text-sm text-muted-foreground break-words">{{ purchaseOrder.store.name }} · {{ purchaseOrder.supplier.name }}</p>
                </div>
                <Badge :variant="getStatusColor(purchaseOrder.status) as any" class="shrink-0 whitespace-nowrap px-4 py-1 text-sm">
                    {{ getStatusLabel(purchaseOrder.status) }}
                </Badge>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Info Cards -->
                <Card>
                    <CardContent class="p-4 text-foreground sm:p-6">
                        <h3 class="mb-4 flex items-center gap-2 border-b pb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <MapPin class="h-4 w-4 shrink-0" /> Lokasi & Supplier
                        </h3>
                        <div class="space-y-4">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase text-muted-foreground">Cabang Tujuan</p>
                                <p class="font-medium break-words">{{ purchaseOrder.store.name }}</p>
                                <p class="mt-1 text-xs text-muted-foreground break-words" v-if="purchaseOrder.store.address">{{ purchaseOrder.store.address }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase text-muted-foreground">Supplier</p>
                                <p class="font-medium break-words">{{ purchaseOrder.supplier.name }}</p>
                                <p class="mt-1 text-xs text-muted-foreground break-all">
                                    {{ purchaseOrder.supplier.phone || '-' }} · {{ purchaseOrder.supplier.email || '-' }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4 text-foreground sm:p-6">
                        <h3 class="mb-4 flex items-center gap-2 border-b pb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <Calendar class="h-4 w-4 shrink-0" /> Detail Dokumen
                        </h3>
                        <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase text-muted-foreground">Tgl Dibuat</p>
                                <p class="font-medium break-words">{{ formatDate(purchaseOrder.created_at, true) }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase text-muted-foreground">Estimasi Datang</p>
                                <p class="font-medium break-words">{{ formatDate(purchaseOrder.expected_date) }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase text-muted-foreground">Admin</p>
                                <p class="font-medium break-words">{{ purchaseOrder.creator?.name || 'Sistem' }}</p>
                            </div>
                            <div v-if="purchaseOrder.status === 'received'" class="min-w-0">
                                <p class="text-[10px] font-bold uppercase text-green-600">Waktu Terima</p>
                                <p class="font-bold text-green-700 break-words dark:text-green-500">{{ formatDate(purchaseOrder.received_at, true) }}</p>
                            </div>
                            <div class="mt-2 min-w-0 sm:col-span-2">
                                <p class="text-[10px] uppercase text-muted-foreground">Catatan</p>
                                <p class="italic text-muted-foreground break-words">{{ purchaseOrder.notes || '—' }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Items Table -->
            <Card>
                <CardContent class="p-0 overflow-hidden">
                    <div class="p-4 border-b flex items-center gap-2 bg-muted/20">
                        <Box class="w-4 h-4 text-muted-foreground" />
                        <h3 class="font-semibold text-xs uppercase tracking-wide text-muted-foreground">Item Pesanan</h3>
                    </div>
                    <!-- Kartu vertikal untuk layar sempit -->
                    <div class="md:hidden">
                        <div class="divide-y">
                            <div v-for="item in purchaseOrder.items" :key="`card-${item.id}`" class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium break-words">{{ item.product.name }}</p>
                                        <p class="text-[10px] uppercase text-muted-foreground break-all" v-if="item.product.sku">
                                            SKU: {{ item.product.sku }}
                                        </p>
                                    </div>
                                    <Badge variant="outline" class="shrink-0 whitespace-nowrap border-muted-foreground/30 font-bold">
                                        {{ Number(item.quantity) }} {{ item.product.unit }}
                                    </Badge>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                                    <span class="text-muted-foreground tabular-nums whitespace-nowrap">{{ formatCurrency(item.buy_price) }} / {{ item.product.unit }}</span>
                                    <span class="font-semibold tabular-nums whitespace-nowrap">{{ formatCurrency(item.subtotal) }}</span>
                                </div>
                            </div>
                            <p v-if="!purchaseOrder.items.length" class="p-8 text-center text-sm text-muted-foreground">
                                Belum ada item.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t bg-muted/10 p-4">
                            <span class="text-xs uppercase tracking-wider text-muted-foreground">Grand Total Estimasi</span>
                            <span class="text-lg font-bold text-primary tabular-nums whitespace-nowrap">{{ formatCurrency(purchaseOrder.total_amount) }}</span>
                        </div>
                    </div>

                    <div class="hidden md:block">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-muted/30 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="h-10 px-4">Produk</th>
                                    <th class="h-10 px-4 text-center">Jumlah</th>
                                    <th class="h-10 px-4 text-right">Harga Satuan</th>
                                    <th class="h-10 px-4 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-foreground">
                                <tr v-for="item in purchaseOrder.items" :key="item.id" class="transition-colors hover:bg-muted/10">
                                    <td class="p-4">
                                        <div class="font-medium break-words">{{ item.product.name }}</div>
                                        <div class="text-[10px] uppercase text-muted-foreground break-all" v-if="item.product.sku">
                                            SKU: {{ item.product.sku }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-center align-middle">
                                        <Badge variant="outline" class="whitespace-nowrap border-muted-foreground/30 font-bold">
                                            {{ Number(item.quantity) }} {{ item.product.unit }}
                                        </Badge>
                                    </td>
                                    <td class="p-4 text-right text-muted-foreground tabular-nums whitespace-nowrap">
                                        {{ formatCurrency(item.buy_price) }}
                                    </td>
                                    <td class="p-4 text-right font-semibold tabular-nums whitespace-nowrap">
                                        {{ formatCurrency(item.subtotal) }}
                                    </td>
                                </tr>
                                <tr v-if="!purchaseOrder.items.length">
                                    <td colspan="4" class="p-8 text-center text-muted-foreground">
                                        Belum ada item.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t bg-muted/10 font-bold">
                                <tr class="text-foreground">
                                    <td colspan="3" class="p-4 text-right text-xs uppercase tracking-wider text-muted-foreground">Grand Total Estimasi Pembelian</td>
                                    <td class="p-4 text-right text-xl text-primary tabular-nums whitespace-nowrap">{{ formatCurrency(purchaseOrder.total_amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Dialogs -->
        <Dialog :open="showReceiveDialog" @update:open="showReceiveDialog = $event">
            <DialogContent class="w-[95vw] max-w-md">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Penerimaan Stok</DialogTitle>
                    <DialogDescription>
                        Aksi ini akan mengubah status PO menjadi <span class="font-bold text-green-600">DITERIMA</span> dan secara otomatis menambahkan stok barang ke database.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4 flex-col gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="showReceiveDialog = false">Batal</Button>
                    <Button class="w-full bg-green-600 text-white sm:w-auto" @click="receivePO">Ya, Terima Barang</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showCancelDialog" @update:open="showCancelDialog = $event">
            <DialogContent class="w-[95vw] max-w-md">
                <DialogHeader>
                    <DialogTitle>Batalkan Purchase Order?</DialogTitle>
                    <DialogDescription>
                        Apakah Anda benar-benar ingin membatalkan pesanan barang ini?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4 flex-col gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="showCancelDialog = false">Tutup</Button>
                    <Button variant="destructive" class="w-full sm:w-auto" @click="cancelPO">Ya, Batalkan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AdminLayout>
</template>
