<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import copy from '@/i18n/learningContentLocalization.json'
defineProps({ modelValue: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue', 'busy'])
const { locale } = useI18n()
const labels = computed(() => copy[locale.value] || copy.en)
const busy = ref(false)
const error = ref('')
const upload = async event => {
    const file = event.target.files?.[0]
    if (!file || busy.value) return
    error.value = ''
    if (file.size > 5 * 1024 * 1024) { error.value = labels.value.certificate_logo_hint; event.target.value = ''; return }
    busy.value = true
    emit('busy', true)
    try {
        const data = new FormData()
        data.append('file', file)
        const result = await window.axios.post(route('auth.learning.studio.certificate-logo.store'), data)
        if (!result.data?.url) throw new Error()
        emit('update:modelValue', result.data.url)
    } catch (failure) {
        error.value = failure?.response?.data?.errors?.file?.[0] || labels.value.certificate_logo_failed
    } finally {
        busy.value = false
        emit('busy', false)
        event.target.value = ''
    }
}
</script>
<template>
    <div class="grid gap-2">
        <label class="text-sm text-secondary">{{ labels.certificate_logo_url }}
            <input :value="modelValue" type="url" :disabled="busy" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" @input="$emit('update:modelValue', $event.target.value)">
        </label>
        <label class="cursor-pointer rounded-lg border border-border px-4 py-2 text-center text-sm text-primary" :class="{ 'opacity-50': busy }">
            <i class="las la-upload" aria-hidden="true"></i> {{ labels.certificate_logo_upload }}
            <input type="file" accept="image/png,image/jpeg,image/webp" :disabled="busy" class="sr-only" @change="upload">
        </label>
        <p class="text-xs text-secondary">{{ labels.certificate_logo_hint }}</p>
        <progress v-if="busy" class="w-full" :aria-label="labels.certificate_logo_upload"></progress>
        <p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p>
    </div>
</template>
