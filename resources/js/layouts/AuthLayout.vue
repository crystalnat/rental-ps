<script setup lang="ts">
import { CreditCard } from 'lucide-vue-next'

defineProps<{ title?: string; description?: string }>()
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-muted/30 p-4">
        <div class="w-full max-w-sm">
            <!-- Logo -->
            <div class="mb-8 flex flex-col items-center gap-3 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg">
                    <CreditCard class="h-6 w-6" />
                </div>
                <div>
                    <h1 v-if="title" class="text-xl font-bold">{{ title }}</h1>
                    <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
                </div>
            </div>

            <!-- Card -->
            <div class="rounded-2xl border bg-card p-6 shadow-sm">
                <slot />
            </div>
        </div>
    </div>
</template>
