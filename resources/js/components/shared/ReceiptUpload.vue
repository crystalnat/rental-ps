<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ImagePlus, Trash2, Eye } from 'lucide-vue-next'
import { Label } from '@/components/ui/label'

const props = defineProps<{
    modelValue: File | null
    existingUrl?: string | null
    label?: string
    hint?: string
}>()

const emit = defineEmits<{
    (event: 'update:modelValue', value: File | null): void
}>()

const MAX_SIZE_MB = 4
const inputRef = ref<HTMLInputElement | null>(null)
const previewUrl = ref<string | null>(null)
const errorMessage = ref('')
const isDragging = ref(false)

// URL objek harus dilepas agar blob tidak menumpuk di memori saat foto diganti berkali-kali
watch(() => props.modelValue, (file) => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
        previewUrl.value = null
    }
    if (file) {
        previewUrl.value = URL.createObjectURL(file)
    }
}, { immediate: true })

const displayUrl = computed(() => previewUrl.value ?? props.existingUrl ?? null)
const fileSizeLabel = computed(() =>
    props.modelValue ? `${(props.modelValue.size / 1024 / 1024).toFixed(2)} MB` : '',
)

function setFile(file: File | null) {
    errorMessage.value = ''

    if (file) {
        if (!file.type.startsWith('image/')) {
            errorMessage.value = 'File harus berupa gambar (JPG, PNG, atau WebP).'
            return
        }
        if (file.size > MAX_SIZE_MB * 1024 * 1024) {
            errorMessage.value = `Ukuran maksimal ${MAX_SIZE_MB} MB.`
            return
        }
    }

    emit('update:modelValue', file)
}

function onSelect(event: Event) {
    setFile((event.target as HTMLInputElement).files?.[0] ?? null)
}

function onDrop(event: DragEvent) {
    isDragging.value = false
    setFile(event.dataTransfer?.files?.[0] ?? null)
}

function clearFile() {
    if (inputRef.value) {
        inputRef.value.value = ''
    }
    setFile(null)
}

function openPicker() {
    inputRef.value?.click()
}
</script>

<template>
    <div class="space-y-1.5">
        <Label class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
            {{ label ?? 'Foto Bukti' }}
        </Label>

        <input
            ref="inputRef"
            type="file"
            accept="image/*"
            class="hidden"
            @change="onSelect"
        />

        <div
            v-if="!displayUrl"
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-6 text-center transition-colors"
            :class="isDragging ? 'border-primary bg-primary/5' : 'border-muted-foreground/25 hover:border-primary/60 hover:bg-muted/40'"
            role="button"
            tabindex="0"
            @click="openPicker"
            @keydown.enter.prevent="openPicker"
            @keydown.space.prevent="openPicker"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
        >
            <div class="rounded-full bg-muted p-2.5 text-muted-foreground">
                <ImagePlus class="h-5 w-5" />
            </div>
            <div>
                <p class="text-sm font-bold">Unggah foto struk</p>
                <p class="text-[11px] text-muted-foreground">
                    Klik atau seret gambar ke sini
                </p>
            </div>
            <p class="text-[10px] text-muted-foreground">JPG, PNG, atau WebP &middot; maks {{ MAX_SIZE_MB }} MB</p>
        </div>

        <div v-else class="flex items-center gap-3 rounded-lg border bg-muted/30 p-2.5">
            <img :src="displayUrl" alt="Pratinjau bukti" class="h-16 w-16 shrink-0 rounded-md border object-cover" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-bold">
                    {{ modelValue ? modelValue.name : 'Bukti tersimpan' }}
                </p>
                <p class="text-[11px] text-muted-foreground">
                    {{ modelValue ? fileSizeLabel : 'Pilih file baru untuk mengganti' }}
                </p>
                <div class="mt-1.5 flex items-center gap-3">
                    <button
                        type="button"
                        class="text-[11px] font-bold text-primary hover:underline"
                        @click="openPicker"
                    >
                        Ganti
                    </button>
                    <a
                        :href="displayUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 text-[11px] font-bold text-muted-foreground hover:text-foreground"
                    >
                        <Eye class="h-3 w-3" />
                        Lihat
                    </a>
                    <button
                        v-if="modelValue"
                        type="button"
                        class="inline-flex items-center gap-1 text-[11px] font-bold text-destructive hover:underline"
                        @click="clearFile"
                    >
                        <Trash2 class="h-3 w-3" />
                        Hapus
                    </button>
                </div>
            </div>
        </div>

        <p v-if="errorMessage" class="text-[11px] font-medium text-destructive">{{ errorMessage }}</p>
        <p v-else-if="hint" class="text-[10px] italic text-muted-foreground">{{ hint }}</p>
    </div>
</template>
