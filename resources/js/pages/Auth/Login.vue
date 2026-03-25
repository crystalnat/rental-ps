<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Loader2 } from 'lucide-vue-next'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <AuthLayout title="Masuk ke POS" description="Masukkan kredensial akun Anda">
        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="nama@toko.com"
                    autocomplete="email"
                    :disabled="form.processing"
                    required
                />
                <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-2">
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    :disabled="form.processing"
                    required
                />
                <p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p>
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="remember"
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-input accent-primary"
                />
                <Label for="remember" class="font-normal">Ingat saya</Label>
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                <Loader2 v-if="form.processing" class="animate-spin" />
                Masuk
            </Button>
        </form>
    </AuthLayout>
</template>
