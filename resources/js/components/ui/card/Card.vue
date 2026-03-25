<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'

type CardVariant = 'default' | 'elevated' | 'bordered'

const props = withDefaults(
    defineProps<{ variant?: CardVariant; class?: string }>(),
    { variant: 'default' },
)

const variantStyles = computed(() => {
    const base = 'rounded-xl text-card-foreground'
    const variants: Record<CardVariant, string> = {
        default: 'border border-border/80 bg-card shadow-sm',
        elevated:
            'border border-border/60 bg-gradient-to-b from-card to-muted/20 shadow-md',
        bordered:
            'border-2 border-border bg-card shadow-sm',
    }
    return cn(base, variants[props.variant], props.class)
})
</script>
<template>
    <div :class="variantStyles">
        <slot />
    </div>
</template>
