<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

interface Props {
    links: {
        url: string | null
        label: string
        active: boolean
    }[]
    from?: number
    to?: number
    total?: number
}

defineProps<Props>()
</script>

<template>
    <div class="flex items-center justify-between">
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-muted-foreground" v-if="from !== undefined && to !== undefined && total !== undefined">
                    Showing
                    <span class="font-medium">{{ from }}</span>
                    to
                    <span class="font-medium">{{ to }}</span>
                    of
                    <span class="font-medium">{{ total }}</span>
                    results
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <template v-for="(link, key) in links" :key="key">
                        <div
                            v-if="link.url === null"
                            class="relative inline-flex items-center px-4 py-2 border border-input bg-muted/50 text-sm font-medium text-muted-foreground"
                            :class="{ 'rounded-l-md': key === 0, 'rounded-r-md': key === links.length - 1 }"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            class="relative inline-flex items-center px-4 py-2 border text-sm font-medium transition-colors"
                            :class="[
                                link.active 
                                    ? 'z-10 bg-primary border-primary text-primary-foreground focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary' 
                                    : 'bg-background border-input text-muted-foreground hover:bg-muted/50',
                                { 'rounded-l-md': key === 0, 'rounded-r-md': key === links.length - 1 }
                            ]"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </div>

        <!-- Mobile view -->
        <div class="flex-1 flex justify-between sm:hidden">
            <template v-for="(link, key) in links" :key="key">
                <Link
                    v-if="link.url && (link.label.includes('Previous') || link.label.includes('Next'))"
                    :href="link.url"
                    class="relative inline-flex items-center px-4 py-2 border border-input text-sm font-medium rounded-md text-muted-foreground bg-background hover:bg-muted/50"
                >
                    {{ link.label.includes('Previous') ? 'Sebelumnya' : 'Berikutnya' }}
                </Link>
            </template>
        </div>
    </div>
</template>
