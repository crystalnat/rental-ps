<script setup lang="ts">
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { AlertTriangle, XCircle, Info } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = withDefaults(
    defineProps<{
        open: boolean
        title?: string
        message: string
        variant?: 'info' | 'error' | 'warning'
    }>(),
    {
        title: undefined,
        variant: 'info',
    },
)

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void
}>()

const computedTitle = () => {
    if (props.title) return props.title
    if (props.variant === 'error') return 'Terjadi Kesalahan'
    if (props.variant === 'warning') return 'Perhatian'
    return 'Informasi'
}

const iconClass = () =>
    cn(
        'h-5 w-5 shrink-0',
        props.variant === 'error' && 'text-destructive',
        props.variant === 'warning' && 'text-warning',
        props.variant === 'info' && 'text-primary',
    )

function close() {
    emit('update:open', false)
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <div :class="iconClass()">
                        <XCircle v-if="variant === 'error'" class="h-5 w-5" />
                        <AlertTriangle v-else-if="variant === 'warning'" class="h-5 w-5" />
                        <Info v-else class="h-5 w-5" />
                    </div>
                    <div class="flex-1">
                        <DialogTitle>{{ computedTitle() }}</DialogTitle>
                        <DialogDescription class="mt-1">
                            {{ message }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>
            <DialogFooter>
                <Button @click="close">OK</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
