<script setup lang="ts">
import { cn } from '@/lib/utils'
import { useVModel } from '@vueuse/core'

const props = defineProps<{
    modelValue?: string
    defaultValue?: string
    class?: string
    placeholder?: string
    disabled?: boolean
    rows?: number
}>()

const emits = defineEmits<{ (e: 'update:modelValue', v: string): void }>()
const modelValue = useVModel(props, 'modelValue', emits, { passive: true, defaultValue: props.defaultValue })
</script>

<template>
    <textarea
        v-bind="$attrs"
        v-model="modelValue"
        :rows="rows ?? 3"
        :class="cn(
            'flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
            props.class,
        )"
    />
</template>
