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

defineProps<{
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
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <Button variant="ghost" size="sm" @click="goBack" class="self-start px-0 text-foreground hover:bg-transparent">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>
                <h1 class="text-xl font-bold text-foreground sm:text-2xl">Profil Pelanggan</h1>
            </div>

            <!-- Customer Profile Card -->
            <Card>
                <CardContent class="p-4 sm:p-6">
                    <div class="flex flex-col items-start gap-4 sm:flex-row sm:gap-6">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary sm:h-20 sm:w-20">
                            <UserCircle class="h-8 w-8 sm:h-10 sm:w-10" />
                        </div>

                        <div class="w-full min-w-0 flex-1 space-y-4">
                            <div class="min-w-0">
                                <h2 class="text-xl font-bold text-foreground break-words sm:text-2xl">{{ customer.name }}</h2>
                                <div class="mt-2 flex flex-col gap-1.5 text-sm font-medium text-muted-foreground sm:flex-row sm:flex-wrap sm:gap-4">
                                    <div v-if="customer.email" class="flex min-w-0 items-center gap-1.5">
                                        <Mail class="h-4 w-4 shrink-0 text-primary/60" />
                                        <span class="min-w-0 break-all">{{ customer.email }}</span>
                                    </div>
                                    <div v-if="customer.phone" class="flex min-w-0 items-center gap-1.5">
                                        <Phone class="h-4 w-4 shrink-0 text-primary/60" />
                                        <span class="min-w-0 break-all">{{ customer.phone }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 pt-2 sm:flex sm:flex-wrap">
                                <div class="flex items-center gap-3 rounded-lg border bg-card px-4 py-2 shadow-sm">
                                    <ShoppingBag class="h-4 w-4 shrink-0 text-emerald-500" />
                                    <div class="min-w-0">
                                        <p class="mb-1 text-[10px] font-bold uppercase leading-none text-muted-foreground">Total Order</p>
                                        <p class="text-lg font-bold leading-none tabular-nums">{{ customer.total_orders }} <span class="text-[10px] font-normal">Kunjungan</span></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 rounded-lg border bg-card px-4 py-2 shadow-sm">
                                    <History class="h-4 w-4 shrink-0 text-primary" />
                                    <div class="min-w-0">
                                        <p class="mb-1 text-[10px] font-bold uppercase leading-none text-muted-foreground">Total Belanja</p>
                                        <p class="text-lg font-bold leading-none text-primary tabular-nums break-words">{{ formatCurrency(customer.total_spent) }}</p>
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
                    
                    <!-- Kartu vertikal untuk layar sempit -->
                    <div class="divide-y md:hidden">
                        <Link
                            v-for="order in orders"
                            :key="`card-${order.id}`"
                            :href="`/admin/orders/${order.id}`"
                            class="block p-4 transition-colors active:bg-muted/30"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-xs font-bold break-all">{{ order.order_code }}</p>
                                    <p class="mt-0.5 text-[11px] text-muted-foreground">{{ order.created_at }}</p>
                                </div>
                                <Badge variant="outline" class="shrink-0 px-2 py-0 text-[10px] font-bold uppercase tracking-widest">
                                    {{ typeLabels[order.type] ?? order.type }}
                                </Badge>
                            </div>
                            <div class="mt-2 flex items-end justify-between gap-3">
                                <div class="min-w-0 text-xs text-muted-foreground">
                                    <p class="font-medium text-foreground break-words">{{ order.store_name }}</p>
                                    <p v-if="order.table_name" class="text-[10px] text-primary">Meja: {{ order.table_name }}</p>
                                    <p class="text-[11px]">Kasir: {{ order.cashier_name || '—' }}</p>
                                </div>
                                <p class="shrink-0 font-bold text-primary tabular-nums whitespace-nowrap">
                                    {{ formatCurrency(order.final_amount) }}
                                </p>
                            </div>
                        </Link>
                        <div v-if="orders.length === 0" class="flex flex-col items-center bg-muted/5 py-16 text-center text-muted-foreground">
                            <History class="mb-3 h-10 w-10 text-muted-foreground/20" />
                            <p class="text-sm font-medium">Belum ada riwayat pesanan</p>
                        </div>
                    </div>

                    <div class="hidden md:block">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-muted/30 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="h-11 px-4"><Hash class="mr-1 inline h-3 w-3" /> Nota</th>
                                    <th class="h-11 px-4"><Calendar class="mr-1 inline h-3 w-3" /> Waktu</th>
                                    <th class="hidden h-11 px-4 lg:table-cell"><Store class="mr-1 inline h-3 w-3" /> Lokasi Toko</th>
                                    <th class="h-11 px-4 text-center">Tipe</th>
                                    <th class="hidden h-11 px-4 xl:table-cell"><User class="mr-1 inline h-3 w-3" /> Kasir</th>
                                    <th class="h-11 px-4 text-right">Total Akhir</th>
                                    <th class="h-11 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-foreground">
                                <tr v-for="order in orders" :key="order.id" class="transition-colors hover:bg-muted/10">
                                    <td class="p-4 align-middle">
                                        <div class="font-mono text-xs font-bold break-all">{{ order.order_code }}</div>
                                        <!-- Toko & kasir ikut di sel nota saat kolomnya disembunyikan -->
                                        <div class="mt-0.5 text-[10px] text-muted-foreground lg:hidden">{{ order.store_name }}</div>
                                        <div class="text-[10px] text-muted-foreground xl:hidden">Kasir: {{ order.cashier_name || '—' }}</div>
                                    </td>
                                    <td class="p-4 align-middle text-[11px] text-muted-foreground">
                                        {{ order.created_at }}
                                    </td>
                                    <td class="hidden p-4 align-middle font-medium lg:table-cell">
                                        {{ order.store_name }}
                                        <div v-if="order.table_name" class="text-[10px] text-primary">Meja: {{ order.table_name }}</div>
                                    </td>
                                    <td class="p-4 text-center align-middle">
                                        <Badge variant="outline" class="px-2 py-0 text-[10px] font-bold uppercase tracking-widest">
                                            {{ typeLabels[order.type] ?? order.type }}
                                        </Badge>
                                    </td>
                                    <td class="hidden p-4 align-middle text-[11px] text-muted-foreground xl:table-cell">
                                        {{ order.cashier_name || '—' }}
                                    </td>
                                    <td class="p-4 text-right align-middle font-bold text-primary tabular-nums whitespace-nowrap">
                                        {{ formatCurrency(order.final_amount) }}
                                    </td>
                                    <td class="p-4 text-right align-middle">
                                        <Link :href="`/admin/orders/${order.id}`">
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-primary">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="orders.length === 0">
                                    <td colspan="7" class="bg-muted/5 py-20 text-center text-muted-foreground">
                                        <div class="flex flex-col items-center">
                                            <History class="mb-3 h-10 w-10 text-muted-foreground/20" />
                                            <p class="text-sm font-medium">Belum ada riwayat pesanan</p>
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
