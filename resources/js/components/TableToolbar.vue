<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Search, X } from 'lucide-vue-next'

defineProps<{
    modelValue?: string
    searchPlaceholder?: string
    hasActiveFilters?: boolean
}>()

defineEmits<{ (e: 'update:modelValue', v: string): void; (e: 'clear'): void }>()
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-center gap-3 rounded-xl border bg-muted/20 p-3">
        <!-- Search -->
        <div class="relative flex-1 min-w-0">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                :model-value="modelValue"
                :placeholder="searchPlaceholder ?? 'Cari...'"
                class="pl-9 h-10 md:h-9"
                @update:model-value="$emit('update:modelValue', $event)"
            />
        </div>

        <!-- Filters & Actions -->
        <div v-if="$slots.filters || hasActiveFilters" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <div v-if="$slots.filters" class="flex flex-1 items-center gap-2 overflow-x-auto pb-1 sm:pb-0 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                <slot name="filters" />
            </div>
            
            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                class="h-9 shrink-0 text-muted-foreground hover:text-destructive px-2"
                @click="$emit('clear')"
            >
                <X class="mr-1 h-3.5 w-3.5" />
                Reset
            </Button>
        </div>
    </div>
</template>
