<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { formatCurrency } from '@/lib/utils'
import { RotateCcw, Search, Eye, Plus, Loader2 } from 'lucide-vue-next'

interface RefundRow {
    id: number
    refund_code: string
    order_code: string | null
    order_id: number
    type: 'full' | 'partial'
    refund_amount: number
    refund_method: string
    reason: string | null
    user_name: string | null
    status: string
    created_at: string
}

interface StoreItem { id: number; name: string; slug: string }

const props = defineProps<{
    stores: StoreItem[]
    store: StoreItem | null
    refunds: { data: RefundRow[]; total: number }
    filters?: { search: string | null }
}>()

const searchQuery = ref(props.filters?.search ?? '')

function applySearch() {
    const params: Record<string, string> = {}
    if (props.store) params.store = String(props.store.id)
    if (searchQuery.value) params.search = searchQuery.value
    router.get('/admin/refunds', params, { preserveState: true })
}

function changeStore(storeId: number) {
    router.get('/admin/refunds', { store: storeId })
}

function viewRefund(id: number) {
    router.visit(`/admin/refunds/${id}`)
}

const methodLabels: Record<string, string> = {
    cash: 'Tunai',
    original: 'Metode Asal',
}

// New Refund Lookup
const showLookupDialog = ref(false)
const lookupCode = ref('')
const lookingUp = ref(false)

function submitLookup() {
    if (!lookupCode.value) return
    lookingUp.value = true
    showLookupDialog.value = false // Immediate close pattern
    router.post('/admin/refunds/lookup', {
        order_code: lookupCode.value
    }, {
        onError: () => {
            showLookupDialog.value = true
        },
        onFinish: () => {
            lookingUp.value = false
        }
    })
}
</script>

