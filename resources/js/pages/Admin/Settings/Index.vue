<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { CreditCard, Plus, Pencil, Trash2 } from 'lucide-vue-next'

const PAYMENT_TYPES = [
    { value: 'cash', label: 'Tunai', fields: ['cash_input'] },
    { value: 'qris', label: 'QRIS', fields: ['qrcode'] },
    { value: 'bank_transfer', label: 'Transfer Bank', fields: ['account'] },
    { value: 'e_wallet', label: 'E-Wallet', fields: ['account'] },
    { value: 'other', label: 'Lainnya', fields: [], allowCustomName: true },
]

interface PaymentMethod {
    id: number
    name: string
    code: string
    sort_order: number
    is_active: boolean
    requires_cash_input: boolean
    qrcode_image: string | null
    account_name: string | null
    account_number: string | null
}

const props = defineProps<{
    payment_methods: PaymentMethod[]
}>()

const showAddDialog = ref(false)
const editingId = ref<number | null>(null)
const processing = ref(false)

const form = ref({
    type: 'cash' as string,
    name: '',
    code: '',
    sort_order: 0,
    is_active: true,
    requires_cash_input: false,
    qrcode_image: null as File | null,
    remove_qrcode: false,
    account_name: '',
    account_number: '',
})

const selectedType = computed(() => PAYMENT_TYPES.find((t) => t.value === form.value.type))
const showAccountFields = computed(() => selectedType.value?.fields.includes('account') ?? false)
const showQrcodeField = computed(() => selectedType.value?.fields.includes('qrcode') ?? false)
const showCashInputField = computed(() => selectedType.value?.fields.includes('cash_input') ?? false)
const showCustomName = computed(() => (selectedType.value as { allowCustomName?: boolean })?.allowCustomName ?? false)

const existingQrcode = computed(() => {
    if (!editingId.value) return null
    return props.payment_methods.find((p) => p.id === editingId.value)?.qrcode_image ?? null
})

const canSubmit = computed(() => {
    if (!form.value.name || !form.value.code) return false
    if (showQrcodeField.value) {
        return !!form.value.qrcode_image || (!!existingQrcode.value && !form.value.remove_qrcode)
    }
    if (showAccountFields.value) {
        return !!form.value.account_name?.trim() && !!form.value.account_number?.trim()
    }
    return true
})

const confirmState = ref<{
    open: boolean
    title: string
    description: string
    variant: 'default' | 'destructive'
    onConfirm: () => void
}>({
    open: false,
    title: '',
    description: '',
    variant: 'default',
    onConfirm: () => {},
})

watch(() => form.value.type, (newType) => {
    const t = PAYMENT_TYPES.find((x) => x.value === newType)
    if (t) {
        form.value.name = t.label
        form.value.code = t.value
        form.value.requires_cash_input = newType === 'cash'
        if (newType !== 'bank_transfer' && newType !== 'e_wallet') {
            form.value.account_name = ''
            form.value.account_number = ''
        }
        if (newType !== 'qris') {
            form.value.qrcode_image = null
            form.value.remove_qrcode = false
        }
    }
})

function openAdd() {
    form.value = {
        type: 'cash',
        name: 'Tunai',
        code: 'cash',
        sort_order: props.payment_methods.length,
        is_active: true,
        requires_cash_input: true,
        qrcode_image: null,
        remove_qrcode: false,
        account_name: '',
        account_number: '',
    }
    editingId.value = null
    showAddDialog.value = true
}

function openEdit(pm: PaymentMethod) {
    const type = PAYMENT_TYPES.find((t) => t.value === pm.code)?.value ?? 'other'
    form.value = {
        type,
        name: pm.name,
        code: pm.code,
        sort_order: pm.sort_order,
        is_active: pm.is_active,
        requires_cash_input: pm.requires_cash_input,
        qrcode_image: null,
        remove_qrcode: false,
        account_name: pm.account_name ?? '',
        account_number: pm.account_number ?? '',
    }
    editingId.value = pm.id
    showAddDialog.value = true
}

function submitForm() {
    processing.value = true
    const fd = new FormData()
    fd.append('name', form.value.name)
    fd.append('code', form.value.code)
    fd.append('sort_order', String(form.value.sort_order))
    fd.append('is_active', form.value.is_active ? '1' : '0')
    fd.append('requires_cash_input', form.value.requires_cash_input ? '1' : '0')
    fd.append('account_name', form.value.account_name)
    fd.append('account_number', form.value.account_number)
    if (form.value.qrcode_image) {
        fd.append('qrcode_image', form.value.qrcode_image)
    }
    if (editingId.value && form.value.remove_qrcode) {
        fd.append('remove_qrcode', '1')
    }
    const opts = {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            processing.value = false
            showAddDialog.value = false
        },
    }
    if (editingId.value) {
        fd.append('_method', 'PUT')
        router.post(route('admin.settings.payment-methods.update', editingId.value), fd, opts)
    } else {
        router.post(route('admin.settings.payment-methods.store'), fd, opts)
    }
}

function confirmDelete(pm: PaymentMethod) {
    confirmState.value = {
        open: true,
        title: 'Hapus Metode Pembayaran',
        description: `Yakin ingin menghapus "${pm.name}"?`,
        variant: 'destructive',
        onConfirm: () => {
            router.delete(route('admin.settings.payment-methods.destroy', pm.id), {
                preserveScroll: true,
                onFinish: () => { confirmState.value.open = false },
            })
        },
    }
}
</script>

