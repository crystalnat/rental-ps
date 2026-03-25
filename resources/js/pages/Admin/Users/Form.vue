<script setup lang="ts">
import { computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { ArrowLeft, Loader2, Users } from 'lucide-vue-next'

interface UserData {
    id: number
    name: string
    email: string
    phone: string | null
    role: string
    store_id: number | null
    is_active: boolean
}

interface StoreItem {
    id: number
    name: string
}

interface RoleOption {
    value: string
    label: string
}

const props = defineProps<{
    user: UserData | null
    stores: StoreItem[]
    role_options: RoleOption[]
}>()

const isEdit = computed(() => !!props.user)
const pageTitle = computed(() =>
    isEdit.value ? `Edit: ${props.user!.name}` : 'Tambah Karyawan Baru',
)

const form = useForm({
    name:      props.user?.name      ?? '',
    email:     props.user?.email     ?? '',
    phone:     props.user?.phone     ?? '',
    password:  '',
    password_confirmation: '',
    role:      props.user?.role      ?? 'cashier',
    store_id:  (props.user?.store_id ?? '') as number | string,
    is_active: props.user?.is_active ?? true,
})

function submit() {
    const storeId = form.store_id === '' || form.store_id === null || form.store_id === undefined
        ? null
        : Number(form.store_id)
    if (isEdit.value) {
        const payload: Record<string, unknown> = {
            name: form.name,
            email: form.email,
            phone: form.phone,
            role: form.role,
            store_id: storeId,
            is_active: form.is_active,
        }
        if (form.password) {
            payload.password = form.password
            payload.password_confirmation = form.password_confirmation
        }
        form.transform(() => payload).put(route('admin.users.update', props.user!.id))
    } else {
        form.transform((data) => ({
            ...data,
            store_id: storeId,
        })).post(route('admin.users.store'))
    }
}
</script>

<template>
    <AdminLayout :title="pageTitle">
        <div class="mx-auto max-w-2xl space-y-4">
            <div>
                <Link
                    :href="route('admin.users.index')"
                    class="inline-flex h-9 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke daftar
                </Link>
            </div>
            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Users class="h-4 w-4" />
                            </div>
                            <div>
                                <CardTitle>Data Karyawan</CardTitle>
                                <CardDescription>
                                    Informasi akun karyawan untuk login dan manajemen
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Lengkap <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Contoh: Ahmad Budi"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email <span class="text-destructive">*</span></Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="ahmad@example.com"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">No. Telepon</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                inputmode="numeric"
                                placeholder="08123456789"
                                :disabled="form.processing"
                                @input="(e: Event) => { form.phone = (e.target as HTMLInputElement).value.replace(/\D/g, '') }"
                            />
                            <p v-if="form.errors.phone" class="text-xs text-destructive">{{ form.errors.phone }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="password">
                                Password {{ isEdit ? '(kosongkan jika tidak diubah)' : '' }}
                                <span v-if="!isEdit" class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                placeholder="Minimal 6 karakter"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p>
                        </div>

                        <div v-if="!isEdit || form.password" class="space-y-2">
                            <Label for="password_confirmation">Konfirmasi Password <span class="text-destructive">*</span></Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="Ulangi password"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.password_confirmation" class="text-xs text-destructive">{{ form.errors.password_confirmation }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="role">Role <span class="text-destructive">*</span></Label>
                            <select
                                id="role"
                                v-model="form.role"
                                class="filter-select h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                :disabled="form.processing"
                            >
                                <option v-for="opt in role_options" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.role" class="text-xs text-destructive">{{ form.errors.role }}</p>
                        </div>

                        <div v-if="stores.length > 0" class="space-y-2">
                            <Label for="store_id">Toko (untuk kasir)</Label>
                            <select
                                id="store_id"
                                v-model="form.store_id"
                                class="filter-select h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                :disabled="form.processing"
                            >
                                <option value="">— Tidak ditugaskan —</option>
                                <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p class="text-xs text-muted-foreground">Kasir biasanya ditugaskan ke toko tertentu</p>
                            <p v-if="form.errors.store_id" class="text-xs text-destructive">{{ form.errors.store_id }}</p>
                        </div>

                        <div class="flex items-center gap-2 rounded-lg border p-3">
                            <Checkbox
                                id="is_active"
                                :checked="form.is_active"
                                @update:checked="(v) => form.is_active = !!v"
                            />
                            <Label for="is_active" class="cursor-pointer text-sm font-medium">
                                Aktif — karyawan dapat login ke sistem
                            </Label>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex items-center justify-end gap-3 rounded-xl border bg-card p-4">
                    <Button type="button" variant="outline" :disabled="form.processing" as-child>
                        <Link :href="route('admin.users.index')">Batal</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="animate-spin" />
                        {{ isEdit ? 'Simpan Perubahan' : 'Buat Karyawan' }}
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
