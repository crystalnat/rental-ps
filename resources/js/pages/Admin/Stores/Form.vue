<script setup lang="ts">
import { computed, watch } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { ArrowLeft, Loader2, Printer, Store, Target } from 'lucide-vue-next'
import { Switch } from '@/components/ui/switch'

interface StoreData {
    id: number
    name: string
    slug: string
    address: string | null
    city: string | null
    province: string | null
    postal_code: string | null
    phone: string | null
    email: string | null
    open_time: string | null
    close_time: string | null
    is_active: boolean
    receipt_print_enabled: boolean
    daily_sales_target: number
}

const props = defineProps<{ store: StoreData | null }>()

const isEdit = computed(() => !!props.store)
const pageTitle = computed(() => isEdit.value ? `Edit: ${props.store!.name}` : 'Tambah Cabang Baru')

/** Nilai awal form dari props — pakai !! agar 0/1 dari DB ikut terbaca sebagai boolean. */
function storeFormDefaults() {
    const s = props.store
    if (!s) {
        return {
            name: '',
            address: '',
            city: '',
            province: '',
            postal_code: '',
            phone: '',
            email: '',
            open_time: '',
            close_time: '',
            receipt_print_enabled: false,
            daily_sales_target: 0,
        }
    }
    return {
        name: s.name,
        address: s.address ?? '',
        city: s.city ?? '',
        province: s.province ?? '',
        postal_code: s.postal_code ?? '',
        phone: s.phone ?? '',
        email: s.email ?? '',
        open_time: s.open_time ?? '',
        close_time: s.close_time ?? '',
        receipt_print_enabled: !!s.receipt_print_enabled,
        daily_sales_target: s.daily_sales_target ?? 0,
    }
}

const form = useForm(storeFormDefaults())

// Tambahkan watcher untuk debug
watch(() => form.receipt_print_enabled, (newVal, oldVal) => {
    console.log('[WATCH receipt_print_enabled]', { old: oldVal, new: newVal })
})

// Reset form hanya saat ID toko berubah (bukan saat receipt_print_enabled berubah dari server)
let lastStoreId: number | null = null
watch(
    () => props.store?.id,
    (newId) => {
        // Hanya reset jika benar-benar ganti toko, bukan saat props.store di-refresh
        if (newId !== lastStoreId) {
            lastStoreId = newId ?? null
            const defaults = storeFormDefaults()
            console.log('[WATCH] Form reset for store ID:', newId, 'defaults:', defaults)
            form.reset(defaults)
        }
    },
    { immediate: true },
)

function submit() {
    console.log('[SUBMIT START]')
    console.log('[SUBMIT] form.receipt_print_enabled:', form.receipt_print_enabled)
    console.log('[SUBMIT] form.data():', form.data())
    
    // PAKSA inject manual — Inertia useForm bug dengan boolean field
    const submitData = {
        name: form.name,
        address: form.address,
        city: form.city,
        province: form.province,
        postal_code: form.postal_code,
        phone: form.phone,
        email: form.email,
        open_time: form.open_time,
        close_time: form.close_time,
        receipt_print_enabled: !!form.receipt_print_enabled,
        daily_sales_target: form.daily_sales_target,
    }
    
    console.log('[SUBMIT] submitData:', submitData)
    
    if (isEdit.value) {
        form.transform(() => submitData).put(`/admin/stores/${props.store!.id}`)
    } else {
        form.transform(() => submitData).post('/admin/stores')
    }
}

const provinces = [
    'Aceh', 'Bali', 'Banten', 'Bengkulu', 'DI Yogyakarta', 'DKI Jakarta',
    'Gorontalo', 'Jambi', 'Jawa Barat', 'Jawa Tengah', 'Jawa Timur',
    'Kalimantan Barat', 'Kalimantan Selatan', 'Kalimantan Tengah', 'Kalimantan Timur', 'Kalimantan Utara',
    'Kepulauan Bangka Belitung', 'Kepulauan Riau', 'Lampung', 'Maluku', 'Maluku Utara',
    'Nusa Tenggara Barat', 'Nusa Tenggara Timur', 'Papua', 'Papua Barat', 'Papua Barat Daya',
    'Papua Pegunungan', 'Papua Selatan', 'Papua Tengah', 'Riau',
    'Sulawesi Barat', 'Sulawesi Selatan', 'Sulawesi Tengah', 'Sulawesi Tenggara', 'Sulawesi Utara',
    'Sumatera Barat', 'Sumatera Selatan', 'Sumatera Utara',
]
</script>

