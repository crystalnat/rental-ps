<script setup lang="ts">
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'

defineProps<{
    open: boolean
    title: string
    description: string
    confirmLabel?: string
    cancelLabel?: string
    variant?: 'default' | 'destructive'
    loading?: boolean
}>()

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void
    (e: 'confirm'): void
}>()

function onConfirm() {
    emit('confirm')
}

function onCancel() {
    emit('update:open', false)
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" :disabled="loading" @click="onCancel">
                    {{ cancelLabel ?? 'Batal' }}
                </Button>
                <Button
                    :variant="variant === 'destructive' ? 'destructive' : 'default'"
                    :disabled="loading"
                    @click="onConfirm"
                >
                    <span v-if="loading" class="animate-pulse">Memproses...</span>
                    <span v-else>{{ confirmLabel ?? (variant === 'destructive' ? 'Ya, Hapus' : 'Ya, Lanjutkan') }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
