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

async function requestReview() {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
        const response = await window.axios.post(route('auth.clubs.deletion.review', props.club.id))
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
    <Head :title="copy('Verein löschen', 'Delete club')" />
    <div class="mx-auto max-w-3xl space-y-8 px-4 py-6 sm:px-6">
        <div class="flex items-start gap-4">
            <Link :href="route('auth.clubs.show', club.id)" :aria-label="copy('Zurück zum Verein', 'Back to club')" class="mt-1 text-xl text-secondary hover:text-primary"><i class="las la-arrow-left" aria-hidden="true"></i></Link>
            <div class="min-w-0">
                <p class="text-sm text-secondary">{{ copy('Vereinseinstellungen', 'Club settings') }}</p>
                <h1 class="break-words text-2xl font-bold text-primary">{{ copy('Verein löschen', 'Delete club') }}</h1>
                <p class="mt-1 break-words text-secondary">{{ club.name }}</p>
            </div>
        </div>

        <section class="space-y-3 text-sm leading-6 text-secondary">
            <h2 class="text-lg font-semibold text-primary">{{ copy('Was passiert?', 'What happens?') }}</h2>
            <p>{{ copy('Der Antrag startet eine 30-tägige Bedenkzeit. Bis zur Ausführung bleibt der Verein nutzbar und du kannst den Antrag zurücknehmen. Der Besitzer und eingetragene Vorstand werden benachrichtigt.', 'The request starts a 30-day grace period. The club remains available and the request can be cancelled until execution. The owner and registered board are notified.') }}</p>
            <p>{{ copy('Teams, Termine, Aufgaben, Mitgliedschaften, Inhalte und vereinseigene Dateien werden nach Fristablauf entfernt, sofern keine Sperre besteht. Persönliche Konten und andere Vereine bleiben bestehen.', 'Teams, events, tasks, memberships, content and club-owned files are removed after the grace period unless a restriction applies. Personal accounts and other clubs remain.') }}</p>
            <p>{{ copy('Bei Finanzdaten archivierst du die aufbewahrungspflichtigen Originalunterlagen selbst. Nach Export und deiner Bestätigung kann die 30-Tage-Frist starten; die Ausführung bleibt bis zur Supportprüfung gesperrt. Ein laufendes Zahlungsanbieter-Abo muss zuerst beendet werden.', 'For financial records, you archive the required original documents yourself. After export and your confirmation the 30-day period can start; execution remains blocked until support review. An active payment-provider subscription must be ended first.') }}</p>
            <a :href="route('auth.clubs.deletion.financial-export', club.id)" class="inline-flex items-center gap-2 font-semibold text-link hover:underline"><i class="las la-file-download" aria-hidden="true"></i>{{ copy('Finanzdaten und verknüpfte Belege herunterladen', 'Download financial data and linked receipts') }}</a>
            <button type="button" :disabled="busy" class="ml-4 font-semibold text-link hover:underline disabled:opacity-50" @click="load">{{ copy('Status nach Download aktualisieren', 'Refresh status after download') }}</button>
            <p class="text-xs">{{ copy('Das ZIP enthält Finanz-Rohdaten und vorhandene verknüpfte Belege. Es ist kein vollständiger Vereinsexport und kein revisionssicheres Langzeitarchiv.', 'The ZIP contains raw financial data and available linked receipts. It is not a complete club export or a compliant long-term archive.') }}</p>
            <Link :href="route('legal.club-deletion')" class="font-semibold text-link hover:underline">{{ copy('Ausführliche Informationen zur Vereinslöschung', 'Detailed club deletion information') }}</Link>
        </section>

        <div v-if="!status && !error" class="text-sm text-secondary" role="status">{{ copy('Lade Status …', 'Loading status …') }}</div>
        <div v-if="error" class="rounded border border-error/50 bg-error/10 p-3 text-sm text-error" role="alert">{{ error }}</div>
        <button v-if="!status" type="button" class="text-sm font-semibold text-link" @click="load">{{ copy('Erneut laden', 'Retry') }}</button>

        <section v-if="status" class="border-t border-border pt-6">
            <div v-if="status.has_financial_records" class="mb-6 space-y-3 border-b border-border pb-6 text-sm text-secondary">
                <h2 class="text-lg font-semibold text-primary">{{ copy('Finanzunterlagen sichern', 'Preserve financial records') }}</h2>
                <p>{{ copy('1. Lade das Datenpaket herunter und prüfe Inhalt und Belege. 2. Überführe die erforderlichen Originalunterlagen in ein eigenes, rechtlich geeignetes Archiv. Das ZIP allein reicht dafür nicht.', '1. Download the data package and check its contents and receipts. 2. Preserve the required original documents in your own legally suitable archive. The ZIP alone is insufficient.') }}</p>
                <p v-if="status.financial_exported_at" role="status">{{ copy('Export erstellt:', 'Export created:') }} {{ new Date(status.financial_exported_at).toLocaleString(locale) }}</p>
                <form v-if="status.financial_exported_at && !status.archive_confirmed_at" class="space-y-3" @submit.prevent="confirmArchive">
                    <label class="flex items-start gap-3 text-primary"><input v-model="archiveConfirmed" type="checkbox" required class="mt-1" /><span>{{ copy('Ich habe die benötigten Originalunterlagen außerhalb von Airmius vollständig und gesetzeskonform archiviert. Mir ist klar, dass das Airmius-ZIP allein kein revisionssicheres Archiv ist.', 'I have completely preserved the required original documents outside Airmius in a legally compliant archive. I understand that the Airmius ZIP alone is not a compliant long-term archive.') }}</span></label>
                    <button type="submit" :disabled="busy || !archiveConfirmed" class="rounded border border-border px-4 py-2 font-semibold text-primary disabled:opacity-50">{{ copy('Archivierung bestätigen', 'Confirm archive') }}</button>
                </form>
                <p v-if="status.archive_confirmed_at" role="status">{{ copy('Eigene Archivierung bestätigt.', 'External archive confirmed.') }}</p>
                <p v-if="status.financial_approved_at" role="status">{{ copy('Support hat die Finanzdatenlöschung freigegeben.', 'Support approved financial data deletion.') }}</p>
                <p v-else-if="status.financial_rejected_at" class="text-error" role="alert">{{ copy('Support hat die Löschung nicht freigegeben. Bitte kläre den Grund mit dem Support.', 'Support did not approve deletion. Please clarify the reason with support.') }}</p>
            </div>
            <template v-if="scheduled">
                <h2 class="text-lg font-semibold text-primary">{{ copy('Löschung vorgemerkt', 'Deletion scheduled') }}</h2>
                <p class="mt-2 text-sm text-secondary">{{ copy('Frühester Löschtermin:', 'Earliest deletion date:') }} {{ scheduled }}</p>
                <p v-if="status.blocker" class="mt-3 text-sm text-error" role="alert">{{ status.blocker }}</p>
                <button type="button" :disabled="busy" class="mt-5 inline-flex items-center gap-2 rounded border border-border px-4 py-2 font-semibold text-primary disabled:opacity-50" @click="cancelDeletion"><i class="las la-undo" aria-hidden="true"></i>{{ copy('Löschung zurücknehmen', 'Cancel deletion') }}</button>
            </template>
            <template v-else-if="!status.can_request">
                <h2 class="text-lg font-semibold text-primary">{{ copy('Löschung derzeit gesperrt', 'Deletion currently blocked') }}</h2>
                <p class="mt-2 text-sm text-error" role="alert">{{ status.blocker }}</p>
                <Link :href="route('legal.reporting')" class="mt-3 inline-flex text-sm font-semibold text-link hover:underline">{{ copy('Support für die Prüfung kontaktieren', 'Contact support for review') }}</Link>
            </template>
            <template v-else>
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
                        <button type="submit" :disabled="busy || confirmation.trim() !== status.confirmation || !/^\d{6}$/.test(code)" class="justify-self-start rounded bg-error px-4 py-2 font-semibold text-white disabled:opacity-50">{{ copy('Löschung in 30 Tagen vormerken', 'Schedule deletion in 30 days') }}</button>
                    </form>
                </div>
            </template>
            <div v-if="status.blocker" class="mt-5 border-t border-border pt-5 text-sm">
                <p v-if="status.review_ticket_id" class="text-primary" role="status">{{ copy('Support-Prüfung angefordert. Dein Verein wird dadurch noch nicht gelöscht.', 'Support review requested. This does not delete your club.') }}</p>
                <button v-else type="button" :disabled="busy" class="inline-flex items-center gap-2 rounded border border-border px-4 py-2 font-semibold text-primary disabled:opacity-50" @click="requestReview"><i class="las la-headset" aria-hidden="true"></i>{{ copy('Prüfung beim Support anfordern', 'Request support review') }}</button>
            </div>
        </section>
    </div>
</template>
