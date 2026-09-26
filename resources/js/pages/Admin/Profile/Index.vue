<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { KeyRound, Loader2 } from 'lucide-vue-next'

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function submit() {
    form.put(route('admin.profile.password'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <AdminLayout title="Ubah Password">
        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <KeyRound class="h-4 w-4" />
                            </div>
                            <div>
                                <CardTitle>Ubah Password</CardTitle>
                                <CardDescription>Perbarui password akun Anda</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="current_password">Password Saat Ini <span class="text-destructive">*</span></Label>
                            <Input id="current_password" v-model="form.current_password" type="password" autocomplete="current-password" :disabled="form.processing" />
                            <p v-if="form.errors.current_password" class="text-xs text-destructive">{{ form.errors.current_password }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="password">Password Baru <span class="text-destructive">*</span></Label>
                            <Input id="password" v-model="form.password" type="password" autocomplete="new-password" placeholder="Minimal 8 karakter" :disabled="form.processing" />
                            <p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="password_confirmation">Konfirmasi Password Baru <span class="text-destructive">*</span></Label>
                            <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" :disabled="form.processing" />
                        </div>
                    </CardContent>
                </Card>
                <div class="flex justify-end rounded-xl border bg-card p-4">
                    <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="animate-spin" />
                        Simpan Password
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
