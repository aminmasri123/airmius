<script setup>
defineProps({
    modal: { type: Object, required: true },
    form: { type: Object, required: true },
    adPlacements: { type: Array, default: () => [] },
    editCampaignAdFormats: { type: Array, default: () => [] },
    selectedEditAdPlacement: { type: Object, default: () => ({}) },
    selectedEditAdFormat: { type: Object, default: () => ({}) },
    editCampaignPreviewUrl: { type: String, default: '' },
    editCampaignCreativeRows: { type: Array, default: () => [] },
    moneyInputAttrs: { type: Object, default: () => ({}) },
    creativePreviewUrl: { type: Function, required: true },
})

const emit = defineEmits([
    'add-creative-row',
    'close',
    'remove-creative-row',
    'set-creative-upload',
    'submit',
])
</script>

<template>
    <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <form class="relative max-h-[90dvh] w-full max-w-3xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="emit('submit')">
            <button
                type="button"
                class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                aria-label="Bearbeiten schließen"
                @click="emit('close')"
            >
                <i class="las la-times text-xl"></i>
            </button>
            <div class="pr-12">
                <h2 class="text-lg font-semibold text-primary">Ads-Kampagne bearbeiten</h2>
                <p class="mt-2 text-sm text-secondary">
                    Bezahlte oder bereits aktive Kampagnen gehen nach Änderungen wieder zur Admin-Prüfung.
                </p>
            </div>

            <div class="mt-5 grid gap-3">
                <input v-model="form.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kampagnenname">
                <p v-if="form.errors.name" class="text-sm text-error">{{ form.errors.name }}</p>
                <input v-model="form.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Headline, max. 120 Zeichen">
                <input v-model="form.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL">
                <p v-if="form.errors.target_url" class="text-sm text-error">{{ form.errors.target_url }}</p>
                <textarea v-model="form.primary_text" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anzeigentext / Primary Text"></textarea>
                <textarea v-model="form.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interne Beschreibung"></textarea>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Ziel</label>
                        <select v-model="form.objective" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="traffic">Traffic</option>
                            <option value="awareness">Reichweite</option>
                            <option value="leads">Leads</option>
                            <option value="sales">Sales</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Placement - wo erscheint die Ad?</label>
                        <select v-model="form.placement" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="placement in adPlacements" :key="placement.key" :value="placement.key">{{ placement.label }}</option>
                        </select>
                        <p class="mt-1 text-xs text-secondary">{{ selectedEditAdPlacement.hint }}</p>
                    </div>
                </div>
                <div class="rounded-lg border border-border bg-bg p-3">
                    <label class="text-xs font-semibold uppercase text-secondary">Creative Format - welches Bildmass?</label>
                    <select v-model="form.creative_format" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="format in editCampaignAdFormats" :key="format.key" :value="format.key">
                            {{ format.label }} - {{ format.size }}
                        </option>
                    </select>
                    <p class="mt-2 text-sm font-semibold text-primary">{{ selectedEditAdFormat.size }} - {{ selectedEditAdFormat.ratio }}</p>
                    <p class="text-xs text-secondary">{{ selectedEditAdFormat.hint }}</p>
                    <p class="mt-2 rounded-lg border border-border bg-card px-3 py-2 text-xs text-secondary">
                        Placement entscheidet den Ort. Creative Format entscheidet nur Größe und Seitenverhältnis der Anzeige.
                    </p>
                </div>
                <input v-model="form.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL optional">
                <div class="overflow-hidden rounded-lg border border-border bg-bg">
                    <div class="flex items-center justify-between gap-3 border-b border-border px-3 py-2">
                        <span class="text-xs font-semibold uppercase text-secondary">Aktuelles Anzeigenbild</span>
                        <span class="text-xs text-secondary">{{ selectedEditAdFormat.size }}</span>
                    </div>
                    <img
                        v-if="editCampaignPreviewUrl"
                        :src="editCampaignPreviewUrl"
                        :alt="form.headline || form.name || 'Ads Vorschau'"
                        class="max-h-64 w-full bg-inputBg object-contain"
                    >
                    <div v-else class="flex min-h-36 items-center justify-center px-4 py-8 text-center text-sm text-secondary">
                        Noch kein Anzeigenbild hinterlegt.
                    </div>
                </div>
                <div class="rounded-lg border border-border bg-bg p-3">
                    <label class="text-xs font-semibold uppercase text-secondary">Neues Hauptbild hochladen</label>
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-creative-upload', $event)">
                </div>

                <div class="rounded-lg border border-border bg-bg p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">A/B-Test Varianten</label>
                            <p class="mt-1 text-xs text-secondary">Bearbeite Headline, Text, Ziel-URL, Bild-URL und Gewicht.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-creative-row')">
                            Variante hinzufügen
                        </button>
                    </div>
                    <div class="mt-3 space-y-3">
                        <div v-for="(creative, index) in editCampaignCreativeRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-card p-3">
                            <div class="grid gap-2 sm:grid-cols-[1fr_6rem_auto]">
                                <input v-model="creative.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Variante A">
                                <input v-model.number="creative.weight" type="number" min="1" max="1000" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Gewicht">
                                <label class="flex items-center gap-2 text-xs font-semibold text-primary">
                                    <input v-model="creative.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                                    Aktiv
                                </label>
                            </div>
                            <input v-model="creative.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Headline dieser Variante">
                            <textarea v-model="creative.primary_text" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Anzeigentext dieser Variante"></textarea>
                            <textarea v-model="creative.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung dieser Variante"></textarea>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <input v-model="creative.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL optional">
                                <input v-model="creative.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA optional">
                            </div>
                            <input v-model="creative.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL dieser Variante">
                            <div v-if="creativePreviewUrl(creative)" class="overflow-hidden rounded-lg border border-border bg-bg">
                                <p class="border-b border-border px-3 py-2 text-xs font-semibold uppercase text-secondary">Variantenbild</p>
                                <img :src="creativePreviewUrl(creative)" :alt="creative.name || 'Variantenbild'" class="max-h-40 w-full bg-inputBg object-contain">
                            </div>
                            <button v-if="editCampaignCreativeRows.length > 1" type="button" class="justify-self-start rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="emit('remove-creative-row', index)">
                                Variante entfernen
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <input v-model="form.budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary disabled:opacity-60" placeholder="Gesamtbudget in EUR" :disabled="modal.campaign?.payment_completed">
                    <input v-model="form.daily_budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Tagesbudget in EUR">
                    <input v-model="form.starts_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <input v-model="form.ends_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                </div>
                <p v-if="modal.campaign?.payment_completed" class="text-xs text-secondary">Das bezahlte Gesamtbudget kann hier nicht nachträglich geändert werden.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input v-model="form.audience_age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter von">
                    <input v-model="form.audience_age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Alter bis">
                </div>
                <input v-model="form.audience_locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Regionen, z. B. Berlin, NRW">
                <input v-model="form.audience_interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Interessen, z. B. Fußball, Fitness">
                <input v-model="form.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA, z. B. Jetzt ansehen">
            </div>

            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                    Abbrechen
                </button>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="form.processing">
                    Speichern
                </button>
            </div>
        </form>
    </div>
</template>