<template>
    <AdminLayout title="Pengaturan">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <CreditCard class="h-5 w-5" />
                    Metode Pembayaran
                </CardTitle>
                <CardDescription>
                    Kelola metode pembayaran yang tersedia di kasir.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="mb-4 flex justify-end">
                    <Button size="sm" @click="openAdd">
                        <Plus class="h-4 w-4" />
                        Tambah Metode Pembayaran
                    </Button>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="pm in payment_methods"
                        :key="pm.id"
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div class="flex items-center gap-4">
                            <div>
                                <p class="font-medium">{{ pm.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ pm.code }}
                                    <span v-if="pm.qrcode_image" class="ml-1 text-xs">· QR</span>
                                    <span v-if="pm.account_number" class="ml-1 text-xs">· {{ pm.account_number }}</span>
                                </p>
                            </div>
                            <Badge v-if="!pm.is_active" variant="secondary">Nonaktif</Badge>
                            <Badge v-else-if="pm.requires_cash_input" variant="outline">Input tunai</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="ghost" size="icon" @click="openEdit(pm)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="text-destructive" @click="confirmDelete(pm)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <p v-if="payment_methods.length === 0" class="py-8 text-center text-muted-foreground">
                        Belum ada metode pembayaran. Klik "Tambah Metode Pembayaran" untuk menambah.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Add/Edit Dialog -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit' : 'Tambah' }} Metode Pembayaran</DialogTitle>
                    <DialogDescription>
                        Pilih tipe metode pembayaran, lalu isi data yang diminta.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitForm">
                    <div>
                        <Label for="type">Tipe Metode Pembayaran</Label>
                        <select
                            id="type"
                            v-model="form.type"
                            class="filter-select mt-1 flex h-9 w-full rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                        >
                            <option
                                v-for="t in PAYMENT_TYPES"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </select>
                    </div>

                    <div v-if="showCustomName">
                        <Label for="name">Nama tampilan</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Contoh: OVO, Dana"
                            class="mt-1"
                        />
                    </div>

                    <div v-if="showQrcodeField">
                        <Label for="qrcode_image">Gambar QR/Barcode</Label>
                        <input
                            id="qrcode_image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-1 block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                            @change="form.qrcode_image = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <div v-if="existingQrcode && !form.remove_qrcode" class="mt-2 flex items-center gap-2">
                            <img :src="existingQrcode" alt="QR" class="h-24 w-24 rounded border object-cover" />
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="form.remove_qrcode" type="checkbox" />
                                Hapus & ganti gambar
                            </label>
                        </div>
                        <p v-if="form.qrcode_image" class="mt-1 text-xs text-muted-foreground">{{ form.qrcode_image.name }}</p>
                    </div>

                    <div v-if="showAccountFields" class="space-y-3 rounded-lg border p-4">
                        <p class="text-sm font-medium">
                            {{ form.type === 'bank_transfer' ? 'Data Rekening Bank' : 'Data E-Wallet' }}
                        </p>
                        <div>
                            <Label :for="form.type === 'bank_transfer' ? 'account_name' : 'ewallet_name'">
                                {{ form.type === 'bank_transfer' ? 'Nama Rekening' : 'Nama (a.n.)' }}
                            </Label>
                            <Input
                                :id="form.type === 'bank_transfer' ? 'account_name' : 'ewallet_name'"
                                v-model="form.account_name"
                                :placeholder="form.type === 'bank_transfer' ? 'Contoh: PT Toko ABC' : 'Contoh: John Doe'"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label :for="form.type === 'bank_transfer' ? 'account_number' : 'ewallet_number'">
                                {{ form.type === 'bank_transfer' ? 'Nomor Rekening' : 'Nomor HP / ID' }}
                            </Label>
                            <Input
                                :id="form.type === 'bank_transfer' ? 'account_number' : 'ewallet_number'"
                                v-model="form.account_number"
                                :placeholder="form.type === 'bank_transfer' ? 'Contoh: 1234567890' : 'Contoh: 08123456789'"
                                class="mt-1"
                            />
                        </div>
                    </div>

                    <div v-if="showCashInputField" class="flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <Label for="requires_cash_input">Input uang tunai</Label>
                            <p class="text-xs text-muted-foreground">Kasir input uang diterima & kembalian</p>
                        </div>
                        <Switch
                            id="requires_cash_input"
                            :checked="form.requires_cash_input"
                            @update:checked="form.requires_cash_input = $event"
                        />
                    </div>

                    <div>
                        <Label for="sort_order">Urutan tampilan</Label>
                        <Input
                            id="sort_order"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            class="mt-1"
                        />
                    </div>

                    <div class="flex items-center justify-between">
                        <Label for="is_active">Aktif</Label>
                        <Switch
                            id="is_active"
                            :checked="form.is_active"
                            @update:checked="form.is_active = $event"
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showAddDialog = false">
                            Batal
                        </Button>
                        <Button type="submit" :disabled="processing || !canSubmit">
                            {{ processing ? 'Menyimpan...' : (editingId ? 'Simpan' : 'Tambah') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="confirmState.open"
            :title="confirmState.title"
            :description="confirmState.description"
            :variant="confirmState.variant"
            @update:open="confirmState.open = $event"
            @confirm="confirmState.onConfirm"
        />
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
:global(.theme-dark) .filter-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
}
</style>
