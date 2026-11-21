<script setup>
import { computed, onMounted, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })
const props = defineProps({ club: { type: Object, required: true } })
const { locale } = useI18n()
const de = computed(() => locale.value.startsWith('de'))
const copy = (german, english) => de.value ? german : english
const status = ref(null)
const busy = ref(false)
const error = ref('')
const identity = ref('')
const code = ref('')
const confirmation = ref('')
const codeSent = ref(false)
const archiveConfirmed = ref(false)
const pending = computed(() => Boolean(status.value?.requested_at || status.value?.scheduled_at))
const review = computed(() => ({
    not_required: copy('Keine Finanzprüfung erforderlich', 'No financial review required'),
    export_required: copy('Aktueller Finanzexport erforderlich', 'Current financial export required'),
    archive_required: copy('Archivbestätigung ausstehend', 'Waiting for archive confirmation'),
    passed: copy('Finanzprüfung bestanden', 'Financial review passed'),
})[status.value?.review_status])
const scheduled = computed(() => status.value?.scheduled_at
    ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'long', timeStyle: 'short' }).format(new Date(status.value.scheduled_at))
    : '')

async function load() {
    error.value = ''
    try {
        const response = await window.axios.get(route('auth.clubs.deletion.show', props.club.id))
        status.value = response.data.data
    } catch (failure) {
        error.value = failure.response?.data?.message || copy('Status konnte nicht geladen werden.', 'Could not load deletion status.')
    }
}

function showError(failure) {
    const errors = failure.response?.data?.errors || {}
    error.value = Object.values(errors).flat().join(' ') || failure.response?.data?.message
        || copy('Die Aktion konnte nicht abgeschlossen werden.', 'The action could not be completed.')
}

async function sendCode() {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
        await window.axios.post(route('auth.clubs.deletion.code', props.club.id), { identity: identity.value })
        identity.value = ''
        codeSent.value = true
    } catch (failure) {
        showError(failure)
    } finally {
        busy.value = false
    }
}

async function requestDeletion() {
    if (busy.value || confirmation.value.trim() !== status.value?.confirmation || !/^\d{6}$/.test(code.value)) return
    busy.value = true
    error.value = ''
    try {
        const response = await window.axios.post(route('auth.clubs.deletion.request', props.club.id), {
            confirmation: confirmation.value.trim(), code: code.value,
        })
        status.value = response.data.data
        code.value = ''
        confirmation.value = ''
        codeSent.value = false
    } catch (failure) {
        showError(failure)
    } finally {
        busy.value = false
    }
}

async function cancelDeletion() {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
        const response = await window.axios.delete(route('auth.clubs.deletion.cancel', props.club.id))
        status.value = response.data.data
    } catch (failure) {
        showError(failure)
    } finally {
        busy.value = false
    }
}

async function confirmArchive() {
    if (busy.value || !archiveConfirmed.value) return
    busy.value = true
    error.value = ''
    try {
        const response = await window.axios.post(route('auth.clubs.deletion.archive-confirmation', props.club.id), { archive_confirmed: true })
        status.value = response.data.data
    } catch (failure) {
        showError(failure)
    } finally {
        busy.value = false
    }
}

onMounted(load)
</script>

