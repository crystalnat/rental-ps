<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PromoForm from './Form.vue'

// No specialized props needed for Create beyond defaults
</script>

<template>
    <Head title="Tambah Promo Baru" />
    <PromoForm :promo="null" :is-edit="false" />
</template>
