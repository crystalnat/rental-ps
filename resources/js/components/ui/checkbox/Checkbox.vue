<script setup lang="ts">
import { CheckboxRoot, CheckboxIndicator, type CheckboxRootProps } from 'radix-vue'
import { Check } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = defineProps<CheckboxRootProps & { class?: string }>()
const emits = defineEmits<{ 'update:checked': [v: boolean] }>()
</script>

<template>
    <CheckboxRoot
        v-bind="props"
        :class="cn(
            'peer h-4 w-4 shrink-0 rounded-sm border border-input shadow transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary',
            props.class,
        )"
        @update:checked="emits('update:checked', $event)"
    >
        <CheckboxIndicator class="flex items-center justify-center text-current">
            <Check class="h-3 w-3" />
        </CheckboxIndicator>
    </CheckboxRoot>
</template>
