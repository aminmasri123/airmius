<script setup>
defineProps({
    attributePresets: {
        type: Array,
        default: () => [],
    },
    attributeRows: {
        type: Array,
        default: () => [],
    },
    featureRows: {
        type: Array,
        default: () => [],
    },
    form: {
        type: Object,
        required: true,
    },
    moneyInputAttrs: {
        type: Object,
        default: () => ({}),
    },
    normalizeAttributeRows: {
        type: Function,
        default: (rows) => rows,
    },
    presetValuesFor: {
        type: Function,
        default: () => [],
    },
    variantRows: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'add-attribute-row',
    'add-feature-row',
    'add-variant-row',
    'close',
    'remove-attribute-row',
    'remove-feature-row',
    'remove-variant-row',
    'set-gallery-uploads',
    'set-image-upload',
    'submit',
])
</script>

<template>
    <div v-if="form.open" class="fixed inset-0 z-50 overflow-y-auto bg-black/60 px-4 py-8">
        <div class="mx-auto w-full max-w-4xl rounded-xl border border-border bg-card p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-primary">Produkt bearbeiten</h2>
                    <p class="mt-1 text-sm text-secondary">Name, Texte, Preis, Bilder, Bestand und Variantenoptionen zentral pflegen.</p>
                </div>
                <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                    Schließen
                </button>
            </div>

            <form class="mt-5 grid gap-3 md:grid-cols-2" @submit.prevent="emit('submit')">
                <input v-model="form.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
                <input v-model="form.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR">
                <select v-model="form.product_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="single">Einfaches Produkt</option>
                    <option value="variable">Variables Produkt</option>
                    <option value="digital">Immaterial / digital</option>
                </select>
                <textarea
                    v-if="form.product_type === 'digital'"
                    v-model="form.digital_delivery_note"
                    rows="2"
                    class="rounded-lg border-border bg-inputBg text-sm text-primary"
                    placeholder="Lieferinfo, z. B. Versand per E-Mail"
                ></textarea>
                <select v-model="form.category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="product">Produkt</option>
                    <option value="course">Kurs</option>
                    <option value="camp">Camp</option>
                    <option value="service">Dienstleistung</option>
                </select>
                <input v-model="form.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer">
                <select v-model="form.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="standard">Standardsteuer</option>
                    <option value="reduced">Ermäßigt</option>
                    <option value="zero">Nullsatz</option>
                </select>
                <select v-model="form.status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="draft">Entwurf</option>
                    <option value="review">Prüfen</option>
                    <option value="published">Freigegeben</option>
                    <option value="archived">Archiviert</option>
                    <option value="rejected">Abgelehnt</option>
                </select>
                <label class="flex items-center gap-2 text-sm text-primary">
                    <input v-model="form.is_shippable" type="checkbox" class="rounded border-border bg-inputBg">
                    Versandpflichtig
                </label>
                <label class="flex items-center gap-2 text-sm text-primary">
                    <input v-model="form.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
                    Lagerbestand verwalten
                </label>
                <input v-if="form.manages_stock" v-model="form.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerbestand">
                <input v-model="form.low_stock_threshold" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Warnbestand">

                <div class="md:col-span-2 grid gap-3 rounded-lg border border-border bg-bg p-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Hauptbild per URL</label>
                        <input v-model="form.image_url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="https://...">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Hauptbild hochladen</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-image-upload', $event)">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Weitere Bild-URLs</label>
                        <textarea v-model="form.image_urls_text" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Eine URL pro Zeile"></textarea>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">Weitere Bilder hochladen</label>
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-gallery-uploads', $event)">
                        <p class="mt-1 text-xs text-secondary">Neue Uploads werden zur Galerie hinzugefügt.</p>
                    </div>
                </div>

                <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">Merkmale / Variantenoptionen</h3>
                            <p class="text-xs text-secondary">Werte mit Komma oder | trennen, z. B. Rot | Blau | Schwarz.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-attribute-row')">
                            Merkmal hinzufügen
                        </button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="(row, index) in attributeRows" :key="index" class="grid gap-2 md:grid-cols-[11rem_minmax(0,1fr)_auto]">
                            <select v-model="row.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" @change="row.values = []">
                                <option value="">Merkmal wählen</option>
                                <option v-for="preset in attributePresets" :key="preset.name" :value="preset.name">{{ preset.name }}</option>
                            </select>
                            <select v-model="row.values" multiple class="min-h-24 rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="value in presetValuesFor(row.name)" :key="value" :value="value">{{ value }}</option>
                            </select>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-attribute-row', index)">
                                Entfernen
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="form.product_type === 'variable'" class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">Varianten</h3>
                            <p class="text-xs text-secondary">Eigene Merkmale, Preis, Bestand und Bild pro Variante.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-variant-row')">
                            Variante hinzufügen
                        </button>
                    </div>
                    <div class="mt-3 space-y-3">
                        <div v-for="(variant, index) in variantRows" :key="index" class="rounded-lg border border-border p-3">
                            <div class="grid gap-2 md:grid-cols-4">
                                <select
                                    v-for="attribute in normalizeAttributeRows(attributeRows)"
                                    :key="attribute.name"
                                    v-model="variant.attributes[attribute.name]"
                                    class="rounded-lg border-border bg-inputBg text-sm text-primary"
                                >
                                    <option value="">{{ attribute.name }}</option>
                                    <option v-for="value in attribute.values" :key="value" :value="value">{{ value }}</option>
                                </select>
                                <input v-model="variant.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR">
                                <input v-model="variant.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Bestand">
                                <input v-model="variant.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer">
                                <input v-model="variant.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-2" placeholder="Bild-URL für Variante">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-variant-row', index)">
                                    Variante entfernen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">Produkt-Highlights</h3>
                            <p class="text-xs text-secondary">Kurze Bulletpoints für die Produktseite.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-feature-row')">
                            Punkt hinzufügen
                        </button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="(feature, index) in featureRows" :key="index" class="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto]">
                            <input v-model="featureRows[index]" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Atmungsaktiv">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-feature-row', index)">
                                Entfernen
                            </button>
                        </div>
                    </div>
                </div>

                <textarea v-model="form.description" rows="4" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Beschreibung"></textarea>

                <div class="md:col-span-2 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="emit('close')">
                        Abbrechen
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="form.processing">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

