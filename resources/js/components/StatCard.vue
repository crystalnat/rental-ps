<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'

type StatVariant = 'default' | 'success' | 'warning' | 'primary' | 'destructive' | 'muted'

const props = withDefaults(
    defineProps<{
        variant?: StatVariant
        class?: string
    }>(),
    { variant: 'default' },
)

const variantStyles = computed(() => {
    const base = 'relative overflow-hidden rounded-xl border shadow-sm transition-shadow hover:shadow-md'
    const variants: Record<StatVariant, string> = {
        default:
            'border-border/80 bg-gradient-to-br from-card to-muted/30',
        success:
            'border-emerald-500/30 bg-gradient-to-br from-emerald-50/90 to-emerald-100/40 dark:from-emerald-950/40 dark:to-emerald-900/20',
        warning:
            'border-amber-500/30 bg-gradient-to-br from-amber-50/90 to-amber-100/40 dark:from-amber-950/40 dark:to-amber-900/20',
        primary:
            'border-primary/30 bg-gradient-to-br from-primary/10 to-primary/5 dark:from-primary/20 dark:to-primary/10',
        destructive:
            'border-destructive/30 bg-gradient-to-br from-red-50/90 to-red-100/40 dark:from-red-950/40 dark:to-red-900/20',
        muted:
            'border-border/80 bg-gradient-to-br from-muted/50 to-muted/20',
    }
    return cn(base, variants[props.variant], props.class)
})

const iconVariantStyles = computed(() => {
    const variants: Record<StatVariant, string> = {
        default: 'bg-muted/80 text-muted-foreground',
        success: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400',
        warning: 'bg-amber-500/20 text-amber-600 dark:text-amber-400',
        primary: 'bg-primary/20 text-primary',
        destructive: 'bg-destructive/20 text-destructive',
        muted: 'bg-muted text-muted-foreground',
    }
    return variants[props.variant]
})
</script>

<template>
    <div :class="variantStyles">
        <!-- Decorative corner -->
        <div
            class="absolute -right-8 -top-8 h-24 w-24 rounded-full opacity-10"
            :class="{
                'bg-emerald-500': props.variant === 'success',
                'bg-amber-500': props.variant === 'warning',
                'bg-primary': props.variant === 'primary',
                'bg-destructive': props.variant === 'destructive',
                'bg-muted-foreground': props.variant === 'default' || props.variant === 'muted',
            }"
        />
        <div class="relative flex flex-col p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-muted-foreground">
                        <slot name="title" />
                    </p>
                    <div class="mt-2">
                        <slot name="value" />
                    </div>
                    <p v-if="$slots.subtitle" class="mt-1.5 text-xs text-muted-foreground">
                        <slot name="subtitle" />
                    </p>
                </div>
                <div
                    v-if="$slots.icon"
                    :class="cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-lg', iconVariantStyles)"
                >
                    <slot name="icon" />
                </div>
            </div>
        </div>
    </div>
</template>