<template>
    <AdminLayout :title="pageTitle">
        <template #headerActions>
            <Link href="/admin/stores">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
            </Link>
        </template>

        <div class="mx-auto max-w-2xl">
            <form
                :key="`store-form-${props.store?.id ?? 'new'}`"
                @submit.prevent="submit"
                class="space-y-6"
            >
                <!-- Identity -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Store class="h-4 w-4" />
                            </div>
                            <div>
                                <CardTitle>Identitas Toko</CardTitle>
                                <CardDescription>Nama dan informasi kontak toko</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Toko <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Kopi Nusantara - Cabang Selatan"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="phone">No. Telepon</Label>
                                <Input
                                    id="phone"
                                    v-model="form.phone"
                                    type="tel"
                                    inputmode="numeric"
                                    placeholder="021-12345678"
                                    :disabled="form.processing"
                                    @input="(e: Event) => { form.phone = (e.target as HTMLInputElement).value.replace(/\D/g, '') }"
                                />
                                <p v-if="form.errors.phone" class="text-xs text-destructive">{{ form.errors.phone }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="cabang@toko.com"
                                    :disabled="form.processing"
                                />
                                <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Address -->
                <Card>
                    <CardHeader>
                        <CardTitle>Alamat</CardTitle>
                        <CardDescription>Lokasi fisik toko</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="address">Alamat Lengkap</Label>
                            <Textarea
                                id="address"
                                v-model="form.address"
                                placeholder="Jl. Sudirman No. 12, RT 01/RW 02..."
                                :rows="3"
                                :disabled="form.processing"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="city">Kota / Kabupaten</Label>
                                <Input
                                    id="city"
                                    v-model="form.city"
                                    placeholder="Jakarta Selatan"
                                    :disabled="form.processing"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="province">Provinsi</Label>
                                <select
                                    id="province"
                                    v-model="form.province"
                                    :disabled="form.processing"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Pilih Provinsi</option>
                                    <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="w-1/2 space-y-2">
                            <Label for="postal_code">Kode Pos</Label>
                            <Input
                                id="postal_code"
                                v-model="form.postal_code"
                                placeholder="12345"
                                maxlength="10"
                                :disabled="form.processing"
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Targets & Alerts -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Target class="h-4 w-4" />
                            </div>
                            <div>
                                <CardTitle>Target & Notifikasi</CardTitle>
                                <CardDescription>Target harian dan pengaturan alert otomatis</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="daily_sales_target">Target Penjualan Harian (Rp)</Label>
                            <Input
                                id="daily_sales_target"
                                v-model.number="form.daily_sales_target"
                                type="number"
                                min="0"
                                placeholder="Misal: 5000000"
                                :disabled="form.processing"
                            />
                            <p class="text-[10px] text-muted-foreground">Kirim notifikasi ke owner/admin jika target harian tercapai.</p>
                            <p v-if="form.errors.daily_sales_target" class="text-xs text-destructive">{{ form.errors.daily_sales_target }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Struk kasir (mobile) -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Printer class="h-4 w-4" />
                            </div>
                            <div>
                                <CardTitle>Cetak struk (kasir)</CardTitle>
                                <CardDescription>
                                    Per toko. Jika diaktifkan, kasir di aplikasi mobile dapat memilih mencetak struk setelah pembayaran (format struk standar / PDF).
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col gap-2 rounded-lg border bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="space-y-1">
                                <p class="text-sm font-medium">Aktifkan fitur cetak struk</p>
                                <p class="text-xs text-muted-foreground">
                                    Kasir tetap memilih mau cetak atau tidak pada setiap transaksi.
                                </p>
                            </div>
                            <Switch
                                v-model:checked="form.receipt_print_enabled"
                                :disabled="form.processing"
                                @update:checked="(val) => {
                                    console.log('[SWITCH CLICKED]', val)
                                    form.receipt_print_enabled = val
                                }"
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Operating Hours -->
                <Card>
                    <CardHeader>
                        <CardTitle>Jam Operasional</CardTitle>
                        <CardDescription>Waktu buka dan tutup toko</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="open_time">Jam Buka</Label>
                                <Input
                                    id="open_time"
                                    v-model="form.open_time"
                                    type="time"
                                    :disabled="form.processing"
                                />
                                <p v-if="form.errors.open_time" class="text-xs text-destructive">{{ form.errors.open_time }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="close_time">Jam Tutup</Label>
                                <Input
                                    id="close_time"
                                    v-model="form.close_time"
                                    type="time"
                                    :disabled="form.processing"
                                />
                                <p v-if="form.errors.close_time" class="text-xs text-destructive">{{ form.errors.close_time }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-3 rounded-xl border bg-card p-4">
                    <Link href="/admin/stores">
                        <Button type="button" variant="outline" :disabled="form.processing">Batal</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="animate-spin" />
                        {{ isEdit ? 'Simpan Perubahan' : 'Buat Toko' }}
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
