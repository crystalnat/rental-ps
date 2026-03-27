<script setup lang="ts">
import { computed } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import { ArrowLeft, Loader2, Tag, Percent, Banknote, Calendar, Layers, ShieldCheck } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Card, CardContent } from '@/components/ui/card'
import AdminLayout from '@/layouts/AdminLayout.vue'

interface PromoData {
    id: number
    code: string
    type: 'percentage' | 'nominal'
    value: number | string
    min_purchase: number | string
    max_discount: number | string | null
    valid_from: string | null
    valid_until: string | null
    quota: number | string | null
    is_active: boolean
}

const props = defineProps<{
    promo: PromoData | null
    isEdit?: boolean
}>()

const form = useForm({
    code:         props.promo?.code         ?? '',
    type:         props.promo?.type         ?? 'percentage',
    value:        props.promo?.value        ?? 0,
    min_purchase: props.promo?.min_purchase ?? 0,
    max_discount: props.promo?.max_discount ?? '',
    valid_from:   props.promo?.valid_from   ?? '',
    valid_until:  props.promo?.valid_until  ?? '',
    quota:        props.promo?.quota        ?? '',
    is_active:    props.promo?.is_active    ?? true,
})

const pageTitle = computed(() => props.isEdit ? `Ubah Promo: ${props.promo?.code}` : 'Tambah Promo Baru')

function submit() {
    const payload = {
        ...form.data(),
        max_discount: form.max_discount === '' ? null : form.max_discount,
        quota: form.quota === '' ? null : form.quota,
    }

    if (props.isEdit && props.promo) {
        form.put(`/admin/promos/${props.promo.id}`, {
            onSuccess: () => {},
        })
    } else {
        form.post('/admin/promos', {
            onSuccess: () => {},
        })
    }
}

function goBack() {
    router.visit('/admin/promos')
}
</script>

<template>
    <AdminLayout>
        <template #headerTitle>
            <div class="flex items-center gap-3">
                <Link href="/admin/promos" class="text-white hover:text-white/80 transition-colors">
                    <ArrowLeft class="h-5 w-5" />
                </Link>
                <h1 class="text-lg font-semibold text-white">{{ pageTitle }}</h1>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Header Interna -->
            <div class="flex items-center justify-between gap-4">
                <Button variant="ghost" size="sm" @click="goBack" class="px-0 hover:bg-transparent text-foreground">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali
                </Button>
                <h1 class="text-2xl font-bold text-foreground">{{ pageTitle }}</h1>
            </div>

            <form @submit.prevent="submit" class="mx-auto max-w-3xl space-y-6">
                <!-- Info Dasar Promo -->
                <Card>
                    <CardContent class="p-6">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-4 border-b pb-2 flex items-center gap-2">
                            <Tag class="h-4 w-4" /> Informasi Voucher
                        </h3>
                        
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="code" class="text-sm font-medium">Kode Promo <span class="text-destructive">*</span></Label>
                                <Input id="code" v-model="form.code" placeholder="DISKON50" class="uppercase font-bold tracking-wider h-11 text-lg" required />
                                <p v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</p>
                                <p class="text-[10px] text-muted-foreground italic">Gunakan kapital & singkat (Contoh: KSSMEI)</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="type" class="text-sm font-medium">Tipe Potongan <span class="text-destructive">*</span></Label>
                                <select 
                                    id="type" 
                                    v-model="form.type"
                                    class="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    required
                                >
                                    <option value="percentage">Persentase (%)</option>
                                    <option value="nominal">Nominal (Rp)</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label for="value" class="text-sm font-medium">Jumlah Potongan <span class="text-destructive">*</span></Label>
                                <div class="relative">
                                    <div v-if="form.type === 'nominal'" class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center text-muted-foreground text-sm font-bold">Rp</div>
                                    <Input 
                                        id="value" 
                                        v-model="form.value" 
                                        type="number" 
                                        min="0" 
                                        class="h-11"
                                        :class="form.type === 'nominal' ? 'pl-9' : 'pr-9'"
                                        required 
                                    />
                                    <div v-if="form.type === 'percentage'" class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center text-muted-foreground font-bold">%</div>
                                </div>
                                <p v-if="form.errors.value" class="text-xs text-destructive">{{ form.errors.value }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="max_discount" class="text-sm font-medium">Batas Maksimal Potongan</Label>
                                <div class="relative">
                                    <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center text-muted-foreground text-sm">Rp</div>
                                    <Input 
                                        id="max_discount" 
                                        v-model="form.max_discount" 
                                        type="number" 
                                        min="0" 
                                        class="h-11 pl-9" 
                                        placeholder="0 (Tanpa batas)"
                                        :disabled="form.type === 'nominal'"
                                    />
                                </div>
                                <p class="text-[10px] text-muted-foreground italic">Hanya berlaku untuk tipe persentase</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Syarat & Kuota -->
                <Card>
                    <CardContent class="p-6">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-4 border-b pb-2 flex items-center gap-2">
                            <Layers class="h-4 w-4" /> Syarat & Batasan
                        </h3>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="min_purchase" class="text-sm font-medium">Minimal Pembelanjaan <span class="text-destructive">*</span></Label>
                                <div class="relative">
                                    <div class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center text-muted-foreground text-sm">Rp</div>
                                    <Input id="min_purchase" v-model="form.min_purchase" type="number" min="0" class="h-11 pl-9" required />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="quota" class="text-sm font-medium">Kuota Penggunaan (Opsional)</Label>
                                <Input id="quota" v-model="form.quota" type="number" min="1" class="h-11" placeholder="999+ (Kosongkan jika Unlimited)" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Masa Berlaku -->
                <Card>
                    <CardContent class="p-6">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-4 border-b pb-2 flex items-center gap-2">
                            <Calendar class="h-4 w-4" /> Masa Berlaku
                        </h3>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="valid_from" class="text-sm font-medium">Berlaku Mulai</Label>
                                <Input id="valid_from" v-model="form.valid_from" type="date" class="h-11" />
                            </div>

                            <div class="space-y-2">
                                <Label for="valid_until" class="text-sm font-medium">Berlaku Sampai</Label>
                                <Input id="valid_until" v-model="form.valid_until" type="date" class="h-11" />
                            </div>
                        </div>
                        <p class="mt-3 text-[10px] text-muted-foreground italic opacity-70">Jika dikosongkan, promo akan berlaku selamanya.</p>
                    </CardContent>
                </Card>

                <!-- Pengaturan -->
                <Card>
                    <CardContent class="p-6">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-4 border-b pb-2 flex items-center gap-2">
                            <ShieldCheck class="h-4 w-4" /> Kontrol Aktivasi
                        </h3>
                        <div class="flex items-center justify-between rounded-lg border p-4 bg-muted/5">
                            <div class="space-y-0.5">
                                <Label class="text-base">Status Aktif</Label>
                                <p class="text-xs text-muted-foreground">Promo hanya dapat diklaim saat status AKTIF.</p>
                            </div>
                            <Switch :checked="form.is_active" @update:checked="form.is_active = $event" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex items-center justify-end gap-3 pb-8">
                    <Button type="button" variant="outline" class="px-8 h-12" @click="goBack" :disabled="form.processing">Batal</Button>
                    <Button type="submit" class="px-10 h-12 text-base font-bold shadow-lg" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                        {{ isEdit ? 'Simpan Perubahan' : 'Buat Promo' }}
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
