<script setup lang="ts">
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

defineProps<{
    label: string
    sortKey: string
    currentSortKey: string | null
    sortDir: 'asc' | 'desc'
    align?: 'left' | 'center' | 'right'
    classNames?: string
}>()

const emit = defineEmits<{ (e: 'sort', key: string): void }>()
</script>

<template>
    <th
        :class="cn(
            'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground cursor-pointer select-none transition-colors hover:text-foreground',
            align === 'center' && 'text-center',
            align === 'right' && 'text-right',
            classNames,
        )"
        @click="emit('sort', sortKey)"
    >
        <span class="inline-flex items-center gap-1">
            {{ label }}
            <span v-if="currentSortKey === sortKey" class="text-foreground">
                <ChevronUp v-if="sortDir === 'asc'" class="h-3.5 w-3.5" />
                <ChevronDown v-else class="h-3.5 w-3.5" />
            </span>
            <ChevronsUpDown v-else class="h-3.5 w-3.5 opacity-40" />
        </span>
    </th>
</template>