<template>
    <AdminLayout title="Refund">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight flex items-center gap-2">
                        <RotateCcw class="h-5 w-5 sm:h-6 sm:w-6 text-primary shrink-0" />
                        Riwayat Refund
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Kelola pengembalian barang dan uang pelanggan
                    </p>
                </div>
                <div class="flex w-full sm:w-auto flex-col sm:flex-row sm:items-center gap-2">
                    <select
                        v-if="stores.length > 1"
                        :value="store?.id"
                        class="filter-select flex h-9 w-full sm:w-auto rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                        @change="changeStore(Number(($event.target as HTMLSelectElement).value))"
                    >
                        <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <Button class="w-full sm:w-auto" @click="showLookupDialog = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Proses Refund Baru
                    </Button>
                </div>
            </div>

            <!-- Search -->
            <Card>
                <CardContent class="p-3 pt-4 sm:p-6">
                    <form class="flex flex-col gap-2 sm:flex-row sm:gap-3" @submit.prevent="applySearch">
                        <div class="relative flex-1 min-w-0">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Cari kode refund atau kode order..." class="pl-9" />
                        </div>
                        <Button type="submit" variant="outline" class="w-full sm:w-auto">Cari</Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card>
                <CardHeader class="p-4 sm:p-6">
                    <CardTitle>Daftar Refund</CardTitle>
                    <CardDescription v-if="store" class="break-words">
                        Toko: {{ store.name }} · {{ refunds.total }} refund
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-3 pt-0 sm:p-6 sm:pt-0">
                    <div v-if="!store" class="py-12 text-center text-muted-foreground">
                        Pilih toko terlebih dahulu.
                    </div>
                    <div v-else-if="refunds.data.length === 0" class="py-12 text-center text-muted-foreground">
                        Belum ada data refund.
                    </div>

                    <!-- Desktop Table -->
                    <div v-else class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="py-3 px-4 text-left font-medium text-muted-foreground">Kode Refund</th>
                                    <th class="py-3 px-4 text-left font-medium text-muted-foreground">Kode Order</th>
                                    <th class="py-3 px-4 text-center font-medium text-muted-foreground">Tipe</th>
                                    <th class="py-3 px-4 text-right font-medium text-muted-foreground">Jumlah</th>
                                    <th class="py-3 px-4 text-center font-medium text-muted-foreground">Metode</th>
                                    <th class="py-3 px-4 text-left font-medium text-muted-foreground">Proses oleh</th>
                                    <th class="py-3 px-4 text-left font-medium text-muted-foreground">Tanggal</th>
                                    <th class="py-3 px-4 text-center font-medium text-muted-foreground">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="r in refunds.data"
                                    :key="r.id"
                                    class="border-b last:border-0 hover:bg-muted/50 transition-colors"
                                >
                                    <td class="py-3 px-4 font-mono font-medium text-sm">{{ r.refund_code }}</td>
                                    <td class="py-3 px-4 font-mono text-sm text-muted-foreground">{{ r.order_code ?? '—' }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <Badge :variant="r.type === 'full' ? 'destructive' : 'secondary'">
                                            {{ r.type === 'full' ? 'Full' : 'Partial' }}
                                        </Badge>
                                    </td>
                                    <td class="py-3 px-4 text-right font-semibold text-red-600">-{{ formatCurrency(r.refund_amount) }}</td>
                                    <td class="py-3 px-4 text-center text-xs">{{ methodLabels[r.refund_method] ?? r.refund_method }}</td>
                                    <td class="py-3 px-4 text-xs">{{ r.user_name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-xs">{{ r.created_at }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <Button variant="ghost" size="sm" @click="viewRefund(r.id)">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div v-if="refunds.data.length > 0" class="md:hidden space-y-3">
                        <button
                            v-for="r in refunds.data"
                            :key="r.id"
                            class="w-full text-left rounded-lg border p-3 hover:bg-muted/50 transition-colors"
                            @click="viewRefund(r.id)"
                        >
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <span class="font-mono font-medium text-sm min-w-0 break-all">{{ r.refund_code }}</span>
                                <Badge :variant="r.type === 'full' ? 'destructive' : 'secondary'" class="text-xs shrink-0">
                                    {{ r.type === 'full' ? 'Full' : 'Partial' }}
                                </Badge>
                            </div>
                            <div class="text-xs text-muted-foreground mb-1 break-words">
                                Order: {{ r.order_code ?? '—' }} · {{ r.created_at }}
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs text-muted-foreground min-w-0 truncate">{{ r.user_name }}</span>
                                <span class="font-semibold text-red-600 whitespace-nowrap tabular-nums">-{{ formatCurrency(r.refund_amount) }}</span>
                            </div>
                        </button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Lookup Order Dialog -->
        <Dialog v-model:open="showLookupDialog">
            <DialogContent class="w-[95vw] max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <RotateCcw class="h-5 w-5" />
                        Proses Refund Baru
                    </DialogTitle>
                    <DialogDescription>
                        Masukkan Kode Pesanan untuk memulai proses pengembalian (Refund).
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitLookup">
                    <div>
                        <Label for="order_code">Kode Pesanan (Order Code)</Label>
                        <Input
                            id="order_code"
                            v-model="lookupCode"
                            placeholder="Contoh: ORD-2024..."
                            class="mt-1"
                            required
                            :disabled="lookingUp"
                        />
                        <p class="text-xs text-muted-foreground mt-1">
                            Kode pesanan dapat ditemukan pada struk belanja pelanggan.
                        </p>
                    </div>
                    <DialogFooter class="flex-col-reverse gap-2 sm:flex-row">
                        <Button type="button" variant="outline" class="w-full sm:w-auto" @click="showLookupDialog = false" :disabled="lookingUp">
                            Batal
                        </Button>
                        <Button type="submit" variant="destructive" class="w-full sm:w-auto" :disabled="lookingUp || !lookupCode.trim()">
                            <Loader2 v-if="lookingUp" class="mr-2 h-4 w-4 animate-spin" />
                            {{ lookingUp ? 'Mencari...' : 'Cari Pesanan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<style scoped>
.filter-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.25rem 1.25rem;
}
</style>
