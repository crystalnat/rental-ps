<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
    modelValue?: number | null
    class?: string
    placeholder?: string
    disabled?: boolean
    id?: string
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', payload: number): void
}>()

// Tampilkan ribuan (1.000.000); model tetap number murni.
const display = computed(() =>
    props.modelValue ? Number(props.modelValue).toLocaleString('id-ID') : '',
)

function onInput(e: Event) {
    const digits = (e.target as HTMLInputElement).value.replace(/\D/g, '')
    emits('update:modelValue', digits ? Number(digits) : 0)
}
</script>

<template>
    <div class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">Rp</span>
        <input
            :id="id"
            :value="display"
            inputmode="numeric"
            :placeholder="placeholder"
            :disabled="disabled"
            @input="onInput"
            :class="cn(
                'flex h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )"
        />
    </div>
</template>
