<script setup>
defineProps({
    modal: { type: Object, required: true },
    form: { type: Object, required: true },
    adCreativeRows: { type: Array, default: () => [] },
})

const emit = defineEmits([
    'add-row',
    'close',
    'remove-row',
    'submit',
])
</script>

<template>
    <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <form class="relative max-h-[90dvh] w-full max-w-3xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="emit('submit')">
            <button
                type="button"
                class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                aria-label="Anzeige und Varianten schließen"
                @click="emit('close')"
            >
                <i class="las la-times text-xl"></i>
            </button>
            <div class="pr-12">
                <p class="text-xs font-semibold uppercase text-air-blue">Schritt 3</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">Anzeige und A/B-Varianten erstellen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Kampagne: {{ modal.campaign?.name }} · Anzeigegruppe: {{ modal.group?.name }}
                </p>
            </div>

            <div class="mt-5 grid gap-3">
                <label class="grid gap-1">
                    <span class="text-xs font-semibold uppercase text-secondary">Anzeigenname</span>
                    <input v-model="form.ad_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Sommeraktion Anzeige 1">
                </label>
                <p v-if="form.errors.ad_name" class="text-sm text-error">{{ form.errors.ad_name }}</p>

                <div class="rounded-lg border border-border bg-bg p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">Varianten für A/B-Test</label>
                            <p class="mt-1 text-xs text-secondary">Jede Variante kann eigene Headline, Text, Ziel-URL, Bild-URL und Gewicht haben.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-row')">
                            Variante hinzufügen
                        </button>
                    </div>
                    <div class="mt-3 space-y-3">
                        <div v-for="(creative, index) in adCreativeRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-card p-3">
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
                                <input v-model="creative.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Ziel-URL">
                                <input v-model="creative.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="CTA, z. B. Jetzt ansehen">
                            </div>
                            <input v-model="creative.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bild-URL dieser Variante">
                            <button v-if="adCreativeRows.length > 1" type="button" class="justify-self-start rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="emit('remove-row', index)">
                                Variante entfernen
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="form.errors.creatives" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                    {{ form.errors.creatives }}
                </div>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                    Abbrechen
                </button>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="form.processing">
                    Anzeige speichern
                </button>
            </div>
        </form>
    </div>
</template>


