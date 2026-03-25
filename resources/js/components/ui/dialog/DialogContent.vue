<script setup lang="ts">
import { computed, inject } from 'vue'
import { DialogContent, DialogPortal, type DialogContentProps } from 'radix-vue'
import { X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = defineProps<DialogContentProps & { class?: string }>()

const isOpen = inject<ReturnType<typeof computed<boolean>>>('dialog:open', computed(() => false))
const closeDialog = inject<() => void>('dialog:close', () => {})
</script>

<template>
    <DialogPortal>
        <Teleport to="body">
            <div v-if="isOpen" class="dialog-overlay" />
        </Teleport>

        <DialogContent
            v-bind="props"
            :class="cn(
                'fixed left-1/2 top-1/2 z-[9999] flex flex-col w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-2xl border bg-background shadow-xl duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%]',
                props.class,
            )"
        >
            <button
                type="button"
                class="dialog-close-btn"
                @click="closeDialog()"
                aria-label="Tutup"
            >
                <X :size="16" />
            </button>

            <div class="dialog-body">
                <slot />
            </div>
        </DialogContent>
    </DialogPortal>
</template>

<style scoped>
.dialog-overlay {
    position: fixed;
    inset: 0;
    z-index: 9990;
    background-color: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(2px);
}

.dialog-close-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 10;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    background: transparent;
    cursor: pointer;
    color: #6b7280;
    transition: background-color 0.15s, color 0.15s;
}

.dialog-close-btn:hover {
    background-color: #f3f4f6;
    color: #111827;
}

:global(.theme-dark) .dialog-close-btn {
    border-color: #374151;
    color: #9ca3af;
}

:global(.theme-dark) .dialog-close-btn:hover {
    background-color: #1f2937;
    color: #f9fafb;
}

.dialog-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.5rem;
}
</style>
