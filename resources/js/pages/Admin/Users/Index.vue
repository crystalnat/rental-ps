<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { Plus, Users, Pencil, Trash2, Search } from 'lucide-vue-next'

interface UserItem {
    id: number
    name: string
    email: string
    phone: string | null
    role: string
    store_id: number | null
    store_name: string | null
    is_active: boolean
}

interface StoreItem {
    id: number
    name: string
}

const props = defineProps<{
    users: UserItem[]
    stores: StoreItem[]
    currentUserId: number
    filters: {
        search: string
        role: string
        store: string
        status: string
    }
}>()

const roleLabels: Record<string, string> = {
    admin: 'Admin',
    cashier: 'Kasir',
    staff: 'Staff',
}

const filterState = ref({
    search: props.filters.search ?? '',
    role: props.filters.role ?? 'all',
    store: props.filters.store ?? 'all',
    status: props.filters.status ?? 'all',
})

watch(() => props.filters, (f) => {
    filterState.value.search = f.search ?? ''
    filterState.value.role = f.role ?? 'all'
    filterState.value.store = f.store ?? 'all'
    filterState.value.status = f.status ?? 'all'
}, { deep: true })

function buildQueryParams() {
    const p: Record<string, string | undefined> = {
        search: filterState.value.search || undefined,
        role: filterState.value.role !== 'all' ? filterState.value.role : undefined,
        store: filterState.value.store !== 'all' ? filterState.value.store : undefined,
        status: filterState.value.status !== 'all' ? filterState.value.status : undefined,
    }
    return Object.fromEntries(Object.entries(p).filter(([, v]) => v != null && v !== ''))
}

function applyFilters() {
    router.get('/admin/users', buildQueryParams(), { preserveState: true })
}

const hasActiveFilters = computed(() =>
    !!filterState.value.search ||
    filterState.value.role !== 'all' ||
    filterState.value.store !== 'all' ||
    filterState.value.status !== 'all',
)

function clearFilters() {
    filterState.value.search = ''
    filterState.value.role = 'all'
    filterState.value.store = 'all'
    filterState.value.status = 'all'
    applyFilters()
}

const deleteTarget = ref<UserItem | null>(null)

function handleDelete() {
    if (!deleteTarget.value) return
    router.delete(`/admin/users/${deleteTarget.value.id}`, {
        onFinish: () => { deleteTarget.value = null },
    })
}

function canDelete(user: UserItem) {
    return user.id !== props.currentUserId
}
</script>

