<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import Pagination from '@/components/Pagination.vue'
import TableToolbar from '@/components/TableToolbar.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import { 
    Plus, Search, Pencil, Trash2, Tag, Check, X, 
    Ticket, Activity, Calendar, Percent, Banknote 
} from 'lucide-vue-next'
import { debounce } from 'lodash'

interface Promo {
    id: number
    code: string
    type: 'percentage' | 'nominal'
    value: number | string
    min_purchase: number | string
    max_discount: number | string | null
    valid_from: string | null
    valid_until: string | null
    quota: number | null
    used_count: number
    is_active: boolean
    created_at: string
}

const props = defineProps<{
    promos: {
        data: Promo[]
        links: any[]
        from: number
        to: number
        total: number
    }
    filters: {
        search?: string
    }
}>()

const search = ref(props.filters.search ?? '')

watch(search, debounce((value: string) => {
    router.get('/admin/promos', { search: value }, {
        preserveState: true,
        replace: true,
    })
}, 300))

const showDeleteDialog = ref(false)
const promoToDelete = ref<Promo | null>(null)

function confirmDelete(promo: Promo) {
    promoToDelete.value = promo
    showDeleteDialog.value = true
}

function deletePromo() {
    if (!promoToDelete.value) return
    router.delete(`/admin/promos/${promoToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteDialog.value = false
            promoToDelete.value = null
        },
    })
}

function formatCurrency(amount: number | string | null) {
    if (amount === null) return '-'
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(amount))
}

function formatDate(dateString: string | null) {
    if (!dateString) return '∞'
    return new Date(dateString).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const activeCount = computed(() => props.promos.data.filter(p => p.is_active).length)
</script>

<template>
    <Head title="Manajemen Promo & Voucher" />

    <AdminLayout title="Promo & Voucher">
        <!-- Summary Cards -->
        <div class="mb-6 grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-3">
            <StatCard variant="primary" class="p-3 sm:p-5">
                <template #title>Total Promo</template>
                <template #value>
                    <p class="text-lg sm:text-2xl font-bold tabular-nums">{{ promos.total }}</p>
                </template>
                <template #icon><Ticket class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="success" class="p-3 sm:p-5">
                <template #title>Promo Aktif</template>
                <template #value>
                    <p class="text-lg sm:text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ activeCount }}</p>
                </template>
                <template #icon><Activity class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="warning" class="p-3 sm:p-5 hidden lg:block">
                <template #title>Sedang Berjalan</template>
                <template #value>
                    <p class="text-lg sm:text-2xl font-bold text-amber-600 dark:text-amber-400">Aktif</p>
                </template>
                <template #icon><Calendar class="h-5 w-5" /></template>
            </StatCard>
        </div>

        <!-- Toolbar -->
        <TableToolbar
            v-model="search"
            search-placeholder="Cari kode promo..."
        >
            <template #filters>
                <Link href="/admin/promos/create" class="w-full sm:w-auto">
                    <Button size="sm" class="h-9 w-full sm:w-auto">
                        <Plus class="mr-1 h-4 w-4" />
                        Tambah Promo
                    </Button>
                </Link>
            </template>
        </TableToolbar>

        <!-- Promotions Table -->
        <Card variant="elevated" class="mt-4">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-muted/30 text-muted-foreground border-b text-xs font-semibold uppercase tracking-wide">
                            <tr>
                                <th class="h-11 px-3 sm:px-4">Kode Promo</th>
                                <th class="h-11 px-3 sm:px-4">Diskon</th>
                                <th class="hidden lg:table-cell h-11 px-4">Min. Belanja</th>
                                <th class="hidden md:table-cell h-11 px-4">Masa Berlaku</th>
                                <th class="hidden lg:table-cell h-11 px-4 text-center">Kuota (Terpakai)</th>
                                <th class="hidden sm:table-cell h-11 px-4 text-center">Status</th>
                                <th class="h-11 px-3 sm:px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-foreground">
                            <tr v-for="promo in promos.data" :key="promo.id" class="hover:bg-muted/10 transition-colors">
                                <td class="p-3 sm:p-4 align-middle">
                                    <div class="flex items-center gap-2">
                                        <div class="hidden sm:flex h-8 w-8 shrink-0 items-center justify-center rounded bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            <Tag class="h-4 w-4" />
                                        </div>
                                        <span class="font-bold tracking-wider uppercase">{{ promo.code }}</span>
                                    </div>
                                    <!-- Ringkasan kolom yang disembunyikan agar info tetap ada di layar sempit -->
                                    <div class="mt-1 flex flex-col gap-1 whitespace-normal sm:hidden">
                                        <Badge :variant="promo.is_active ? 'success' : 'secondary'" class="w-fit rounded-full px-2 text-[10px]">
                                            {{ promo.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </Badge>
                                    </div>
                                    <div class="mt-0.5 whitespace-normal text-[10px] text-muted-foreground md:hidden">
                                        {{ formatDate(promo.valid_from) }} - {{ formatDate(promo.valid_until) }}
                                    </div>
                                    <div class="mt-0.5 whitespace-normal text-[10px] text-muted-foreground lg:hidden">
                                        Min. {{ formatCurrency(promo.min_purchase) }} · {{ promo.used_count }}/{{ promo.quota ?? '∞' }}
                                    </div>
                                </td>
                                <td class="p-3 sm:p-4 align-middle">
                                    <div v-if="promo.type === 'percentage'" class="flex items-center gap-1.5 font-medium text-blue-600 dark:text-blue-400">
                                        <Percent class="h-3.5 w-3.5" />
                                        {{ Number(promo.value) }}% 
                                        <span v-if="promo.max_discount" class="text-[10px] text-muted-foreground whitespace-nowrap">
                                            (Maks: {{ formatCurrency(promo.max_discount) }})
                                        </span>
                                    </div>
                                    <div v-else class="flex items-center gap-1.5 font-medium text-green-600 dark:text-green-400">
                                        <Banknote class="h-3.5 w-3.5" />
                                        {{ formatCurrency(promo.value) }}
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell p-4 align-middle tabular-nums">
                                    {{ formatCurrency(promo.min_purchase) }}
                                </td>
                                <td class="hidden md:table-cell p-4 align-middle">
                                    <div class="flex flex-col text-[11px] leading-tight">
                                        <span class="text-muted-foreground italic">{{ formatDate(promo.valid_from) }}</span>
                                        <span class="mx-auto my-0.5 text-muted-foreground/30">▼</span>
                                        <span class="font-medium">{{ formatDate(promo.valid_until) }}</span>
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell p-4 align-middle text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="font-bold tabular-nums">{{ promo.used_count }} <span class="text-[10px] font-normal text-muted-foreground">/ {{ promo.quota ?? '∞' }}</span></div>
                                        <div v-if="promo.quota" class="mt-1 h-1 w-16 overflow-hidden rounded-full bg-muted">
                                            <div 
                                                class="h-full bg-primary" 
                                                :style="{ width: Math.min((promo.used_count / promo.quota * 100), 100) + '%' }"
                                            />
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell p-4 align-middle text-center">
                                    <Badge :variant="promo.is_active ? 'success' : 'secondary'" class="rounded-full px-3">
                                        {{ promo.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </Badge>
                                </td>
                                <td class="p-3 sm:p-4 align-middle text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-1">
                                        <Link :href="`/admin/promos/${promo.id}/edit`">
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-primary">
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-destructive" @click="confirmDelete(promo)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="promos.data.length === 0">
                                <td colspan="7" class="whitespace-normal px-4 py-16 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center">
                                        <Ticket class="h-10 w-10 text-muted-foreground/20 mb-3" />
                                        <p class="font-medium text-sm">Belum ada promo yang terdaftar</p>
                                        <p class="text-xs text-muted-foreground max-w-xs mx-auto mt-1">
                                            Klik tombol "Tambah Promo" untuk membuat kode diskon atau voucher belanja baru.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="promos.total > 0" class="border-t p-3 sm:p-4">
                    <Pagination :links="promos.links" :from="promos.from" :to="promos.to" :total="promos.total" />
                </div>
            </CardContent>
        </Card>

        <!-- Delete Modal (Standard pattern) -->
        <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <DialogContent class="w-[95vw] max-w-md">
                <DialogHeader>
                    <DialogTitle>Hapus Promo</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus promo <strong>{{ promoToDelete?.code }}</strong>? Aksi ini tidak dapat dibatalkan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4 flex-col-reverse gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="showDeleteDialog = false">Batal</Button>
                    <Button variant="destructive" class="w-full sm:w-auto" @click="deletePromo">Ya, Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<script lang="ts">
import { computed } from 'vue' // Adding computed for activeCount
export default {
    // Boilerplate for direct computed access if needed
}
</script>
