<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Store } from 'lucide-vue-next'

interface StoreItem {
    id: number
    name: string
    slug: string
}

defineProps<{
    stores: StoreItem[]
}>()

function selectStore(storeId: number) {
    router.get('/admin/cashier', { store: storeId })
}
</script>

<template>
    <AdminLayout title="Pilih Toko">
        <div class="flex min-h-[50vh] items-center justify-center">
            <Card class="w-full max-w-md">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Store class="h-5 w-5" />
                        Pilih Toko
                    </CardTitle>
                    <CardDescription>
                        Pilih toko tempat Anda akan melayani transaksi kasir.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-2">
                        <Button
                            v-for="s in stores"
                            :key="s.id"
                            variant="outline"
                            class="h-12 justify-start text-left"
                            @click="selectStore(s.id)"
                        >
                            <Store class="mr-3 h-4 w-4 shrink-0" />
                            {{ s.name }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
