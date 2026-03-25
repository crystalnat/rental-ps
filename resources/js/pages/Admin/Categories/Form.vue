<script setup lang="ts">
import { computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { ArrowLeft, Loader2, Tags } from 'lucide-vue-next'

interface CategoryData {
    id: number
    name: string
    slug: string
    icon: string | null
    color: string | null
    sort_order: number
    is_active: boolean
}

const props = defineProps<{ category: CategoryData | null }>()

const isEdit = computed(() => !!props.category)
const pageTitle = computed(() =>
    isEdit.value ? `Edit: ${props.category!.name}` : 'Tambah Kategori Baru',
)

const form = useForm({
    name:       props.category?.name       ?? '',
    color:      props.category?.color     ?? '',
    sort_order: props.category?.sort_order ?? 0,
    is_active:  props.category?.is_active  ?? true,
})

function submit() {
    if (isEdit.value) {
        form.put(`/admin/categories/${props.category!.id}`)
    } else {
        form.post('/admin/categories')
    }
}

const presetColors = [
    '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#3b82f6', '#8b5cf6', '#ec4899', '#64748b',
]
</script>

<template>
    <AdminLayout :title="pageTitle">
        <template #headerActions>
            <Link href="/admin/categories">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
            </Link>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Tags class="h-4 w-4" />
                            </div>
                            <div>
                                <CardTitle>Data Kategori</CardTitle>
                                <CardDescription>
                                    Nama dan tampilan kategori untuk mengelompokkan produk
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nama Kategori <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Contoh: Makanan, Minuman, Snack"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="color">Warna (opsional)</Label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="color"
                                    v-model="form.color"
                                    type="color"
                                    class="h-9 w-14 cursor-pointer rounded border border-input bg-transparent p-0.5"
                                    :disabled="form.processing"
                                />
                                <Input
                                    v-model="form.color"
                                    placeholder="#3b82f6"
                                    class="font-mono"
                                    :disabled="form.processing"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">Format hex, contoh: #3b82f6</p>
                            <p v-if="form.errors.color" class="text-xs text-destructive">{{ form.errors.color }}</p>
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <button
                                    v-for="c in presetColors"
                                    :key="c"
                                    type="button"
                                    class="h-6 w-6 rounded-full border-2 transition-transform hover:scale-110"
                                    :class="{ 'border-foreground ring-2 ring-offset-2': form.color === c }"
                                    :style="{ backgroundColor: c }"
                                    @click="form.color = c"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="sort_order">Urutan Tampil</Label>
                            <Input
                                id="sort_order"
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                :disabled="form.processing"
                            />
                            <p class="text-xs text-muted-foreground">Angka lebih kecil tampil lebih dulu</p>
                            <p v-if="form.errors.sort_order" class="text-xs text-destructive">{{ form.errors.sort_order }}</p>
                        </div>

                        <div class="flex items-center gap-2 rounded-lg border p-3">
                            <Checkbox
                                id="is_active"
                                :checked="form.is_active"
                                @update:checked="(v) => form.is_active = !!v"
                            />
                            <Label for="is_active" class="cursor-pointer text-sm font-medium">
                                Aktif — kategori tampil di daftar pilihan
                            </Label>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex items-center justify-end gap-3 rounded-xl border bg-card p-4">
                    <Link href="/admin/categories">
                        <Button type="button" variant="outline" :disabled="form.processing">Batal</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="animate-spin" />
                        {{ isEdit ? 'Simpan Perubahan' : 'Buat Kategori' }}
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
