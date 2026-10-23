<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import Modal from './Modal.vue'

const props = defineProps({ document: { type: String, default: null } })
defineEmits(['close'])
const { locale } = useI18n()
const content = ref(null)
const error = ref(false)
const loading = ref(false)
let requestId = 0
const load = async () => {
    const id = ++requestId
    content.value = null
    error.value = false
    if (!props.document) return
    loading.value = true
    try {
        const response = await axios.get(route(props.document === 'privacy' ? 'policy.show' : 'terms.show'), {
            params: { format: 'json', locale: locale.value }, timeout: 15000,
        })
        if (!Array.isArray(response.data.sections) || !response.data.sections.length) throw new Error('Invalid document')
        if (id === requestId) content.value = response.data
    } catch {
        if (id === requestId) error.value = true
    } finally {
        if (id === requestId) loading.value = false
    }
}
watch(() => [props.document, locale.value], load)
</script>

<template>
    <Modal :show="Boolean(document)" :aria-label="document === 'privacy' ? $t('Datenschutzerklärung') : $t('AGB')" @close="$emit('close')">
        <div class="p-6 text-primary">
            <h2 class="text-xl font-semibold">{{ content?.title || (document === 'privacy' ? $t('Datenschutzerklärung') : $t('AGB')) }}</h2>
            <div class="mt-4 max-h-[65vh] overflow-y-auto" :aria-busy="loading">
                <p v-if="loading" role="status">{{ $t('Wird geladen...') }}</p>
                <div v-else-if="error" role="alert">
                    <p>{{ $t('Die Aktion konnte nicht ausgeführt werden. Bitte versuche es erneut.') }}</p>
                    <button type="button" class="mt-3 underline" @click="load">{{ $t('Erneut versuchen') }}</button>
                </div>
                <template v-else-if="content">
                    <p v-if="content.note" class="text-sm text-secondary">{{ content.note }}</p>
                    <section v-for="(section, index) in content.sections" :key="index" class="mt-5">
                        <h3 class="font-semibold">{{ section.title }}</h3>
                        <p v-for="(paragraph, i) in section.body" :key="i" class="mt-2 whitespace-pre-line leading-relaxed">{{ paragraph }}</p>
                    </section>
                </template>
            </div>
            <button type="button" class="mt-5 rounded-lg border border-border px-4 py-2" @click="$emit('close')">{{ $t('Schließen') }}</button>
        </div>
    </Modal>
</template>
