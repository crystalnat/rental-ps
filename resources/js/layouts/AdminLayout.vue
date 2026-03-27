<script setup lang="ts">
import { ref } from 'vue'
import AppSidebar from '@/components/AppSidebar.vue'
import AppHeader from '@/components/AppHeader.vue'
import AppToaster from '@/components/AppToaster.vue'

defineProps<{ title?: string; fullWidth?: boolean }>()

const sidebarOpen = ref(false)
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-background">
        <!-- Sidebar (desktop always visible, mobile overlay) -->
        <div
            :class="[
                'fixed inset-0 z-30 bg-black/50 transition-opacity lg:hidden',
                sidebarOpen ? 'opacity-100' : 'pointer-events-none opacity-0',
            ]"
            @click="sidebarOpen = false"
        />

        <div
            :class="[
                'fixed inset-y-0 left-0 z-40 transition-transform duration-300 lg:static lg:translate-x-0 print:hidden',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <AppSidebar />
        </div>

        <!-- Main content -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <div class="print:hidden">
                <AppHeader :title="title" @toggle-sidebar="sidebarOpen = !sidebarOpen">
                    <template v-if="$slots.headerTitle" #title>
                        <slot name="headerTitle" />
                    </template>
                    <template v-if="$slots.headerActions" #actions>
                        <slot name="headerActions" />
                    </template>
                </AppHeader>
            </div>

            <main class="relative flex-1 overflow-y-auto bg-gradient-to-b from-background to-background/95 p-4 sm:p-6 print:p-0 print:bg-white print:overflow-visible">
                <div :class="['mx-auto w-full space-y-4 print:space-y-6', fullWidth ? 'max-w-full' : 'max-w-6xl']">
                    <slot />
                </div>
                <div class="print:hidden">
                    <AppToaster />
                </div>
            </main>
        </div>
    </div>
</template>
