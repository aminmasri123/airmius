<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    value: String,
})

const { t, te } = useI18n()

const label = computed(() => {
    if (!props.value) {
        return ''
    }

    return te(props.value) ? t(props.value) : props.value
})
</script>

<template>
    <label class="block font-medium text-sm text-primary">
        <span v-if="value">{{ label }}</span>
        <span v-else><slot /></span>
    </label>
</template>
