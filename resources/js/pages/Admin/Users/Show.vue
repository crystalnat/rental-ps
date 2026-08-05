<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ArrowLeft, Users, Pencil } from 'lucide-vue-next'

interface UserData {
    id: number
    name: string
    email: string
    phone: string | null
    role: string
    store_id: number | null
    store_name: string | null
    is_active: boolean
}

interface ActivityItem {
    type: string
    type_label: string
    date: string
    description: string
    detail: string
    link: string | null
}

defineProps<{
    user: UserData
    activities: ActivityItem[]
}>()

const roleLabels: Record<string, string> = {
    admin: 'Admin',
    cashier: 'Kasir',
    staff: 'Staff',
}
</script>

<template>
    <AdminLayout :title="`Karyawan: ${user.name}`">
        <template #headerActions>
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="route('admin.users.edit', user.id)">
                    <Button variant="outline" size="sm">
                        <Pencil class="h-4 w-4" />
                        Edit
                    </Button>
                </Link>
                <Link :href="route('admin.users.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Kembali
                    </Button>
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- User Info -->
            <Card>
                <CardHeader>
                    <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary sm:h-14 sm:w-14">
                            <Users class="h-6 w-6 sm:h-7 sm:w-7" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg break-words sm:text-xl">{{ user.name }}</CardTitle>
                            <CardDescription class="break-all">
                                {{ user.email }}
                                <span v-if="user.phone"> · {{ user.phone }}</span>
                            </CardDescription>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Badge :variant="user.is_active ? 'success' : 'secondary'">
                                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
                                <Badge variant="outline">{{ roleLabels[user.role] ?? user.role }}</Badge>
                                <Badge v-if="user.store_name" variant="outline">{{ user.store_name }}</Badge>
                            </div>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            <!-- Rekam Jejak / Aktivitas -->
            <Card>
                <CardHeader>
                    <CardTitle>Rekam Jejak</CardTitle>
                    <CardDescription>
                        Daftar aktivitas yang dilakukan oleh karyawan ini (transaksi kasir, input stok, pengeluaran, ubah harga)
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <!-- Kartu vertikal untuk layar sempit -->
                    <div class="divide-y md:hidden">
                        <div v-for="(act, idx) in activities" :key="`card-${idx}`" class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="min-w-0 text-sm font-medium break-words">{{ act.description }}</p>
                                <Badge variant="outline" class="shrink-0 whitespace-nowrap font-normal">
                                    {{ act.type_label }}
                                </Badge>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground break-words">{{ act.detail }}</p>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <span class="text-xs text-muted-foreground">{{ act.date }}</span>
                                <Link v-if="act.link" :href="act.link">
                                    <Button variant="ghost" size="sm" class="h-7 text-xs">
                                        Detail
                                    </Button>
                                </Link>
                            </div>
                        </div>
                        <p v-if="activities.length === 0" class="px-4 py-12 text-center text-sm text-muted-foreground">
                            Belum ada aktivitas tercatat
                        </p>
                    </div>

                    <div class="hidden md:block">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Waktu</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Jenis</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Keterangan</th>
                                    <th class="hidden px-4 py-3 font-medium text-muted-foreground lg:table-cell">Detail</th>
                                    <th class="w-20 px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(act, idx) in activities"
                                    :key="idx"
                                    class="border-b transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 text-muted-foreground whitespace-nowrap">{{ act.date }}</td>
                                    <td class="px-4 py-3">
                                        <Badge variant="outline" class="whitespace-nowrap font-normal">
                                            {{ act.type_label }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 font-medium break-words">
                                        {{ act.description }}
                                        <!-- Detail ikut di kolom keterangan saat kolomnya disembunyikan -->
                                        <span class="mt-0.5 block text-xs font-normal text-muted-foreground lg:hidden">{{ act.detail }}</span>
                                    </td>
                                    <td class="hidden px-4 py-3 text-xs text-muted-foreground break-words lg:table-cell">{{ act.detail }}</td>
                                    <td class="px-4 py-3">
                                        <Link
                                            v-if="act.link"
                                            :href="act.link"
                                        >
                                            <Button variant="ghost" size="sm" class="h-7 text-xs">
                                                Detail
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="activities.length === 0">
                                    <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">
                                        Belum ada aktivitas tercatat
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
