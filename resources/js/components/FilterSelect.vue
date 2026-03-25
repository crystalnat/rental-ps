<script setup lang="ts">
import { Label } from '@/components/ui/label'

defineProps<{
    modelValue?: string
    label?: string
    placeholder?: string
    options: { value: string; label: string }[]
    id?: string
}>()

defineEmits<{ (e: 'update:modelValue', v: string): void }>()
</script>

<template>
    <div class="flex flex-col w-full md:w-auto" :class="label ? 'gap-1.5' : ''">
        <Label v-if="label" :for="id ?? label" class="text-xs font-medium text-muted-foreground">
            {{ label }}
        </Label>
        <select
            :id="id ?? label"
            :value="modelValue"
            class="filter-select flex h-9 min-w-[120px] w-full cursor-pointer rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
    </div>
</template>

<style scoped>
.filter-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.25rem 1.25rem;
}

:global(.theme-dark) .filter-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
}
</style>
