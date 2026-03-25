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

const props = defineProps<{
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
            <div class="flex items-center gap-2">
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
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/15 text-primary">
                            <Users class="h-7 w-7" />
                        </div>
                        <div>
                            <CardTitle class="text-xl">{{ user.name }}</CardTitle>
                            <CardDescription>
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
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Waktu</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Jenis</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Keterangan</th>
                                    <th class="px-4 py-3 font-medium text-muted-foreground">Detail</th>
                                    <th class="w-20 px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(act, idx) in activities"
                                    :key="idx"
                                    class="border-b transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 text-muted-foreground">{{ act.date }}</td>
                                    <td class="px-4 py-3">
                                        <Badge variant="outline" class="font-normal">
                                            {{ act.type_label }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ act.description }}</td>
                                    <td class="px-4 py-3 text-muted-foreground text-xs">{{ act.detail }}</td>
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
