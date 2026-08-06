<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Bell, Package, ShoppingCart, Target, X, Check, ExternalLink } from 'lucide-vue-next'
import { Card, CardContent } from '@/components/ui/card'
import axios from 'axios'

interface Notification {
    id: string
    data: {
        title: string
        message: string
        level: string
        type: 'low_stock' | 'new_order' | 'target_reached'
        link?: string
    }
    created_at: string
}

const notifications = ref<Notification[]>([])
const isOpen = ref(false)
const isLoading = ref(false)
let pollInterval: any = null

async function fetchNotifications() {
    try {
        const res = await axios.get('/admin/notifications')
        notifications.value = res.data
    } catch (e) {
        console.error('Failed to fetch notifications', e)
    }
}

async function markAsRead(id: string) {
    try {
        await axios.post('/admin/notifications/mark-read', { id })
        notifications.value = notifications.value.filter(n => n.id !== id)
    } catch (e) {
        console.error('Failed to mark notification as read', e)
    }
}

async function markAllAsRead() {
    try {
        await axios.post('/admin/notifications/mark-all-read')
        notifications.value = []
    } catch (e) {
        console.error('Failed to mark all as read', e)
    }
}

function getIcon(type: string) {
    switch (type) {
        case 'low_stock': return Package
        case 'new_order': return ShoppingCart
        case 'target_reached': return Target
        default: return Bell
    }
}

function getLevelClass(level: string) {
    switch (level) {
        case 'warning': return 'bg-orange-100 text-orange-600 border-orange-200'
        case 'success': return 'bg-green-100 text-green-600 border-green-200'
        case 'info': return 'bg-blue-100 text-blue-600 border-blue-200'
        default: return 'bg-muted text-muted-foreground'
    }
}

onMounted(() => {
    fetchNotifications()
    pollInterval = setInterval(fetchNotifications, 60000) // Poll every 1 minute
})

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval)
})
</script>

<template>
    <div class="relative">
        <!-- Bell Trigger -->
        <button
            type="button"
            class="relative inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/30 bg-white/10 text-white shadow-sm hover:bg-red-800 transition-colors"
            @click="isOpen = !isOpen"
        >
            <Bell class="h-4 w-4" />
            <span 
                v-if="notifications.length > 0" 
                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white ring-2 ring-red-900 animate-pulse"
            >
                {{ notifications.length > 9 ? '9+' : notifications.length }}
            </span>
        </button>

        <!-- Notification Panel (Popover/Drawer hybrid) -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-1 opacity-0"
        >
            <div 
                v-if="isOpen" 
                class="fixed inset-0 z-50 lg:absolute lg:inset-auto lg:right-0 lg:top-10 lg:w-96"
                v-outside-click="() => isOpen = false"
            >
                <!-- Mobile Backdrop -->
                <div class="fixed inset-0 bg-black/50 lg:hidden" @click="isOpen = false" />

                <Card class="relative mx-auto mt-20 w-[90%] max-w-sm overflow-hidden lg:mx-0 lg:mt-0 lg:max-w-none shadow-2xl">
                    <div class="flex items-center justify-between border-b px-4 py-3 bg-muted/40">
                        <div class="flex items-center gap-2">
                            <Bell class="h-4 w-4 text-primary" />
                            <h3 class="text-sm font-bold">Notifikasi</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <button 
                                v-if="notifications.length > 0"
                                @click="markAllAsRead"
                                class="text-[10px] uppercase font-bold text-primary hover:underline"
                            >
                                Tandai Semua Dibaca
                            </button>
                            <button @click="isOpen = false" class="lg:hidden text-muted-foreground">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <CardContent class="max-h-[70vh] overflow-y-auto p-0 scrollbar-hide">
                        <div v-if="notifications.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="mb-3 rounded-full bg-muted p-3">
                                <Bell class="h-6 w-6 text-muted-foreground/50" />
                            </div>
                            <p class="text-sm text-muted-foreground">Tidak ada notifikasi baru.</p>
                        </div>

                        <div v-else class="divide-y">
                            <div 
                                v-for="notif in notifications" 
                                :key="notif.id" 
                                class="group relative flex gap-3 p-4 transition-colors hover:bg-muted/30"
                            >
                                <div 
                                    :class="[
                                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border shadow-sm',
                                        getLevelClass(notif.data.level)
                                    ]"
                                >
                                    <component :is="getIcon(notif.data.type)" class="h-5 w-5" />
                                </div>
                                
                                <div class="flex-1 space-y-1 pr-8">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">
                                            {{ new Date(notif.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        </p>
                                    </div>
                                    <h4 class="text-sm font-bold leading-tight">{{ notif.data.title }}</h4>
                                    <p class="text-xs text-muted-foreground leading-normal">{{ notif.data.message }}</p>
                                    
                                    <div v-if="notif.data.link" class="pt-2">
                                        <a :href="notif.data.link" class="inline-flex items-center gap-1 text-[10px] font-bold text-primary hover:underline uppercase">
                                            Lihat Detail <ExternalLink class="h-3 w-3" />
                                        </a>
                                    </div>
                                </div>

                                <button 
                                    @click.stop="markAsRead(notif.id)"
                                    class="absolute top-4 right-4 h-6 w-6 rounded-full border bg-background flex items-center justify-center text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-green-600 hover:border-green-600"
                                    title="Tandai dibaca"
                                >
                                    <Check class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </CardContent>
                    
                    <div class="border-t p-2 text-center bg-muted/20">
                        <Link href="/admin/notifications" class="text-[10px] font-bold text-muted-foreground hover:text-primary transition-colors uppercase tracking-widest" @click="isOpen = false">
                             Lihat Semua Riwayat
                        </Link>
                    </div>
                </Card>
            </div>
        </transition>
    </div>
</template>

<style scoped>
/* Simplified scrollbar */
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>
