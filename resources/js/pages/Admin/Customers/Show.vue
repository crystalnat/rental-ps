<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { formatCurrency } from '@/lib/utils'
import { UserCircle, ArrowLeft } from 'lucide-vue-next'

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
</script>

<template>
    <AdminLayout :title="`Pelanggan: ${customer.name}`">
        <template #headerActions>
            <Link :href="route('admin.customers.index')">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
            </Link>
        </template>

        <div class="space-y-6">
            <!-- Customer Info -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/15 text-primary">
                            <UserCircle class="h-7 w-7" />
                        </div>
                        <div>
                            <CardTitle class="text-xl">{{ customer.name }}</CardTitle>
                            <CardDescription>
                                <span v-if="customer.email">{{ customer.email }}</span>
                                <span v-if="customer.email && customer.phone"> · </span>
                                <span v-if="customer.phone">{{ customer.phone }}</span>
                                <span v-if="!customer.email && !customer.phone">—</span>
                            </CardDescription>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Badge variant="outline">{{ customer.total_orders }} pesanan</Badge>
                                <Badge variant="secondary">{{ formatCurrency(customer.total_spent) }} total belanja</Badge>
                            </div>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            <!-- Riwayat Pesanan -->
            <Card>
                <CardHeader>
                    <CardTitle>Riwayat Pesanan</CardTitle>
                    <CardDescription>
                        Daftar pesanan yang pernah dilakukan oleh pelanggan ini
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Kode</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Waktu</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Toko</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Tipe</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Meja</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Kasir</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground text-right">Total</th>
                                    <th class="w-20 px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="order in orders"
                                    :key="order.id"
                                    class="border-b transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 font-mono text-xs font-semibold">{{ order.order_code }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ order.created_at }}</td>
                                    <td class="px-4 py-3">{{ order.store_name }}</td>
                                    <td class="px-4 py-3">
                                        <Badge variant="outline" class="font-normal">
                                            {{ typeLabels[order.type] ?? order.type }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3">{{ order.table_name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ order.cashier_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(order.final_amount) }}</td>
                                    <td class="px-4 py-3">
                                        <Link :href="route('admin.orders.show', order.id)">
                                            <Button variant="ghost" size="sm" class="h-7 text-xs">
                                                Detail
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="orders.length === 0">
                                    <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">
                                        Belum ada riwayat pesanan
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