<template>
    <AdminLayout title="Karyawan">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="grid flex-1 grid-cols-2 gap-3 sm:gap-4">
                <StatCard variant="primary" class="border-none shadow-sm p-3 sm:p-5">
                    <template #title>Total Karyawan</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-black tabular-nums">{{ users.length }}</p>
                    </template>
                    <template #icon><Users class="h-5 w-5" /></template>
                </StatCard>
                <StatCard variant="success" class="border-none shadow-sm p-3 sm:p-5">
                    <template #title>Aktif</template>
                    <template #value>
                        <p class="text-lg sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ users.filter(u => u.is_active).length }}</p>
                    </template>
                    <template #icon><Users class="h-5 w-5" /></template>
                </StatCard>
            </div>
            <Button class="h-11 md:h-10 w-full sm:w-auto font-bold px-6 shadow-md" as-child>
                <Link href="/admin/users/create">
                    <Plus class="mr-2 h-5 w-5 md:h-4 md:w-4" />
                    Tambah Karyawan
                </Link>
            </Button>
        </div>

        <!-- Filters -->
        <Card class="border-none shadow-sm md:shadow-md mb-6 overflow-hidden">
            <CardContent class="p-3 sm:p-5">
                <div class="space-y-4">
                    <div class="relative group">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground group-focus-within:text-primary transition-colors" />
                        <Input
                            v-model="filterState.search"
                            placeholder="Cari nama, email, atau telepon..."
                            class="h-11 md:h-10 pl-10 border-muted-foreground/20"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground pl-1">Jabatan</Label>
                            <select
                                v-model="filterState.role"
                                class="h-10 w-full rounded-md border border-muted-foreground/20 bg-background px-3 text-sm focus:ring-2 focus:ring-primary/20 transition-all outline-none"
                            >
                                <option value="all">Semua Role</option>
                                <option value="admin">Admin</option>
                                <option value="cashier">Kasir</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <div v-if="stores.length > 0" class="space-y-1.5">
                            <Label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground pl-1">Cabang Toko</Label>
                            <select
                                v-model="filterState.store"
                                class="h-10 w-full rounded-md border border-muted-foreground/20 bg-background px-3 text-sm focus:ring-2 focus:ring-primary/20 transition-all outline-none"
                            >
                                <option value="all">Semua Toko</option>
                                <option v-for="s in stores" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5 sm:col-span-2 md:col-span-1">
                            <Label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground pl-1">Status Akun</Label>
                            <select
                                v-model="filterState.status"
                                class="h-10 w-full rounded-md border border-muted-foreground/20 bg-background px-3 text-sm focus:ring-2 focus:ring-primary/20 transition-all outline-none"
                            >
                                <option value="all">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 pt-2 border-t border-dashed sm:flex-row">
                        <Button class="flex-1 h-11 md:h-10 font-bold" @click="applyFilters">
                            Terapkan Filter
                        </Button>
                        <Button variant="outline" class="flex-1 h-11 md:h-10 border-muted-foreground/20" :disabled="!hasActiveFilters" @click="clearFilters">
                            Reset
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Table -->
        <Card class="border-none shadow-sm md:shadow-md overflow-hidden">
            <CardContent class="p-0">
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-muted/50 border-b">
                                <th class="px-4 py-4 text-left font-black uppercase tracking-widest text-[10px] text-muted-foreground">Nama Karyawan</th>
                                <th class="hidden xl:table-cell px-4 py-4 text-left font-black uppercase tracking-widest text-[10px] text-muted-foreground">Kontak</th>
                                <th class="px-4 py-4 text-left font-black uppercase tracking-widest text-[10px] text-muted-foreground">Akses</th>
                                <th class="hidden lg:table-cell px-4 py-4 text-left font-black uppercase tracking-widest text-[10px] text-muted-foreground">Toko</th>
                                <th class="px-4 py-4 text-center font-black uppercase tracking-widest text-[10px] text-muted-foreground">Status</th>
                                <th class="px-4 py-4 text-right font-black uppercase tracking-widest text-[10px] text-muted-foreground">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="user in users" :key="user.id" class="hover:bg-primary/5 transition-colors group" :class="{ 'opacity-60 grayscale-[0.5]': !user.is_active }">
                                <td class="px-4 py-3 min-w-0">
                                    <Link :href="`/admin/users/${user.id}`" class="font-bold text-primary group-hover:underline break-words">
                                        {{ user.name }}
                                        <Badge v-if="user.id === currentUserId" variant="secondary" class="ml-2 text-[8px] h-4">SAYA</Badge>
                                    </Link>
                                    <!-- Ringkasan kolom yang disembunyikan agar info tetap ada di layar sempit -->
                                    <span class="mt-0.5 block text-xs text-muted-foreground break-all xl:hidden">{{ user.email }}</span>
                                    <span class="mt-0.5 block text-xs text-muted-foreground lg:hidden">{{ user.store_name || 'Semua Cabang' }}</span>
                                </td>
                                <td class="hidden xl:table-cell px-4 py-3">
                                    <div class="text-xs space-y-0.5">
                                        <p class="break-all">{{ user.email }}</p>
                                        <p v-if="user.phone" class="text-muted-foreground italic font-mono">{{ user.phone }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="outline" class="font-black text-[10px] border-primary/20 bg-primary/5 text-primary">
                                        {{ roleLabels[user.role] ?? user.role }}
                                    </Badge>
                                </td>
                                <td class="hidden lg:table-cell px-4 py-3 text-xs font-medium">{{ user.store_name || 'Semua Cabang' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <Badge :variant="user.is_active ? 'success' : 'secondary'" class="text-[10px] font-bold">
                                        {{ user.is_active ? 'AKTIF' : 'NONAKTIF' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="`/admin/users/${user.id}/edit`">
                                            <Button variant="ghost" size="icon" class="h-8 w-8 rounded-full">
                                                <Pencil class="h-3.5 w-3.5" />
                                            </Button>
                                        </Link>
                                        <Button
                                            v-if="canDelete(user)"
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-full text-destructive hover:bg-destructive/10"
                                            @click="deleteTarget = user"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y">
                    <div v-for="user in users" :key="user.id" class="p-3 space-y-3" :class="{ 'opacity-60': !user.is_active }">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link :href="`/admin/users/${user.id}`" class="font-bold text-primary text-base break-words">{{ user.name }}</Link>
                                    <Badge v-if="user.id === currentUserId" variant="secondary" class="text-[8px] h-3.5 px-1 font-black">SAYA</Badge>
                                </div>
                                <div class="text-xs text-muted-foreground space-y-0.5">
                                    <p class="break-all">{{ user.email }}</p>
                                    <p v-if="user.phone" class="font-mono">{{ user.phone }}</p>
                                </div>
                                <div class="flex flex-wrap gap-1.5 pt-1">
                                    <Badge variant="outline" class="text-[9px] font-black uppercase tracking-widest border-primary/30">
                                        {{ roleLabels[user.role] ?? user.role }}
                                    </Badge>
                                    <Badge v-if="user.store_name" variant="outline" class="text-[9px] font-black uppercase tracking-widest border-muted-foreground/30">
                                        {{ user.store_name }}
                                    </Badge>
                                    <Badge :variant="user.is_active ? 'success' : 'secondary'" class="text-[9px] font-black">
                                        {{ user.is_active ? 'AKTIF' : 'NONAKTIF' }}
                                    </Badge>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 shrink-0">
                                <Button variant="secondary" size="icon" class="h-9 w-9 rounded-full shadow-sm" as-child>
                                    <Link :href="`/admin/users/${user.id}/edit`">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    v-if="canDelete(user)"
                                    variant="secondary"
                                    size="icon"
                                    class="h-9 w-9 rounded-full shadow-sm text-destructive"
                                    @click="deleteTarget = user"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="users.length === 0 && !hasActiveFilters"
                    class="flex flex-col items-center justify-center py-16 text-center"
                >
                    <Users class="mb-3 h-12 w-12 text-muted-foreground/50" />
                    <p class="text-muted-foreground">Belum ada karyawan</p>
                    <Link href="/admin/users/create" class="mt-3">
                        <Button size="sm">
                            <Plus class="h-4 w-4" />
                            Tambah Karyawan Pertama
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!deleteTarget" @update:open="(v) => { if (!v) deleteTarget = null }">
            <DialogContent class="w-[95vw] max-w-sm">
                <DialogHeader>
                    <DialogTitle>Hapus Karyawan</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus karyawan
                        <strong>{{ deleteTarget?.name }}</strong>?
                        Akun ini tidak dapat digunakan lagi untuk login.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col-reverse gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="deleteTarget = null">Batal</Button>
                    <Button variant="destructive" class="w-full sm:w-auto" @click="handleDelete">Ya, Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
