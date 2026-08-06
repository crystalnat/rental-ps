<script setup lang="ts">
import { Menu, SunMedium, MoonStar } from 'lucide-vue-next'
import { useTheme } from '@/lib/theme'
import NotificationDrawer from '@/components/NotificationDrawer.vue'

defineProps<{ title?: string }>()
defineEmits<{ toggleSidebar: [] }>()

const { theme, toggleTheme } = useTheme()
</script>

<template>
    <header class="sticky top-0 z-10 flex h-14 items-center gap-2 border-b border-red-950/50 bg-red-900 px-3 text-white shadow-md sm:h-16 sm:gap-4 sm:px-6">
        <button
            class="shrink-0 rounded-md p-1.5 text-white/90 hover:bg-red-800 hover:text-white lg:hidden"
            @click="$emit('toggleSidebar')"
        >
            <Menu class="h-5 w-5" />
        </button>

        <div class="min-w-0 flex-1">
            <h1 v-if="title" class="truncate text-base font-semibold text-white sm:text-lg">{{ title }}</h1>
            <slot v-else name="title" />
        </div>

        <div class="header-actions flex shrink-0 items-center gap-1.5 sm:gap-2">
            <slot name="actions" />
            <NotificationDrawer />
            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/30 bg-white/10 text-white shadow-sm hover:bg-red-800"
                @click="toggleTheme"
            >
                <SunMedium v-if="theme === 'light'" class="h-4 w-4" />
                <MoonStar v-else class="h-4 w-4" />
            </button>
        </div>
    </header>
</template>

<style scoped>
.header-actions :deep(button),
.header-actions :deep(a) {
    border-color: rgba(255, 255, 255, 0.5) !important;
    background-color: rgba(255, 255, 255, 0.15) !important;
    color: white !important;
}
.header-actions :deep(button:hover),
.header-actions :deep(a:hover) {
    background-color: rgba(255, 255, 255, 0.25) !important;
    border-color: rgba(255, 255, 255, 0.7) !important;
}
</style>
