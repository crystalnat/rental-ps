<script setup lang="ts">
import { computed, provide } from 'vue'
import { DialogRoot, type DialogRootProps } from 'radix-vue'

const props = defineProps<DialogRootProps>()
const emits = defineEmits<{ 'update:open': [v: boolean] }>()

provide('dialog:open', computed(() => !!props.open))
provide('dialog:close', () => emits('update:open', false))
</script>

<template>
    <DialogRoot v-bind="props" @update:open="emits('update:open', $event)">
        <slot />
    </DialogRoot>
</template>
