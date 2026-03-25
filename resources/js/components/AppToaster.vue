<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'
import { CheckCircle2, AlertTriangle, XCircle, X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const page = usePage<PageProps>()

const flash = computed(() => page.props?.flash)
const visible = ref(false)
const type = ref<'success' | 'error' | 'warning'>('success')
const message = ref<string | null>(null)
let timeoutId: number | null = null

watchEffect(() => {
    if (flash.value?.success) {
        type.value = 'success'
        message.value = String(flash.value.success)
        show()
    } else if (flash.value?.error) {
        type.value = 'error'
        message.value = String(flash.value.error)
        show()
    } else if (flash.value?.warning) {
        type.value = 'warning'
        message.value = String(flash.value.warning)
        show()
    }
})

function show() {
    visible.value = true
    if (timeoutId) {
        window.clearTimeout(timeoutId)
    }
    timeoutId = window.setTimeout(() => {
        visible.value = false
    }, 4000)
}

function close() {
    visible.value = false
    if (timeoutId) {
        window.clearTimeout(timeoutId)
        timeoutId = null
    }
}

const iconClass = computed(() =>
    cn(
        'h-5 w-5',
        type.value === 'success' && 'text-success',
        type.value === 'error' && 'text-destructive',
        type.value === 'warning' && 'text-warning',
    ),
)

const containerClass = computed(() =>
    cn(
        'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg ring-1 ring-black/10',
        'bg-card/98 text-card-foreground',
    ),
)
</script>

<template>
    <div
        aria-live="polite"
        class="pointer-events-none fixed inset-0 z-[60] flex items-start justify-end px-4 py-4 sm:p-6"
    >
        <transition
            enter-active-class="transform ease-out duration-200 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transform ease-in duration-150 transition"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="visible && message" class="pointer-events-auto sm:max-w-sm">
                <div :class="containerClass">
                    <div class="mt-0.5">
                        <CheckCircle2 v-if="type === 'success'" :class="iconClass" />
                        <XCircle v-else-if="type === 'error'" :class="iconClass" />
                        <AlertTriangle v-else :class="iconClass" />
                    </div>
                    <div class="flex-1">
                        <p class="font-medium">
                            {{ type === 'success' ? 'Berhasil' : type === 'error' ? 'Terjadi Kesalahan' : 'Perhatian' }}
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                            {{ message }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="mt-0.5 inline-flex rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        @click="close"
                    >
                        <span class="sr-only">Tutup</span>
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>

