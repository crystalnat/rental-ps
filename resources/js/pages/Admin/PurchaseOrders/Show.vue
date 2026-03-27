<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3'
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
            <div class="flex items-center justify-between gap-4">
                <Button variant="ghost" size="sm" @click="goBack" class="px-0 hover:bg-transparent text-foreground">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>

                <div class="flex items-center gap-3">
                    <Button v-if="purchaseOrder.status === 'pending'" variant="outline" size="sm" @click="router.visit(`/admin/purchase-orders/${purchaseOrder.id}/edit`)" :disabled="processing">
                        <Pencil class="mr-2 h-4 w-4" /> Edit
                    </Button>
                    <Button v-if="purchaseOrder.status === 'pending'" variant="destructive" size="sm" @click="showCancelDialog = true" :disabled="processing">
                        <XCircle class="mr-2 h-4 w-4" /> Batal
                    </Button>
                    <Button v-if="purchaseOrder.status === 'pending'" size="sm" @click="showReceiveDialog = true" class="bg-green-600 hover:bg-green-700 text-white" :disabled="processing">
                        <CheckCircle2 v-v-if="!processing" class="mr-2 h-4 w-4" />
                        <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                        Terima Barang
                    </Button>
                </div>
            </div>

            <!-- Title & Status -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">PO: {{ purchaseOrder.po_number }}</h1>
                    <p class="text-sm text-muted-foreground">{{ purchaseOrder.store.name }} · {{ purchaseOrder.supplier.name }}</p>
                </div>
                <Badge :variant="getStatusColor(purchaseOrder.status) as any" class="text-sm px-4 py-1">
                    {{ getStatusLabel(purchaseOrder.status) }}
                </Badge>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Info Cards -->
                <Card>
                    <CardContent class="p-6 text-foreground">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-4 border-b pb-2 flex items-center gap-2">
                            <MapPin class="h-4 w-4" /> Lokasi & Supplier
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <p class="text-[10px] text-muted-foreground uppercase font-bold">Cabang Tujuan</p>
                                <p class="font-medium">{{ purchaseOrder.store.name }}</p>
                                <p class="text-xs text-muted-foreground mt-1" v-if="purchaseOrder.store.address">{{ purchaseOrder.store.address }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-muted-foreground uppercase font-bold">Supplier</p>
                                <p class="font-medium">{{ purchaseOrder.supplier.name }}</p>
                                <p class="text-xs text-muted-foreground mt-1">
                                    {{ purchaseOrder.supplier.phone || '-' }} · {{ purchaseOrder.supplier.email || '-' }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6 text-foreground">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-4 border-b pb-2 flex items-center gap-2">
                            <Calendar class="h-4 w-4" /> Detail Dokumen
                        </h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-[10px] text-muted-foreground uppercase">Tgl Dibuat</p>
                                <p class="font-medium">{{ formatDate(purchaseOrder.created_at, true) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-muted-foreground uppercase">Estimasi Datang</p>
                                <p class="font-medium">{{ formatDate(purchaseOrder.expected_date) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-muted-foreground uppercase">Admin</p>
                                <p class="font-medium">{{ purchaseOrder.creator?.name || 'Sistem' }}</p>
                            </div>
                            <div v-if="purchaseOrder.status === 'received'">
                                <p class="text-[10px] text-green-600 font-bold uppercase">Waktu Terima</p>
                                <p class="font-bold text-green-700 dark:text-green-500">{{ formatDate(purchaseOrder.received_at, true) }}</p>
                            </div>
                            <div class="col-span-2 mt-2">
                                <p class="text-[10px] text-muted-foreground uppercase">Catatan</p>
                                <p class="italic text-muted-foreground">{{ purchaseOrder.notes || '—' }}</p>
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
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-muted/30 text-muted-foreground border-b text-xs font-semibold uppercase tracking-wide">
                                <tr>
                                    <th class="h-10 px-4">Produk</th>
                                    <th class="h-10 px-4 text-center">Jumlah</th>
                                    <th class="h-10 px-4 text-right">Harga Satuan</th>
                                    <th class="h-10 px-4 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-foreground">
                                <tr v-for="item in purchaseOrder.items" :key="item.id" class="hover:bg-muted/10 transition-colors">
                                    <td class="p-4">
                                        <div class="font-medium">{{ item.product.name }}</div>
                                        <div class="text-[10px] text-muted-foreground uppercase" v-if="item.product.sku">
                                            SKU: {{ item.product.sku }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-center align-middle">
                                        <Badge variant="outline" class="font-bold border-muted-foreground/30">
                                            {{ Number(item.quantity) }} {{ item.product.unit }}
                                        </Badge>
                                    </td>
                                    <td class="p-4 text-right text-muted-foreground tabular-nums">
                                        {{ formatCurrency(item.buy_price) }}
                                    </td>
                                    <td class="p-4 text-right font-semibold tabular-nums">
                                        {{ formatCurrency(item.subtotal) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-muted/10 font-bold border-t">
                                <tr class="text-foreground">
                                    <td colspan="3" class="p-4 text-right uppercase text-xs tracking-wider text-muted-foreground">Grand Total Estimasi Pembelian</td>
                                    <td class="p-4 text-right text-xl text-primary tabular-nums">{{ formatCurrency(purchaseOrder.total_amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Dialogs -->
        <Dialog :open="showReceiveDialog" @update:open="showReceiveDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Penerimaan Stok</DialogTitle>
                    <DialogDescription>
                        Aksi ini akan mengubah status PO menjadi <span class="text-green-600 font-bold">DITERIMA</span> dan secara otomatis menambahkan stok barang ke database.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="showReceiveDialog = false">Batal</Button>
                    <Button class="bg-green-600 text-white" @click="receivePO">Ya, Terima Barang</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showCancelDialog" @update:open="showCancelDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Batalkan Purchase Order?</DialogTitle>
                    <DialogDescription>
                        Apakah Anda benar-benar ingin membatalkan pesanan barang ini?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="showCancelDialog = false">Tutup</Button>
                    <Button variant="destructive" @click="cancelPO">Ya, Batalkan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AdminLayout>
</template>
