<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Modal from '@/Components/Modal.vue'

const props = defineProps({ clubId: { type: Number, required: true }, clubName: { type: String, required: true } })
const { locale } = useI18n()
const status = ref(null)
const open = ref(false)
const busy = ref(false)
const error = ref('')
const confirmation = ref('')
const messages = {
    de: {
        title: 'Verein löschen', pending: 'Löschung vorgemerkt', manage: 'Löschantrag verwalten',
        warning: 'Der Verein wird frühestens 30 Tage nach deiner Bestätigung endgültig gelöscht. Bis zur Ausführung kannst du den Antrag zurücknehmen. Der Verein bleibt während der Frist nutzbar.',
        data: 'Teams, Termine, To-dos, Mitgliedschaften, Inhalte und vereinseigene Dateien werden gelöscht. Persönliche Konten, andere Vereine und aufbewahrte Abrechnungs- und Supportunterlagen bleiben erhalten. Aufbewahrungs- oder Vertragssperren können die Löschung verhindern.',
        notice: 'Besitzer und eingetragener Vorstand werden per E-Mail und bei vorhandenem Konto in der App informiert.',
        date: 'Frühester Löschtermin', phrase: 'Bitte folgenden Text eingeben:', label: 'Bestätigung',
        request: 'Löschung in 30 Tagen vormerken', cancel: 'Löschung zurücknehmen', close: 'Schließen',
        failed: 'Die Aktion konnte nicht abgeschlossen werden.', retry: 'Erneut laden',
        blocked: 'Löschung angehalten. Bitte kontaktiere den Support oder nimm den Antrag zurück.',
    },
    en: {
        title: 'Delete club', pending: 'Deletion scheduled', manage: 'Manage deletion request',
        warning: 'The club will be permanently deleted no earlier than 30 days after your confirmation. You can cancel the request until deletion takes place. The club remains available during this period.',
        data: 'Teams, events, tasks, memberships, content and club-owned files will be deleted. Personal accounts, other clubs and retained billing and support records remain. Retention or subscription restrictions may prevent deletion.',
        notice: 'Owners and registered board members are notified by email and, when they have an account, in the app.',
        date: 'Earliest deletion date', phrase: 'Please enter the following phrase:', label: 'Confirmation',
        request: 'Schedule deletion in 30 days', cancel: 'Cancel deletion', close: 'Close',
        failed: 'The action could not be completed.', retry: 'Reload',
        blocked: 'Deletion paused. Please contact support or cancel the request.',
    },
}
const text = computed(() => messages[locale.value.split('-')[0]] || messages.en)
const date = computed(() => status.value?.scheduled_at
    ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'long', timeStyle: 'short' }).format(new Date(status.value.scheduled_at)) : '')
let generation = 0
async function load() {
    const current = ++generation
    error.value = ''
    try {
        const response = await window.axios.get(route('auth.clubs.deletion.show', props.clubId))
        if (current === generation) status.value = response.data.data
    } catch (failure) {
        if (current === generation) error.value = failure.response?.data?.message || text.value.failed
    }
}
watch(() => props.clubId, () => { status.value = null; open.value = false; confirmation.value = ''; load() }, { immediate: true })
async function submit(cancel) {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
        const response = cancel
            ? await window.axios.delete(route('auth.clubs.deletion.cancel', props.clubId))
            : await window.axios.delete(route('api.v1.clubs.destroy', props.clubId), { data: { confirmation: confirmation.value.trim() } })
        status.value = response.data.data
        confirmation.value = ''
        if (cancel) open.value = false
    } catch (failure) {
        error.value = failure.response?.data?.message || text.value.failed
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-y border-border py-3 text-sm">
        <p v-if="date" class="font-semibold text-error">{{ text.pending }}: {{ date }}</p>
        <button type="button" class="inline-flex items-center gap-2 font-semibold text-error" @click="open = true; confirmation = ''">
            <i :class="date ? 'las la-undo' : 'las la-trash'" aria-hidden="true"></i>
            {{ date ? text.manage : text.title }}
        </button>
        <Modal :show="open" max-width="lg" :closeable="!busy" @close="open = false">
            <form class="space-y-4 p-5 sm:p-6" @submit.prevent="submit(Boolean(date))">
                <h2 class="text-lg font-bold text-primary">{{ text.title }}: {{ clubName }}</h2>
                <p class="text-sm text-secondary">{{ text.warning }}</p>
                <p class="text-sm text-secondary">{{ text.data }}</p>
                <p class="text-sm text-secondary">{{ text.notice }}</p>
                <p v-if="status?.blocked" class="text-sm text-error" role="alert">{{ text.blocked }}</p>
                <p v-if="status?.blocker" class="text-sm text-error">{{ status.blocker }}</p>
                <p v-if="error" class="text-sm text-error" role="alert">{{ error }}</p>
                <button v-if="!status" type="button" class="text-link" @click="load">{{ text.retry }}</button>
                <div v-if="date" class="border-l-4 border-error pl-3">
                    <p class="font-bold text-primary">{{ text.pending }}</p>
                    <p class="text-sm text-secondary">{{ text.date }}: {{ date }}</p>
                </div>
                <label v-else-if="status && !status.blocker" class="block space-y-2 text-sm text-primary">
                    <span class="block">{{ text.phrase }} <strong>{{ status.confirmation }}</strong></span>
                    <input v-model="confirmation" :disabled="busy" :aria-label="text.label" autocomplete="off" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-primary">
                </label>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="open = false">{{ text.close }}</button>
                    <button v-if="date || (status && !status.blocker)" type="submit" :disabled="busy || (!date && confirmation.trim() !== status.confirmation)" class="inline-flex items-center justify-center gap-2 rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                        <i :class="date ? 'las la-undo' : 'las la-trash'" aria-hidden="true"></i>
                        {{ date ? text.cancel : text.request }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
