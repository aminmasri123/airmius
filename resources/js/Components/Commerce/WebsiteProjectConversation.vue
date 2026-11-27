<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const props = defineProps({ projectId: { type: Number, required: true } })
const project = ref(null), messages = ref([]), busy = ref(false), error = ref(''), body = ref(''), page = ref(1), lastPage = ref(1)
let retryKey = null, retryBody = null
const statuses = { new: 'Anfrage eingegangen', contacted: 'In Klärung', quoted: 'Angebot erstellt', in_progress: 'In Umsetzung', done: 'Abgeschlossen', cancelled: 'Storniert' }
const date = value => value ? new Date(value).toLocaleString() : ''
const safeLink = value => { try { return ['https:', 'http:'].includes(new URL(value).protocol) } catch { return false } }
async function load(more = false) {
    busy.value = true; error.value = ''
    try {
        const next = more ? page.value + 1 : 1
        const { data } = await axios.get(route('auth.commerce.website-project.show', props.projectId), { params: { page: next } })
        project.value = data.data
        messages.value = more ? [...messages.value, ...data.messages.data] : data.messages.data
        page.value = next; lastPage.value = data.messages.last_page
    } catch { error.value = 'Das Projekt konnte nicht geladen werden. Bitte aktualisieren.' }
    finally { busy.value = false }
}
async function send() {
    const text = body.value.trim()
    if (busy.value || !text) return
    if (text !== retryBody) { retryKey = crypto.randomUUID(); retryBody = text }
    busy.value = true; error.value = ''
    try {
        await axios.post(route('auth.commerce.website-project.messages', props.projectId), { body: text, request_key: retryKey })
        body.value = ''; retryKey = null; retryBody = null
        await load()
    } catch { error.value = 'Nachricht nicht bestätigt. Bitte erneut senden; Wiederholungen werden nicht doppelt gespeichert.' }
    finally { busy.value = false }
}
onMounted(() => load())
</script>

<template>
    <section class="space-y-6 rounded-xl border border-border bg-bg p-5" :aria-busy="busy">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h3 class="text-lg font-bold">{{ project?.project_title || `Website-Projekt #${projectId}` }}</h3>
            <button type="button" class="rounded-lg border border-border px-4 py-2" :disabled="busy" @click="load()">Aktualisieren</button>
        </div>
        <p v-if="error" role="alert" class="text-error">{{ error }}</p>
        <template v-if="project">
            <p class="font-semibold text-air-blue">{{ statuses[project.status] || project.status }}</p>
            <p class="text-sm text-secondary">Anfrage → Rückfragen → Angebot → Umsetzung → Abschluss</p>
            <p class="text-sm text-secondary">Erstellt: {{ date(project.created_at) }} · Unverbindliche Anfrage, keine automatische kostenpflichtige Aktivierung.</p>
            <dl class="grid gap-5 sm:grid-cols-2">
                <div v-for="(label, key) in { domain: 'Gewünschte Domain', goals: 'Ziele und Funktionen', target_audience: 'Zielgruppe', notes: 'Weitere Hinweise' }" :key="key">
                    <dt class="font-semibold">{{ label }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-secondary">{{ project[key] || 'Noch nicht angegeben' }}</dd>
                </div>
            </dl>
            <div v-if="project.inspiration_urls?.length" class="space-y-3">
                <h4 class="font-semibold">Inspirationswebsites</h4>
                <template v-for="url in project.inspiration_urls" :key="url"><a v-if="safeLink(url)" :href="url" target="_blank" rel="noopener noreferrer" class="block break-all text-air-blue underline">{{ url }}</a></template>
            </div>
            <div class="space-y-4 border-t border-border pt-6">
                <h4 class="font-semibold">Fragen und Kommentare</h4>
                <p class="text-sm text-secondary">Sichtbar für die berechtigten Projektbeteiligten und die Administration. Neue Antworten erscheinen nach dem Aktualisieren.</p>
                <form class="space-y-4" @submit.prevent="send">
                    <label class="block">Deine Nachricht<textarea v-model="body" required maxlength="4000" rows="4" :disabled="busy" class="mt-2 block w-full rounded-lg border-border bg-inputBg text-primary"></textarea></label>
                    <button :disabled="busy || !body.trim()" class="rounded-lg bg-buttonPrimary px-5 py-3 font-semibold text-buttonTextPrimary disabled:opacity-50">Nachricht senden</button>
                </form>
                <p v-if="!messages.length" class="text-secondary">Noch keine Nachrichten. Stelle hier deine erste Frage.</p>
                <article v-for="message in messages" :key="message.id" class="space-y-2 rounded-lg border border-border p-4">
                    <p class="text-sm font-semibold">{{ message.author || 'Projektteam' }} · {{ date(message.created_at) }}</p>
                    <p class="whitespace-pre-wrap break-words">{{ message.kind === 'status' ? `Status geändert: ${statuses[message.status] || message.status}` : message.body }}</p>
                </article>
                <button v-if="page < lastPage" type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2" @click="load(true)">Ältere Einträge laden</button>
            </div>
        </template>
    </section>
</template>