<template>
    <Head :title="copy('Verein aus Airmius löschen', 'Delete club from Airmius')" />
    <div class="mx-auto max-w-3xl space-y-8 px-4 py-6 sm:px-6">
        <div class="flex items-start gap-4">
            <Link :href="route('auth.clubs.show', club.id)" :aria-label="copy('Zurück zum Verein', 'Back to club')" class="mt-1 text-xl text-secondary hover:text-primary"><i class="las la-arrow-left" aria-hidden="true"></i></Link>
            <div class="min-w-0">
                <p class="text-sm text-secondary">{{ copy('Vereinseinstellungen', 'Club settings') }}</p>
                <h1 class="break-words text-2xl font-bold text-primary">{{ copy('Verein aus Airmius löschen', 'Delete club from Airmius') }}</h1>
                <p class="mt-1 break-words text-secondary">{{ club.name }}</p>
            </div>
        </div>

        <section v-if="status" class="space-y-2 rounded border border-border p-4 text-sm text-secondary" aria-live="polite">
            <h2 class="text-lg font-semibold text-primary">{{ pending ? copy('Löschung beantragt', 'Deletion requested') : copy('Prüfstatus', 'Review status') }}</h2>
            <p v-if="review">{{ review }}</p>
            <p v-if="scheduled">{{ copy('Frühester Löschtermin', 'Earliest deletion date') }} · {{ scheduled }}</p>
            <p v-else-if="pending">{{ copy('Löschtermin steht noch aus.', 'Deletion date pending.') }}</p>
            <p v-if="status.blocker" role="status">{{ status.blocker }}</p>
            <div class="flex flex-wrap items-center gap-4 pt-2">
                <button v-if="pending" type="button" :disabled="busy" class="rounded border border-border px-4 py-2 font-semibold text-primary disabled:opacity-50" @click="cancelDeletion">{{ copy('Antrag zurücknehmen', 'Cancel request') }}</button>
                <button type="button" :disabled="busy" class="font-semibold text-link hover:underline disabled:opacity-50" @click="load">{{ copy('Status aktualisieren', 'Refresh status') }}</button>
            </div>
        </section>

        <section class="space-y-3 text-sm leading-6 text-secondary">
            <h2 class="text-lg font-semibold text-primary">{{ copy('Was passiert?', 'What happens?') }}</h2>
            <p>{{ copy('Der Antrag startet eine 30-tägige Bedenkzeit. Bis zur Ausführung bleibt der Verein nutzbar und du kannst den Antrag zurücknehmen. Der Besitzer und eingetragene Vorstand werden benachrichtigt.', 'The request starts a 30-day grace period. The club remains available and the request can be cancelled until execution. The owner and registered board are notified.') }}</p>
            <p>{{ copy('Teams, Termine, Aufgaben, Mitgliedschaften, Inhalte und vereinseigene Dateien werden nach Fristablauf entfernt, sofern keine Sperre besteht. Persönliche Konten und andere Vereine bleiben bestehen.', 'Teams, events, tasks, memberships, content and club-owned files are removed after the grace period unless a restriction applies. Personal accounts and other clubs remain.') }}</p>
            <Link :href="route('legal.club-deletion')" class="font-semibold text-link hover:underline">{{ copy('Ausführliche Informationen zur Vereinslöschung', 'Detailed club deletion information') }}</Link>
        </section>

        <div v-if="!status && !error" class="text-sm text-secondary" role="status">{{ copy('Lade Status …', 'Loading status …') }}</div>
        <div v-if="error" class="rounded border border-error/50 bg-error/10 p-3 text-sm text-error" role="alert">{{ error }}</div>
        <button v-if="!status" type="button" class="text-sm font-semibold text-link" @click="load">{{ copy('Erneut laden', 'Retry') }}</button>

        <section v-if="status" class="border-t border-border pt-6">
            <div v-if="status.has_financial_records" class="mb-6 space-y-3 border-b border-border pb-6 text-sm text-secondary">
                <h2 class="text-lg font-semibold text-primary">{{ copy('Finanzunterlagen sichern', 'Preserve financial records') }}</h2>
                <p>{{ copy('1. Lade das Datenpaket herunter und prüfe Inhalt und Belege. 2. Überführe die erforderlichen Originalunterlagen in ein eigenes, rechtlich geeignetes Archiv. Das ZIP allein reicht dafür nicht.', '1. Download the data package and check its contents and receipts. 2. Preserve the required original documents in your own legally suitable archive. The ZIP alone is insufficient.') }}</p>
                <a :href="route('auth.clubs.deletion.financial-export', club.id)" class="inline-flex items-center gap-2 font-semibold text-link hover:underline" @click="archiveConfirmed = false"><i class="las la-file-download" aria-hidden="true"></i>{{ copy('Finanzdaten und Belege herunterladen', 'Download financial data and receipts') }}</a>
                <p class="text-xs">{{ copy('Aktualisiere nach dem Download den Prüfstatus. Fehlen verknüpfte Belege, ergänze sie vor dem erneuten Export.', 'Refresh the review status after downloading. Restore missing linked receipts before exporting again.') }}</p>
                <p v-if="status.financial_exported_at" role="status">{{ copy('Export erstellt:', 'Export created:') }} {{ new Date(status.financial_exported_at).toLocaleString(locale) }}</p>
                <form v-if="status.financial_exported_at && !status.archive_confirmed_at" class="space-y-3" @submit.prevent="confirmArchive">
                    <label class="flex items-start gap-3 text-primary"><input v-model="archiveConfirmed" type="checkbox" required class="mt-1" /><span>{{ copy('Ich habe die benötigten Originalunterlagen außerhalb von Airmius vollständig und gesetzeskonform archiviert. Mir ist klar, dass das Airmius-ZIP allein kein revisionssicheres Archiv ist.', 'I have completely preserved the required original documents outside Airmius in a legally compliant archive. I understand that the Airmius ZIP alone is not a compliant long-term archive.') }}</span></label>
                    <button type="submit" :disabled="busy || !archiveConfirmed" class="rounded border border-border px-4 py-2 font-semibold text-primary disabled:opacity-50">{{ copy('Archivierung bestätigen', 'Confirm archive') }}</button>
                </form>
                <p v-if="status.archive_confirmed_at" role="status">{{ copy('Eigene Archivierung bestätigt.', 'External archive confirmed.') }}</p>
            </div>
            <template v-if="!pending && status.can_request">
                <h2 class="text-lg font-semibold text-primary">{{ copy('Löschung beantragen', 'Request deletion') }}</h2>
                <div class="mt-5 grid gap-5">
                    <form class="grid gap-3" @submit.prevent="sendCode">
                        <label class="text-sm font-semibold text-primary" for="club-deletion-identity">{{ status.requires_password ? copy('Passwort bestätigen', 'Confirm password') : copy('Konto-E-Mail bestätigen', 'Confirm account email') }}</label>
                        <input id="club-deletion-identity" v-model="identity" :type="status.requires_password ? 'password' : 'email'" :autocomplete="status.requires_password ? 'current-password' : 'email'" required class="w-full rounded border border-border bg-inputBg px-3 py-2 text-primary" />
                        <button type="submit" :disabled="busy || !identity" class="justify-self-start rounded border border-border px-4 py-2 font-semibold text-primary disabled:opacity-50">{{ codeSent ? copy('Neuen Code senden', 'Send a new code') : copy('E-Mail-Code anfordern', 'Request email code') }}</button>
                    </form>
                    <form v-if="codeSent" class="grid gap-3 border-t border-border pt-5" @submit.prevent="requestDeletion">
                        <label class="text-sm font-semibold text-primary" for="club-deletion-code">{{ copy('Sechsstelliger E-Mail-Code', 'Six-digit email code') }}</label>
                        <input id="club-deletion-code" v-model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="w-full rounded border border-border bg-inputBg px-3 py-2 text-primary" />
                        <label class="text-sm font-semibold text-primary" for="club-deletion-phrase">{{ copy('Bestätigungstext exakt eingeben:', 'Enter the exact confirmation phrase:') }} <strong>{{ status.confirmation }}</strong></label>
                        <input id="club-deletion-phrase" v-model="confirmation" autocomplete="off" required class="w-full rounded border border-border bg-inputBg px-3 py-2 text-primary" />
                        <button type="submit" :disabled="busy || confirmation.trim() !== status.confirmation || !/^\d{6}$/.test(code)" class="justify-self-start rounded bg-error px-4 py-2 font-semibold text-white disabled:opacity-50">{{ copy('Löschung beantragen', 'Request deletion') }}</button>
                    </form>
                </div>
            </template>
        </section>
    </div>
</template>
