<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'
import copy from '../../../data/learning-deletion-copy.json'
const props = defineProps({ target: Object, busy: Boolean, error: String })
defineEmits(['confirm', 'close'])
const { locale } = useI18n()
const labels = computed(() => copy[locale.value] || copy.en)
const confirmation = ref('')
watch(() => props.target, () => { confirmation.value = '' })
</script>
<template>
    <Modal :show="Boolean(target)" :closeable="!busy" @close="$emit('close')">
        <div v-if="target" class="space-y-4 p-5">
            <h2 class="text-xl font-bold text-error">{{ labels['remove_' + target.kind] }}</h2>
            <p class="break-words font-semibold">{{ target.title }}</p>
            <p class="text-sm leading-relaxed">{{ labels['warn_' + target.kind] }}</p>
            <label class="block">{{ labels.confirm_hint }}
                <input v-model="confirmation" :disabled="busy" dir="ltr" autocomplete="off" class="mt-2 w-full rounded-lg border-border bg-inputBg" />
            </label>
            <p v-if="error" role="alert" class="text-error">{{ error }}</p>
            <div class="flex flex-wrap justify-end gap-3">
                <button type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2" @click="$emit('close')">{{ labels.cancel }}</button>
                <button type="button" :disabled="busy || confirmation !== 'DELETE'" class="rounded-lg bg-error px-4 py-2 text-white disabled:opacity-40" @click="$emit('confirm', target)">{{ labels.delete }}</button>
            </div>
        </div>
    </Modal>
</template>
