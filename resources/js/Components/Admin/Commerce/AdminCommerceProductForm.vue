<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
    productForm: { type: Object, required: true },
    productAttributeRows: { type: Array, default: () => [] },
    productFeatureRows: { type: Array, default: () => [] },
    productVariantRows: { type: Array, default: () => [] },
    attributePresets: { type: Array, default: () => [] },
    moneyInputAttrs: { type: Object, default: () => ({}) },
    presetValuesFor: { type: Function, required: true },
    normalizeAttributeRows: { type: Function, required: true },
})

const emit = defineEmits([
    'store-product',
    'set-product-image-upload',
    'set-product-gallery-uploads',
    'add-product-attribute-row',
    'remove-product-attribute-row',
    'add-product-variant-row',
    'remove-product-variant-row',
    'add-product-feature-row',
    'remove-product-feature-row',
])
</script>

<template>
    <h2 class="text-lg font-semibold text-primary">Marketplace-Produkt</h2>
    <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="emit('store-product')">
        <input v-model="productForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Titel">
        <input v-model="productForm.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Preis in EUR, z. B. 10,99">
        <select v-model="productForm.product_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
            <option value="single">Einfaches Produkt</option>
            <option value="variable">Variables Produkt</option>
            <option value="digital">Immaterial / digital</option>
        </select>
        <textarea
            v-if="productForm.product_type === 'digital'"
            v-model="productForm.digital_delivery_note"
            rows="2"
            class="rounded-lg border-border bg-inputBg text-sm text-primary"
            placeholder="Lieferinfo, z. B. Ticketcode oder Zugang wird per E-Mail versendet"
        ></textarea>

        <div class="md:col-span-2 grid gap-3 rounded-lg border border-border bg-bg p-3 md:grid-cols-2">
            <div>
                <label class="text-xs font-semibold uppercase text-secondary">Hauptbild per URL</label>
                <input v-model="productForm.image_url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.url_placeholder')">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-secondary">Hauptbild hochladen</label>
                <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:me-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-product-image-upload', $event)">
                <p class="mt-1 text-xs text-secondary">JPG, PNG oder WebP. Upload ersetzt die URL.</p>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-secondary">Weitere Bild-URLs</label>
                <textarea v-model="productForm.image_urls_text" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Eine URL pro Zeile"></textarea>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-secondary">Weitere Bilder hochladen</label>
                <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:me-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-product-gallery-uploads', $event)">
                <p class="mt-1 text-xs text-secondary">Bis zu 8 Dateien, Galerie maximal 12 Bilder.</p>
            </div>
        </div>

        <select v-model="productForm.category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
            <option value="product">{{ t('commerce.ui.product') }}</option>
            <option value="course">{{ t('commerce.ui.course') }}</option>
            <option value="camp">{{ t('commerce.ui.camp') }}</option>
            <option value="service">Dienstleistung</option>
        </select>
        <input v-model="productForm.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Artikelnummer">
        <select v-model="productForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
            <option value="standard">Standardsteuer</option>
            <option value="reduced">Ermäßigt</option>
            <option value="zero">Nullsatz</option>
        </select>
        <select v-model="productForm.status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
            <option value="draft">Entwurf</option>
            <option value="review">Prüfung</option>
            <option value="published">Öffentlich</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-primary">
            <input v-model="productForm.is_shippable" type="checkbox" class="rounded border-border bg-inputBg">
            Versandpflichtig
        </label>
        <label class="flex items-center gap-2 text-sm text-primary">
            <input v-model="productForm.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
            Lagerbestand verwalten
        </label>
        <input v-if="productForm.manages_stock" v-model="productForm.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Lagerbestand">

        <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-primary">Merkmale / Variantenoptionen</h3>
                    <p class="text-xs text-secondary">Ein Merkmal pro Zeile. Werte mit Komma oder | trennen, z. B. Rot | Blau | Schwarz.</p>
                </div>
                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-product-attribute-row')">
                    Merkmal hinzufügen
                </button>
            </div>
            <div class="mt-3 space-y-2">
                <div v-for="(row, index) in productAttributeRows" :key="index" class="grid gap-2 md:grid-cols-[11rem_minmax(0,1fr)_auto]">
                    <select v-model="row.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" @change="row.values = []">
                        <option value="">Merkmal wählen</option>
                        <option v-for="preset in attributePresets" :key="preset.name" :value="preset.name">{{ preset.name }}</option>
                    </select>
                    <select v-model="row.values" multiple class="min-h-24 rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="value in presetValuesFor(row.name)" :key="value" :value="value">{{ value }}</option>
                    </select>
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-product-attribute-row', index)">
                        Entfernen
                    </button>
                </div>
            </div>
        </div>

        <div v-if="productForm.product_type === 'variable'" class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-primary">{{ t('commerce.ui.variants') }}</h3>
                    <p class="text-xs text-secondary">Jede Variante kann eigene Merkmale, Preis, Bestand und Bild haben.</p>
                </div>
                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-product-variant-row')">
                    Variante hinzufügen
                </button>
            </div>
            <div class="mt-3 space-y-3">
                <div v-for="(variant, index) in productVariantRows" :key="index" class="rounded-lg border border-border p-3">
                    <div class="grid gap-2 md:grid-cols-4">
                        <select
                            v-for="attribute in normalizeAttributeRows(productAttributeRows)"
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
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-product-variant-row', index)">Variante entfernen</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-primary">Produkt-Highlights</h3>
                    <p class="text-xs text-secondary">Kurze Bulletpoints, die auf der Produktseite als Merkmale erscheinen.</p>
                </div>
                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-product-feature-row')">
                    Punkt hinzufügen
                </button>
            </div>
            <div class="mt-3 space-y-2">
                <div v-for="(feature, index) in productFeatureRows" :key="index" class="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto]">
                    <input v-model="productFeatureRows[index]" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="z. B. Atmungsaktiv">
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-product-feature-row', index)">
                        Entfernen
                    </button>
                </div>
            </div>
        </div>

        <textarea v-model="productForm.description" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('commerce.ui.description_placeholder')"></textarea>
        <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">Speichern</button>
    </form>
</template>
