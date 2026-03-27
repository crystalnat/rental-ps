<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import StatCard from '@/components/StatCard.vue'
import Pagination from '@/components/Pagination.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import TableToolbar from '@/components/TableToolbar.vue'
import { TableHeadSortable } from '@/components/ui/table'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { Truck, Plus, Pencil, Trash2, UserCircle } from 'lucide-vue-next'

interface Supplier {
    id: number
    name: string
    contact_person: string | null
    phone: string | null
    email: string | null
    address: string | null
    is_active: boolean
}

const props = defineProps<{
    suppliers: {
        data: Supplier[]
        links: any[]
        total: number
    }
    stats: {
        total: number
        active: number
    }
    filters: {
        search?: string
    }
}>()

const searchQuery = ref(props.filters.search || '')

function handleSearch(val: string) {
    router.get('/admin/suppliers', { ...props.filters, search: val }, { preserveState: true, replace: true })
}

// Modals State
const formOpen = ref(false)
const showDeleteDialog = ref(false)
const editingId = ref<number | null>(null)
const selectedSupplier = ref<Supplier | null>(null)

// Forms
const form = useForm({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    is_active: true,
})

function openAddModal() {
    editingId.value = null
    form.reset()
    form.clearErrors()
    formOpen.value = true
}

function openEditModal(supplier: Supplier) {
    editingId.value = supplier.id
    selectedSupplier.value = supplier
    form.name = supplier.name
    form.contact_person = supplier.contact_person || ''
    form.phone = supplier.phone || ''
    form.email = supplier.email || ''
    form.address = supplier.address || ''
    form.is_active = supplier.is_active
    form.clearErrors()
    formOpen.value = true
}

function submitForm() {
    if (editingId.value) {
        form.put(`/admin/suppliers/${editingId.value}`, {
            onSuccess: () => {
                formOpen.value = false
                editingId.value = null
            }
        })
    } else {
        form.post('/admin/suppliers', {
            onSuccess: () => {
                formOpen.value = false
                form.reset()
            }
        })
    }
}

function confirmDelete(supplier: Supplier) {
    selectedSupplier.value = supplier
    showDeleteDialog.value = true
}

function deleteSupplier() {
    if (!selectedSupplier.value) return
    router.delete(`/admin/suppliers/${selectedSupplier.value.id}`, {
        onSuccess: () => {
            showDeleteDialog.value = false
            selectedSupplier.value = null
        }
    })
}
</script>

<template>
    <Head title="Data Supplier" />

    <AdminLayout title="Supplier">
        <!-- Summary Stats -->
        <div class="mb-6 grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-3">
            <StatCard variant="primary" class="p-3 md:p-5">
                <template #title>Total Supplier</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold">{{ stats.total }}</p>
                </template>
                <template #icon><Truck class="h-5 w-5" /></template>
            </StatCard>
            <StatCard variant="success" class="p-3 md:p-5">
                <template #title>Supplier Aktif</template>
                <template #value>
                    <p class="text-xl md:text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.active }}</p>
                </template>
                <template #icon><UserCircle class="h-5 w-5" /></template>
            </StatCard>
        </div>

        <!-- Table Toolbar -->
        <TableToolbar
            v-model="searchQuery"
            search-placeholder="Cari supplier..."
            @update:model-value="handleSearch"
        >
            <template #filters>
                <Button size="sm" class="h-9" @click="openAddModal">
                    <Plus class="mr-1 h-4 w-4" />
                    Tambah Supplier
                </Button>
            </template>
        </TableToolbar>

        <!-- Suppliers Table -->
        <Card variant="elevated" class="mt-4">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b bg-muted/30">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Supplier</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kontak Person</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Telepon / Email</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="supplier in suppliers.data" :key="supplier.id" class="border-b transition-colors hover:bg-muted/20">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-primary hover:underline cursor-pointer" @click="openEditModal(supplier)">
                                        {{ supplier.name }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5" v-if="supplier.address">
                                        {{ supplier.address }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm align-middle">
                                    {{ supplier.contact_person || '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm align-middle">
                                    <div class="text-xs space-y-0.5">
                                        <div v-if="supplier.phone">{{ supplier.phone }}</div>
                                        <div v-if="supplier.email" class="text-muted-foreground">{{ supplier.email }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center align-middle">
                                    <Badge :variant="supplier.is_active ? 'success' : 'secondary'">
                                        {{ supplier.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right align-middle">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="openEditModal(supplier)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0 text-destructive hover:bg-destructive/10" @click="confirmDelete(supplier)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="suppliers.total === 0">
                                <td colspan="5" class="py-12 text-center text-muted-foreground">
                                    Belum ada data supplier.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="suppliers.total > 15" class="mt-4">
            <Pagination :links="suppliers.links" />
        </div>

        <!-- Form Modal -->
        <Dialog :open="formOpen" @update:open="(v) => { formOpen = v }">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Ubah Supplier' : 'Tambah Supplier' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingId ? 'Perbarui informasi kontak supplier.' : 'Tambah pemasok baru untuk ketersediaan stok.' }}
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitForm">
                    <div class="space-y-2">
                        <Label for="name">Nama Supplier <span class="text-destructive">*</span></Label>
                        <Input id="name" v-model="form.name" required />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="cp">Kontak Person</Label>
                            <Input id="cp" v-model="form.contact_person" />
                        </div>
                        <div class="space-y-2">
                            <Label for="phone">Telepon</Label>
                            <Input id="phone" v-model="form.phone" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="address">Alamat</Label>
                        <textarea id="address" v-model="form.address" rows="3" class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"></textarea>
                    </div>
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="status" v-model="form.is_active" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
                        <Label for="status">Supplier Aktif</Label>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="formOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="showDeleteDialog"
            title="Hapus Supplier"
            :description="'Hapus supplier ' + selectedSupplier?.name + '?'"
            variant="destructive"
            confirm-label="Ya, Hapus"
            @update:open="(v) => !v && (showDeleteDialog = false)"
            @confirm="deleteSupplier"
        />

    </AdminLayout>
</template>
