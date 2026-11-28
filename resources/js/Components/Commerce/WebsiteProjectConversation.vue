<script setup>
import { useWebsiteProjectCopy } from '@/composables/useWebsiteProjectCopy'
const w = useWebsiteProjectCopy()
import { ref, onMounted } from 'vue'
import axios from 'axios'
const props = defineProps({ projectId: { type: Number, required: true } })
const project = ref(null), messages = ref([]), busy = ref(false), error = ref(''), body = ref(''), page = ref(1), lastPage = ref(1)
const confirmIdentity = ref(false)
const files = ref([]), fileInput = ref(null), editing = ref(false), form = ref({}), links = ref('')
const emit = defineEmits(['changed'])
let retryKey = null, retryBody = null
const statuses = { draft: 'Entwurf', new: 'Anfrage eingegangen', contacted: 'In Klärung', quoted: 'Angebot erstellt', in_progress: 'In Umsetzung', done: 'Abgeschlossen', cancelled: 'Storniert' }
const requestTypes = { website: 'Website', campaign: 'Kampagne', social_media: 'Social Media', print: 'Printmaterial', logo_branding: 'Logo & Branding', video: 'Video', photo: 'Foto', other: 'Sonstiges' }
const deliverableLabels = { strategy: 'Konzept & Strategie', copy: 'Texte', design: 'Design', banner: 'Banner', social_post: 'Social-Post', print_file: 'Druckdatei', logo: 'Logo', photo: 'Fotos', video: 'Video', landing_page: 'Landingpage', other: 'Sonstiges' }
const channelLabels = { website: 'Website', app: 'App', instagram: 'Instagram', facebook: 'Facebook', linkedin: 'LinkedIn', tiktok: 'TikTok', youtube: 'YouTube', email: 'E-Mail', print: 'Print', outdoor: 'Außenwerbung', other: 'Sonstiges' }
const date = value => value ? new Date(value).toLocaleString() : ''
const safeLink = value => { try { return ['https:', 'http:'].includes(new URL(value).protocol) } catch { return false } }
async function load(more = false) {
    busy.value = true; error.value = ''; confirmIdentity.value = false
    try {
        const next = more ? page.value + 1 : 1
        const { data } = await axios.get(route('auth.commerce.website-project.show', props.projectId), { params: { page: next } })
        project.value = data.data
        messages.value = more ? [...messages.value, ...data.messages.data] : data.messages.data
        page.value = next; lastPage.value = data.messages.last_page
    } catch { error.value = w('Das Projekt konnte nicht geladen werden. Bitte aktualisieren.') }
    finally { busy.value = false }
}
async function send() {
    const text = body.value.trim()
    if (busy.value || (!text && !files.value.length)) return
    if (text !== retryBody) { retryKey = crypto.randomUUID(); retryBody = text }
    busy.value = true; error.value = ''; confirmIdentity.value = false
    try {
        const payload = new FormData()
        payload.append('body', text); payload.append('request_key', retryKey)
        files.value.forEach(file => payload.append('files[]', file))
        await axios.post(route('auth.commerce.website-project.messages', props.projectId), payload)
        body.value = ''; retryKey = null; retryBody = null
        files.value = []; if (fileInput.value) fileInput.value.value = ''
        await load()
    } catch (failure) {
        confirmIdentity.value = failure.response?.data?.code === 'admin_step_up_required'
        error.value = failure.response?.data?.message || w('Nachricht nicht bestätigt. Bitte erneut senden; Wiederholungen werden nicht doppelt gespeichert.')
    }
    finally { busy.value = false }
}
function selectFiles(event) {
    const chosen = [...event.target.files]
    if (chosen.length > 5 || chosen.some(file => file.size > 10 * 1024 * 1024)) {
        error.value = w('Maximal 5 Dateien, jeweils bis 10 MB.'); event.target.value = ''; return
    }
    files.value = chosen; retryBody = null; retryKey = null
}
function edit() {
    form.value = Object.fromEntries(['project_title', 'domain', 'goals', 'target_audience', 'notes'].map(key => [key, project.value[key] || '']))
    links.value = (project.value.inspiration_urls || []).join('\n'); editing.value = true
}
async function change(action, payload = {}) {
    busy.value = true; error.value = ''; confirmIdentity.value = false
    try {
        const url = route(`auth.commerce.website-project.${action}`, props.projectId)
        if (action === 'update') await axios.patch(url, payload)
        else await axios.post(url, payload)
        editing.value = false; await load(); emit('changed')
    } catch (failure) {
        confirmIdentity.value = failure.response?.data?.code === 'admin_step_up_required'
        error.value = Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || w('Speichern fehlgeschlagen. Bitte erneut versuchen.')
    } finally { busy.value = false }
}
function save() {
    change('update', { ...form.value, inspiration_urls: links.value.split('\n').map(v => v.trim()).filter(Boolean) })
}
function withdraw() {
    if (window.confirm(w('Anfrage wirklich zurückziehen? Der Verlauf bleibt erhalten.'))) change('withdraw')
}
onMounted(() => load())
</script>

