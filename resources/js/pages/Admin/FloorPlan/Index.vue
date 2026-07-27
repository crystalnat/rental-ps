<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import AlertDialog from '@/components/AlertDialog.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    ArrowLeft, LayoutGrid, Plus, Pencil, Trash2, Map, Printer,
} from 'lucide-vue-next'

interface Floor {
    id: number
    name: string
    width_meters: number
    length_meters: number
    sort_order: number
    is_active: boolean
    tables_count: number
    elements_count: number
}

interface Store {
    id: number
    name: string
    slug: string
}

const props = defineProps<{
    store: Store
    floors: Floor[]
}>()

const showAddDialog = ref(false)
const addForm = ref({
    name: '',
    width_meters: 10,
    length_meters: 15,
})

const confirmState = ref<{
    open: boolean
    title: string
    description: string
    variant: 'default' | 'destructive'
    confirmLabel?: string
    onConfirm: () => void
}>({ open: false, title: '', description: '', variant: 'destructive', onConfirm: () => {} })
const alertState = ref<{ open: boolean; message: string }>({ open: false, message: '' })

function showConfirm(config: typeof confirmState.value) {
    confirmState.value = { ...config, open: true }
}

function showAlert(message: string) {
    alertState.value = { open: true, message }
}

function submitAdd() {
    router.post(`/admin/stores/${props.store.id}/floor-plan`, addForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showAddDialog.value = false
            addForm.value = { name: '', width_meters: 10, length_meters: 15 }
        },
        onError: (errors) => {
            showAlert('Gagal: ' + Object.values(errors).join(', '))
        },
    })
}

function deleteFloor(floor: Floor) {
    showConfirm({
        title: 'Hapus Lantai',
        description: `Hapus lantai "${floor.name}"? Meja dan elemen akan dipindahkan/dihapus.`,
        variant: 'destructive',
        confirmLabel: 'Ya, Hapus',
        onConfirm: () => {
            router.delete(`/admin/stores/${props.store.id}/floor-plan/${floor.id}`, {
                preserveScroll: true,
            })
        },
    })
}
</script>

<template>
    <AdminLayout :title="`Rental PS — ${store.name}`">
        <template #headerActions>
            <Link href="/admin/dashboard">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
            </Link>
        </template>

        <!-- Header -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Lantai & Area PS</h2>
                <p class="text-sm text-muted-foreground">
                    Atur area rental PS, lalu tambahkan unit PS, kasir, dan elemen lainnya.
                </p>
            </div>
            <Button @click="showAddDialog = true">
                <Plus class="h-4 w-4" />
                Tambah Lantai
            </Button>
        </div>

        <!-- Floors List (Lantai 1, Lantai 2, ... berurutan ke bawah) -->
        <div v-if="floors.length > 0" class="flex flex-col gap-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="floor in floors"
                :key="floor.id"
                :href="`/admin/stores/${store.id}/floor-plan/${floor.id}`"
                class="group relative block rounded-2xl border bg-card p-5 shadow-sm transition-all hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Map class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ floor.name }}</h3>
                            <p class="text-sm text-muted-foreground">
                                {{ floor.width_meters }}m × {{ floor.length_meters }}m
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100" @click.stop>
                        <a
                            v-if="floor.tables_count > 0"
                            :href="`/admin/stores/${store.id}/floor-plan/${floor.id}/print-qr`"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            title="Cetak QR Meja"
                        >
                            <Printer class="h-4 w-4" />
                        </a>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-destructive transition-colors hover:bg-destructive/10"
                            @click="deleteFloor(floor)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex gap-4 text-xs text-muted-foreground">
                    <span>{{ floor.tables_count }} unit PS</span>
                    <span>{{ floor.elements_count }} elemen</span>
                </div>

                <div class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed py-2.5 text-sm font-medium transition-colors group-hover:border-primary/50 group-hover:bg-muted/50">
                    <LayoutGrid class="h-4 w-4" />
                    Buka Area Rental PS
                </div>
            </Link>
            </div>

            <!-- Tambah Lantai di bawah -->
            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-muted-foreground/40 py-8 text-muted-foreground transition-colors hover:border-primary/50 hover:bg-primary/5 hover:text-primary"
                @click="showAddDialog = true"
            >
                <Plus class="h-6 w-6" />
                <span class="font-medium">Tambah Lantai</span>
            </button>
        </div>

        <!-- Empty State -->
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed py-20 text-center text-muted-foreground"
        >
            <Map class="mb-4 h-16 w-16 opacity-30" />
            <p class="font-medium">Belum ada lantai</p>
            <p class="mt-1 text-sm">Buat lantai pertama dengan dimensi (meter), lalu atur meja dan elemen di denah.</p>
            <Button class="mt-4" @click="showAddDialog = true">
                <Plus class="h-4 w-4" />
                Tambah Lantai Pertama
            </Button>
        </div>

        <!-- Add Floor Dialog -->
        <Dialog :open="showAddDialog" @update:open="showAddDialog = $event">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Tambah Lantai</DialogTitle>
                    <DialogDescription>
                        Masukkan nama dan dimensi area dalam meter. Skala 1m = 80px di editor.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="floor_name">Nama Lantai</Label>
                        <Input id="floor_name" v-model="addForm.name" placeholder="Lantai 1" class="mt-1.5" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="width">Lebar (m)</Label>
                            <Input id="width" v-model.number="addForm.width_meters" type="number" min="1" max="100" step="0.5" class="mt-1.5" />
                        </div>
                        <div>
                            <Label for="length">Panjang (m)</Label>
                            <Input id="length" v-model.number="addForm.length_meters" type="number" min="1" max="100" step="0.5" class="mt-1.5" />
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddDialog = false">Batal</Button>
                    <Button @click="submitAdd" :disabled="!addForm.name || addForm.width_meters < 1 || addForm.length_meters < 1">
                        Tambah
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="confirmState.open"
            :title="confirmState.title"
            :description="confirmState.description"
            :variant="confirmState.variant"
            :confirm-label="confirmState.confirmLabel"
            @update:open="(v) => { if (!v) confirmState.open = false }"
            @confirm="confirmState.onConfirm(); confirmState.open = false"
        />
        <AlertDialog
            :open="alertState.open"
            :message="alertState.message"
            variant="error"
            @update:open="(v) => { alertState.open = v }"
        />
    </AdminLayout>
</template>
