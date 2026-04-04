<script setup lang="ts">
import { ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { formatCurrency } from '@/lib/utils'
import {
    Plus, Store, Users, LayoutGrid, TrendingUp,
    MoreVertical, Pencil, Power, Trash2, MapPin, Phone, Package,
} from 'lucide-vue-next'

interface StoreItem {
    id: number
    name: string
    slug: string
    city: string | null
    province: string | null
    phone: string | null
    email: string | null
    open_time: string | null
    close_time: string | null
    is_active: boolean
    deleted_at: string | null
    orders_count: number
    users_count: number
    tables_count: number
    month_revenue: number
}

const props = defineProps<{
    stores: StoreItem[]
    brand: { id: number; name: string; slug: string }
}>()

const openMenuId = ref<number | null>(null)
const deleteTarget = ref<StoreItem | null>(null)
const menuPosition = ref({ top: 0, left: 0 })

function toggleMenu(id: number, event?: MouseEvent) {
    if (openMenuId.value === id) {
        openMenuId.value = null
        return
    }

    openMenuId.value = id

    // Calculate position for teleported menu
    if (event) {
        const button = event.currentTarget as HTMLElement
        const rect = button.getBoundingClientRect()
        menuPosition.value = {
            top: rect.bottom + 4,
            left: rect.right - 176, // 176px = w-44 (11rem * 16px)
        }
    }
}

function handleToggleActive(store: StoreItem) {
    openMenuId.value = null
    router.post(`/admin/stores/${store.id}/toggle-active`, {}, {
        preserveScroll: true,
    })
}

function handleDelete() {
    if (!deleteTarget.value) return
    const id = deleteTarget.value.id
    deleteTarget.value = null // Tutup modal segera agar UI responsif
    router.delete(`/admin/stores/${id}`, {
        preserveScroll: true,
    })
}
</script>

<template>
    <AdminLayout title="Manajemen Cabang">
        <template #headerActions>
            <Link href="/admin/stores/create">
                <Button size="sm">
                    <Plus class="h-4 w-4" />
                    Tambah Toko
                </Button>
            </Link>
        </template>

        <!-- Summary Cards -->
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <StatCard variant="primary">
                <template #title>Toko Aktif</template>
                <template #value>
                    <p class="text-2xl font-bold">{{ stores.filter(s => s.is_active).length }}</p>
                </template>
                <template #icon><Store class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="success">
                <template #title>Omzet Bulan Ini</template>
                <template #value>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(stores.reduce((a, s) => a + s.month_revenue, 0)) }}</p>
                </template>
                <template #icon><TrendingUp class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="muted">
                <template #title>Total Karyawan</template>
                <template #value>
                    <p class="text-2xl font-bold">{{ stores.reduce((a, s) => a + s.users_count, 0) }}</p>
                </template>
                <template #icon><Users class="h-5 w-5" /></template>
            </StatCard>
        </div>

        <!-- Store Grid -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="store in stores"
                :key="store.id"
                variant="elevated"
                class="group relative rounded-2xl p-5 transition-all"
                :class="{ 'opacity-50': !store.is_active }"
            >
                <!-- Header -->
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sidebar text-sidebar-foreground text-sm font-bold uppercase">
                            {{ store.name.slice(0, 2) }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold">{{ store.name }}</h3>
                            <div v-if="store.city" class="flex items-center gap-1 text-xs text-muted-foreground">
                                <MapPin class="h-3 w-3" />
                                {{ store.city }}<span v-if="store.province">, {{ store.province }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Menu - keluar dari opacity parent dengan position fixed saat menu terbuka -->
                    <div class="relative">
                        <button
                            class="relative z-20 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            :class="{ 'opacity-100': !store.is_active }"
                            :style="!store.is_active ? 'opacity: 1 !important' : ''"
                            @click.stop="toggleMenu(store.id, $event)"
                        >
                            <MoreVertical class="h-4 w-4" />
                        </button>

                        <teleport to="body">
                            <div
                                v-if="openMenuId === store.id"
                                class="fixed z-50 w-44 overflow-hidden rounded-lg border bg-popover shadow-lg"
                                :style="{
                                    top: `${menuPosition.top}px`,
                                    left: `${menuPosition.left}px`,
                                }"
                            >
                                <Link
                                    :href="`/admin/stores/${store.id}/edit`"
                                    class="flex items-center gap-2 px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                                    @click="openMenuId = null"
                                >
                                    <Pencil class="h-3.5 w-3.5" /> Edit Detail
                                </Link>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm transition-colors hover:bg-accent"
                                    @click="handleToggleActive(store)"
                                >
                                    <Power class="h-3.5 w-3.5" />
                                    {{ store.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                                <div class="my-1 border-t" />
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-destructive transition-colors hover:bg-destructive/10"
                                    @click="() => { deleteTarget = store; openMenuId = null }"
                                >
                                    <Trash2 class="h-3.5 w-3.5" /> Hapus Toko
                                </button>
                            </div>
                        </teleport>
                    </div>
                </div>

                <!-- Status & Hours -->
                <div class="mt-3 flex items-center gap-2">
                    <Badge :variant="store.is_active ? 'success' : 'secondary'" class="opacity-100">
                        {{ store.is_active ? 'Aktif' : 'Nonaktif' }}
                    </Badge>
                    <span v-if="store.open_time && store.close_time" class="text-xs text-muted-foreground">
                        {{ store.open_time }} – {{ store.close_time }}
                    </span>
                </div>

                <!-- Phone -->
                <div v-if="store.phone" class="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                    <Phone class="h-3 w-3" />
                    {{ store.phone }}
                </div>

                <!-- Stats Row -->
                <div class="mt-4 grid grid-cols-3 divide-x rounded-lg bg-muted/50 text-center text-xs">
                    <div class="py-2">
                        <p class="font-semibold">{{ store.orders_count }}</p>
                        <p class="text-muted-foreground">Order</p>
                    </div>
                    <div class="py-2">
                        <p class="font-semibold">{{ store.users_count }}</p>
                        <p class="text-muted-foreground">Staf</p>
                    </div>
                    <div class="py-2">
                        <p class="font-semibold">{{ store.tables_count }}</p>
                        <p class="text-muted-foreground">Meja</p>
                    </div>
                </div>

                <!-- Month Revenue -->
                <div class="mt-3 flex items-center justify-between border-t pt-3">
                    <span class="text-xs text-muted-foreground">Omzet Bulan Ini</span>
                    <span class="text-sm font-semibold text-success">{{ formatCurrency(store.month_revenue) }}</span>
                </div>

                <!-- Action Buttons -->
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <Link :href="`/admin/stores/${store.id}/products`">
                        <Button variant="outline" size="sm" class="w-full text-xs">
                            <Package class="h-3.5 w-3.5" />
                            Kelola Produk
                        </Button>
                    </Link>
                    <Link :href="`/admin/stores/${store.id}/floor-plan`">
                        <Button variant="outline" size="sm" class="w-full text-xs">
                            <LayoutGrid class="h-3.5 w-3.5" />
                            Denah Meja
                        </Button>
                    </Link>
                </div>
            </Card>

            <!-- Add New Store Card -->
            <Link
                href="/admin/stores/create"
                class="flex min-h-[200px] items-center justify-center rounded-xl border-2 border-dashed border-muted-foreground/30 bg-muted/20 text-muted-foreground transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary"
            >
                <div class="text-center">
                    <Plus class="mx-auto mb-2 h-8 w-8" />
                    <p class="text-sm font-medium">Tambah Cabang Baru</p>
                </div>
            </Link>
        </div>

        <!-- Click outside overlay -->
        <div v-if="openMenuId" class="fixed inset-0 z-0" @click="openMenuId = null" />

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!deleteTarget" @update:open="(v) => { if (!v) deleteTarget = null }">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Hapus Toko</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus toko
                        <strong>{{ deleteTarget?.name }}</strong>?
                        Tindakan ini tidak dapat dibatalkan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteTarget = null">Batal</Button>
                    <Button variant="destructive" @click="handleDelete">Ya, Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