<template>
    <section class="space-y-6 rounded-xl border border-border bg-bg p-5" :aria-busy="busy">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h3 class="text-lg font-bold">{{ project?.project_title || `${w('Agenturauftrag')} #${projectId}` }}</h3>
            <button type="button" class="rounded-lg border border-border px-4 py-2" :disabled="busy" @click="load()">{{ w('Aktualisieren') }}</button>
        </div>
        <p v-if="error" role="alert" class="text-error">{{ error }}</p>
        <a v-if="confirmIdentity" :href="route('password.confirm')" target="_blank" rel="noopener" class="text-air-blue underline">{{ w('Identität bestätigen, dann Nachricht erneut senden') }}</a>
        <template v-if="project">
            <p class="font-semibold text-air-blue">{{ w(statuses[project.status] || project.status) }}</p>
            <p v-if="project.withdrawn_at" class="text-secondary">{{ w('Zurückgezogen') }} · {{ date(project.withdrawn_at) }}</p>
            <div class="flex flex-wrap gap-3">
                <button v-if="project.can_edit" type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2" @click="edit">{{ w('Anfrage bearbeiten') }}</button>
                <button v-if="project.status === 'draft' && project.can_edit" type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2" @click="change('update', { status: 'new' })">{{ w('Anfrage absenden') }}</button>
                <button v-if="project.can_withdraw" type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2 text-error" @click="withdraw">{{ w('Anfrage zurückziehen') }}</button>
            </div>
            <p v-if="!project.can_edit" class="text-sm text-secondary">{{ w('Die Anfrage ist für Änderungen gesperrt. Rückfragen sind über die Kommentare möglich, solange das Projekt offen ist.') }}</p>
            <form v-if="editing" class="space-y-4 rounded-lg border border-border p-4" @submit.prevent="save">
                <label v-for="(label, key) in { project_title: 'Projekttitel', domain: 'Gewünschte Domain', goals: 'Ziele und Funktionen', target_audience: 'Zielgruppe', notes: 'Weitere Hinweise' }" :key="key" class="block">{{ w(label) }}<textarea v-model="form[key]" :maxlength="['project_title', 'domain'].includes(key) ? 255 : 2000" class="mt-2 block w-full rounded-lg border-border bg-inputBg text-primary" :disabled="busy" /></label>
                <label class="block">{{ w('Inspirationswebsites') }}<textarea v-model="links" rows="3" class="mt-2 block w-full rounded-lg border-border bg-inputBg text-primary" :disabled="busy" /></label>
                <div class="flex gap-3"><button :disabled="busy" class="rounded-lg border border-border px-4 py-2">{{ w('Änderungen speichern') }}</button><button type="button" :disabled="busy" @click="editing = false">{{ w('Abbrechen') }}</button></div>
            </form>
            <p class="text-sm text-secondary">{{ w('Anfrage → Rückfragen → Angebot → Umsetzung → Abschluss') }}</p>
            <p class="text-sm text-secondary">{{ w('Erstellt') }}: {{ date(project.created_at) }} · {{ w('Unverbindliche Anfrage, keine automatische kostenpflichtige Aktivierung.') }}</p>
            <dl class="grid gap-5 sm:grid-cols-2">
                <div><dt class="font-semibold">{{ w('Auftragsart') }}</dt><dd class="mt-2 text-secondary">{{ w(requestTypes[project.request_type || 'website']) }}</dd></div>
                <div v-for="(label, key) in { domain: 'Gewünschte Domain', goals: 'Ziele und Funktionen', target_audience: 'Zielgruppe', notes: 'Weitere Hinweise' }" :key="key">
                    <dt class="font-semibold">{{ w(label) }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-secondary">{{ project[key] || w('Noch nicht angegeben') }}</dd>
                </div>
                <div><dt class="font-semibold">{{ w('Gewünschte Leistungen') }}</dt><dd class="mt-2 text-secondary">{{ project.deliverables?.map(value => w(deliverableLabels[value] || value)).join(' · ') || w('Noch nicht angegeben') }}</dd></div>
                <div><dt class="font-semibold">{{ w('Geplante Kanäle') }}</dt><dd class="mt-2 text-secondary">{{ project.channels?.map(value => w(channelLabels[value] || value)).join(' · ') || w('Noch nicht angegeben') }}</dd></div>
                <div v-for="(label, key) in { budget_range: 'Budgetrahmen', desired_deadline: 'Gewünschter Fertigstellungstermin', brand_guidelines: 'Markenvorgaben', contact_preference: 'Bevorzugter Kontaktweg' }" :key="key"><dt class="font-semibold">{{ w(label) }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-secondary">{{ project[key] || w('Noch nicht angegeben') }}</dd></div>
            </dl>
            <div v-if="project.inspiration_urls?.length" class="space-y-3">
                <h4 class="font-semibold">{{ w('Inspirationswebsites') }}</h4>
                <template v-for="url in project.inspiration_urls" :key="url"><a v-if="safeLink(url)" :href="url" target="_blank" rel="noopener noreferrer" class="block break-all text-air-blue underline">{{ url }}</a></template>
            </div>
            <div class="space-y-4 border-t border-border pt-6">
                <h4 class="font-semibold">{{ w('Fragen und Kommentare') }}</h4>
                <p class="text-sm text-secondary">{{ w('Sichtbar für die berechtigten Projektbeteiligten und die Administration. Neue Antworten erscheinen nach dem Aktualisieren.') }}</p>
                <form v-if="project.can_comment" class="space-y-4" @submit.prevent="send">
                    <label class="block">{{ w('Deine Nachricht') }}<textarea v-model="body" maxlength="4000" rows="4" :disabled="busy" class="mt-2 block w-full rounded-lg border-border bg-inputBg text-primary"></textarea></label>
                    <label class="block">{{ w('Dateien hinzufügen') }}<input ref="fileInput" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.pptx,.xlsx,.zip" :disabled="busy" class="mt-2 block w-full" @change="selectFiles"><span class="text-sm text-secondary">{{ w('Maximal 5 Dateien, jeweils bis 10 MB.') }} PDF, JPG, PNG, WEBP, DOCX, PPTX, XLSX, ZIP.</span></label>
                    <p v-for="(file, index) in files" :key="index" class="break-all">{{ file.name }}</p>
                    <button :disabled="busy || (!body.trim() && !files.length)" class="rounded-lg bg-buttonPrimary px-5 py-3 font-semibold text-buttonTextPrimary disabled:opacity-50">{{ w('Nachricht senden') }}</button>
                </form>
                <p v-if="!messages.length" class="text-secondary">{{ w('Noch keine Nachrichten. Stelle hier deine erste Frage.') }}</p>
                <article v-for="message in messages" :key="message.id" class="space-y-2 rounded-lg border border-border p-4">
                    <p class="text-sm font-semibold">{{ message.author || w('Projektteam') }} · {{ date(message.created_at) }}</p>
                    <p class="whitespace-pre-wrap break-words">{{ message.kind === 'status' ? `${w('Status geändert')}: ${w(statuses[message.status] || message.status)}` : message.kind === 'updated' ? w('Anfrage bearbeitet') : message.body }}</p>
                    <a v-for="file in message.attachments" :key="file.id" :href="file.download_url" target="_blank" rel="noopener noreferrer" class="block break-all text-air-blue underline">{{ file.name }} ({{ Math.ceil(file.size / 1024) }} KB)</a>
                </article>
                <button v-if="page < lastPage" type="button" :disabled="busy" class="rounded-lg border border-border px-4 py-2" @click="load(true)">{{ w('Ältere Einträge laden') }}</button>
            </div>
        </template>
    </section>
</template>
