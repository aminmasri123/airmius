<script setup>
import { useWebsiteProjectCopy } from '@/composables/useWebsiteProjectCopy'
const w = useWebsiteProjectCopy()
import { useI18n } from 'vue-i18n'
import { ref, watch } from 'vue'
import WebsiteProjectConversation from './WebsiteProjectConversation.vue'
const selected = ref(null)
const inspirationText = ref('')

const props = defineProps({
    design: { type: Boolean, default: false },
    clubs: { type: Array, default: () => [] },
    websiteRequests: { type: Array, default: () => [] },
    websiteRequestModal: { type: Boolean, default: false },
    websiteForm: { type: Object, required: true },
})

const emit = defineEmits([
    'open-website-request-modal',
    'update:websiteRequestModal',
    'store-website-request',
])

const { t } = useI18n()
watch(inspirationText, value => { props.websiteForm.inspiration_urls = value.split('\n').map(v => v.trim()).filter(Boolean) })
watch(() => props.websiteRequestModal, () => { inspirationText.value = props.websiteForm.inspiration_urls.join('\n') })
</script>

<template>
    <article class="surface-card p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">{{ design ? w('Design & Druck') : w('Webseite') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ w('Übermittle Ziele, Leistungen, Kanäle, Budget, Termin und Referenzen in einem vollständigen Briefing.') }}</p>
            </div>
            <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('open-website-request-modal')">
                {{ design ? w('Design- oder Druckauftrag erstellen') : w('Website anfragen') }}
            </button>
        </div>
        <p class="mt-4 text-sm text-secondary">{{ t('agency.request_count', { count: websiteRequests.length }) }}</p>

        <div v-if="websiteRequestModal" class="mt-6 border-t border-border pt-6">
            <form class="relative mx-auto w-full max-w-3xl rounded-xl border border-border bg-card p-6" @submit.prevent="emit('store-website-request')">
                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                    :aria-label="t('agency.close')"
                    @click="emit('update:websiteRequestModal', false)"
                >
                    <i class="las la-times text-xl"></i>
                </button>
                <div class="mb-4 pr-12">
                    <p class="text-xs font-semibold uppercase text-air-blue">{{ w('Detailliertes Briefing') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">{{ design ? w('Design- oder Druckauftrag erstellen') : w('Website anfragen') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ w('Je genauer die Angaben, desto gezielter können Aufwand, Angebot und Umsetzung geplant werden.') }}</p>
                </div>
                                <div class="grid gap-6">
                    <p class="rounded-lg border border-border p-4 text-sm text-secondary">{{ w('Unverbindliche Anfrage: Es wird kein kostenpflichtiges Paket automatisch aktiviert. Beschreibe Ziele, Zielgruppe und gewünschte Funktionen.') }}</p>
                    <label v-if="design" class="grid gap-2">{{ w('Auftragsart') }}<select v-model="websiteForm.request_type" required class="rounded-lg border-border bg-inputBg text-primary">
                        <option v-for="item in [{v:'print',l:'Printmaterial'}, {v:'logo_branding',l:'Logo & Branding'}, {v:'video',l:'Video'}, {v:'photo',l:'Foto'}, {v:'other',l:'Sonstiges'}]" :key="item.v" :value="item.v">{{ w(item.l) }}</option>
                    </select></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label v-for="item in (design ? [{v:'product',l:'Produkt: Flyer, Visitenkarten, Kleidung …'}, {v:'service',l:'Nur Gestaltung, nur Druck oder beides?'}, {v:'quantity',l:'Stückzahl und Größenverteilung'}, {v:'dimensions',l:'Maße, Format und Material'}, {v:'finish',l:'Farben, Druckseiten und Veredelung'}, {v:'delivery',l:'Lieferort und Liefertermin'}] : [{v:'existing_site',l:'Bestehende Website und Domain'}, {v:'pages',l:'Gewünschte Seiten und Sprachen'}, {v:'features',l:'Shop, Buchung, Mitgliederbereich und Funktionen'}, {v:'content',l:'Vorhandene Texte, Bilder und Logo'}, {v:'hosting',l:'Hosting, E-Mail und spätere Betreuung'}, {v:'integrations',l:'Schnittstellen und besondere Anforderungen'}])" :key="item.v" class="grid gap-2">{{ w(item.l) }}<textarea v-model="websiteForm.quote_details[item.v]" maxlength="1000" rows="2" class="rounded-lg border-border bg-inputBg text-primary"></textarea></label>
                    </div>
                    <label class="grid gap-2">{{ w('Projekttitel') }}<input v-model="websiteForm.project_title" maxlength="255" class="rounded-lg border-border bg-inputBg text-primary"></label>
                    <label class="grid gap-2">{{ w('Zielgruppe') }}<textarea v-model="websiteForm.target_audience" maxlength="2000" rows="2" class="rounded-lg border-border bg-inputBg text-primary"></textarea></label>
                    <label class="grid gap-2">{{ w('Inspirationswebsites') }}<textarea v-model="inspirationText" rows="3" class="rounded-lg border-border bg-inputBg text-primary"></textarea><span class="text-xs text-secondary">{{ w('Bis zu fünf vollständige HTTP-/HTTPS-Links, ein Link pro Zeile.') }}</span></label>
                    <ul v-if="Object.keys(websiteForm.errors).length" role="alert" class="text-error"><li v-for="(error, field) in websiteForm.errors" :key="field">{{ error }}</li></ul>
                    <label class="grid gap-2">{{ w('Verein') }}<select v-model="websiteForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select></label>
                    <label v-if="!design" class="grid gap-2">{{ w('Gewünschte Domain') }}<input v-model="websiteForm.domain" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('agency.domain')"></label>
                    <label class="grid gap-2">{{ w('Ziele und Funktionen') }}<textarea v-model="websiteForm.goals" required maxlength="2000" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('agency.goals')"></textarea></label>
                    <fieldset class="grid gap-3"><legend class="font-semibold">{{ w('Gewünschte Leistungen') }}</legend><div class="flex flex-wrap gap-3"><label v-for="item in [{v:'strategy',l:'Konzept & Strategie'}, {v:'copy',l:'Texte'}, {v:'design',l:'Design'}, {v:'banner',l:'Banner'}, {v:'social_post',l:'Social-Post'}, {v:'print_file',l:'Druckdatei'}, {v:'logo',l:'Logo'}, {v:'photo',l:'Fotos'}, {v:'video',l:'Video'}, {v:'landing_page',l:'Landingpage'}, {v:'other',l:'Sonstiges'}]" :key="item.v" class="flex items-center gap-2 rounded-lg border border-border px-3 py-2"><input v-model="websiteForm.deliverables" type="checkbox" :value="item.v">{{ w(item.l) }}</label></div></fieldset>
                    <fieldset class="grid gap-3"><legend class="font-semibold">{{ w('Geplante Kanäle') }}</legend><div class="flex flex-wrap gap-3"><label v-for="item in [{v:'website',l:'Website'}, {v:'app',l:'App'}, {v:'instagram',l:'Instagram'}, {v:'facebook',l:'Facebook'}, {v:'linkedin',l:'LinkedIn'}, {v:'tiktok',l:'TikTok'}, {v:'youtube',l:'YouTube'}, {v:'email',l:'E-Mail'}, {v:'print',l:'Print'}, {v:'outdoor',l:'Außenwerbung'}, {v:'other',l:'Sonstiges'}]" :key="item.v" class="flex items-center gap-2 rounded-lg border border-border px-3 py-2"><input v-model="websiteForm.channels" type="checkbox" :value="item.v">{{ w(item.l) }}</label></div></fieldset>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2">{{ w('Budgetrahmen (optional)') }}<input v-model="websiteForm.budget_range" maxlength="80" :placeholder="w('z. B. 500–1.000 €')" class="rounded-lg border-border bg-inputBg text-primary"></label>
                        <label class="grid gap-2">{{ w('Gewünschter Fertigstellungstermin') }}<input v-model="websiteForm.desired_deadline" type="date" class="rounded-lg border-border bg-inputBg text-primary"></label>
                    </div>
                    <label class="grid gap-2">{{ w('Markenvorgaben, Farben, Schriften und Tonalität') }}<textarea v-model="websiteForm.brand_guidelines" maxlength="2000" rows="3" class="rounded-lg border-border bg-inputBg text-primary"></textarea></label>
                    <label class="grid gap-2">{{ w('Bevorzugter Kontaktweg') }}<select v-model="websiteForm.contact_preference" class="rounded-lg border-border bg-inputBg text-primary"><option value="app">{{ w('Nachrichten im Projektraum') }}</option><option value="email">{{ w('E-Mail') }}</option><option value="phone">{{ w('Telefon') }}</option></select></label>
                    <label class="grid gap-2">{{ w('Weitere Hinweise') }}<textarea v-model="websiteForm.notes" maxlength="2000" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('agency.notes')"></textarea></label>
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-border bg-bg p-3 text-sm font-semibold text-primary">
                        <input v-model="websiteForm.accepted_privacy" type="checkbox" class="mt-0.5 rounded border-border bg-inputBg text-air-blue focus:ring-air-blue">
                        <span>{{ t('agency.privacy_accept') }}</span>
                    </label>
                    <label class="flex gap-2"><input v-model="websiteForm.status" type="checkbox" true-value="draft" false-value="new">{{ w('Als Entwurf speichern') }}</label>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="websiteForm.processing || !websiteForm.accepted_privacy">{{ websiteForm.status === 'draft' ? w('Als Entwurf speichern') : t('agency.send') }}</button>
                </div>
            </form>
        </div>
        <div class="mt-6 space-y-4">
            <article v-for="request in websiteRequests" :key="request.id" class="rounded-xl border border-border p-5">
                <button type="button" class="flex w-full flex-wrap items-center justify-between gap-4 text-start" :aria-expanded="selected === request.id" @click="selected = selected === request.id ? null : request.id">
                    <span class="font-semibold">{{ request.project_title || request.club?.name || `${w('Website-Projekt')} #${request.id}` }}</span><span class="text-air-blue">{{ w('Projekt und Nachrichten öffnen') }}</span>
                </button>
                <WebsiteProjectConversation v-if="selected === request.id" :project-id="request.id" class="mt-5" />
            </article>
        </div>
    </article>
</template>
