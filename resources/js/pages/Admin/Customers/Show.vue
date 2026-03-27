<script setup lang="ts">
import { Link, router, Head } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { 
    UserCircle, ArrowLeft, Mail, Phone, ShoppingBag, 
    History, Store, User, Hash, Calendar, Eye
} from 'lucide-vue-next'

interface CustomerData {
    id: number
    name: string
    email: string | null
    phone: string | null
    total_orders: number
    total_spent: number
}

interface OrderItem {
    id: number
    order_code: string
    type: string
    status: string
    payment_status: string
    final_amount: number
    store_name: string
    cashier_name: string | null
    table_name: string | null
    created_at: string
}

const props = defineProps<{
    customer: CustomerData
    orders: OrderItem[]
}>()

const typeLabels: Record<string, string> = {
    dine_in: 'Dine In',
    takeaway: 'Take Away',
    walk_in: 'Walk In',
}

function formatCurrency(amount: number | string | null) {
    if (amount === null) return '-'
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(amount))
}

function goBack() {
    router.visit('/admin/customers')
}
</script>

<template>
    <Head :title="`Detail Pelanggan: ${customer.name}`" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header Interna -->
            <div class="flex items-center justify-between gap-4">
                <Button variant="ghost" size="sm" @click="goBack" class="px-0 hover:bg-transparent text-foreground">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>
                <h1 class="text-2xl font-bold text-foreground">Profil Pelanggan</h1>
            </div>

            <!-- Customer Profile Card -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col md:flex-row gap-6 items-start">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <UserCircle class="h-10 w-10" />
                        </div>
                        
                        <div class="flex-1 space-y-4">
                            <div>
                                <h2 class="text-2xl font-bold text-foreground">{{ customer.name }}</h2>
                                <div class="flex flex-wrap gap-4 mt-2 text-sm text-muted-foreground font-medium">
                                    <div v-if="customer.email" class="flex items-center gap-1.5">
                                        <Mail class="h-4 w-4 text-primary/60" /> {{ customer.email }}
                                    </div>
                                    <div v-if="customer.phone" class="flex items-center gap-1.5">
                                        <Phone class="h-4 w-4 text-primary/60" /> {{ customer.phone }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3 pt-2">
                                <div class="rounded-lg border bg-card px-4 py-2 shadow-sm flex items-center gap-3">
                                    <ShoppingBag class="h-4 w-4 text-emerald-500" />
                                    <div>
                                        <p class="text-[10px] uppercase font-bold text-muted-foreground leading-none mb-1">Total Order</p>
                                        <p class="text-lg font-bold leading-none">{{ customer.total_orders }} <span class="text-[10px] font-normal">Kunjungan</span></p>
                                    </div>
                                </div>
                                <div class="rounded-lg border bg-card px-4 py-2 shadow-sm flex items-center gap-3">
                                    <History class="h-4 w-4 text-primary" />
                                    <div>
                                        <p class="text-[10px] uppercase font-bold text-muted-foreground leading-none mb-1">Total Belanja</p>
                                        <p class="text-lg font-bold leading-none text-primary tabular-nums">{{ formatCurrency(customer.total_spent) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Order History Section -->
            <Card>
                <CardContent class="p-0 overflow-hidden">
                    <div class="p-4 border-b flex items-center gap-2 bg-muted/20">
                        <History class="w-4 h-4 text-muted-foreground" />
                        <h3 class="font-semibold text-xs uppercase tracking-wide text-muted-foreground">Riwayat Pesanan Lengkap</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-muted/30 text-muted-foreground border-b text-xs font-semibold uppercase tracking-wide">
                                <tr>
                                    <th class="h-11 px-4"><Hash class="h-3 w-3 inline mr-1" /> Nota</th>
                                    <th class="h-11 px-4"><Calendar class="h-3 w-3 inline mr-1" /> Waktu</th>
                                    <th class="h-11 px-4"><Store class="h-3 w-3 inline mr-1" /> Lokasi Toko</th>
                                    <th class="h-11 px-4 text-center">Tipe</th>
                                    <th class="h-11 px-4"><User class="h-3 w-3 inline mr-1" /> Kasir</th>
                                    <th class="h-11 px-4 text-right">Total Akhir</th>
                                    <th class="h-11 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-foreground">
                                <tr v-for="order in orders" :key="order.id" class="hover:bg-muted/10 transition-colors group">
                                    <td class="p-4 align-middle">
                                        <div class="font-mono text-xs font-bold">{{ order.order_code }}</div>
                                    </td>
                                    <td class="p-4 align-middle text-[11px] text-muted-foreground">
                                        {{ order.created_at }}
                                    </td>
                                    <td class="p-4 align-middle font-medium">
                                        {{ order.store_name }}
                                        <div v-if="order.table_name" class="text-[10px] text-primary">Meja: {{ order.table_name }}</div>
                                    </td>
                                    <td class="p-4 align-middle text-center">
                                        <Badge variant="outline" class="text-[10px] font-bold uppercase tracking-widest px-2 py-0">
                                            {{ typeLabels[order.type] ?? order.type }}
                                        </Badge>
                                    </td>
                                    <td class="p-4 align-middle text-[11px] text-muted-foreground">
                                        {{ order.cashier_name || '—' }}
                                    </td>
                                    <td class="p-4 align-middle text-right font-bold text-primary tabular-nums">
                                        {{ formatCurrency(order.final_amount) }}
                                    </td>
                                    <td class="p-4 align-middle text-right">
                                        <Link :href="`/admin/orders/${order.id}`">
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-primary">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="orders.length === 0">
                                    <td colspan="7" class="py-20 text-center text-muted-foreground bg-muted/5">
                                        <div class="flex flex-col items-center">
                                            <History class="h-10 w-10 text-muted-foreground/20 mb-3" />
                                            <p class="font-medium text-sm">Belum ada riwayat pesanan</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
