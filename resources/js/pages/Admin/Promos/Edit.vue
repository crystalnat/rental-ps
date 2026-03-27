<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PromoForm from './Form.vue'

interface PromoData {
    id: number
    code: string
    type: 'percentage' | 'nominal'
    value: number | string
    min_purchase: number | string
    max_discount: number | string | null
    valid_from: string | null
    valid_until: string | null
    quota: number | string | null
    used_count: number
    is_active: boolean
}

defineProps<{
    promo: PromoData
}>()
</script>

<template>
    <Head :title="`Ubah Promo: ${promo.code}`" />
    <PromoForm :promo="promo" :is-edit="true" />
</template>
