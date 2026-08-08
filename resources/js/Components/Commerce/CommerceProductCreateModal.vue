<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps({
    clubs: { type: Array, default: () => [] },
    productForm: { type: Object, required: true },
    learningCourses: { type: Array, default: () => [] },
    sellerMarketplaceCategories: { type: Array, default: () => [] },
    selectedProductCommission: { type: Object, default: () => ({ label: '', commission_percent: 0 }) },
    productCommissionPreviewCents: { type: Number, default: 0 },
    productSellerPayoutPreviewCents: { type: Number, default: 0 },
    productStockRequired: { type: Boolean, default: false },
    isLearningOffer: { type: Boolean, default: false },
    inventoryCountries: { type: Array, default: () => [] },
    inventoryTotalStock: { type: Number, default: 0 },
    productAttributeRows: { type: Array, default: () => [] },
    productVariantRows: { type: Array, default: () => [] },
    attributePresets: { type: Array, default: () => [] },
    moneyInputAttrs: { type: Object, default: () => ({}) },
    formatMoney: { type: Function, required: true },
    presetValuesFor: { type: Function, required: true },
    normalizeAttributeRows: { type: Function, required: true },
})

const emit = defineEmits([
    'close',
    'store-product',
    'set-product-image-upload',
    'set-product-gallery-uploads',
    'add-product-inventory-row',
    'remove-product-inventory-row',
    'add-product-attribute-row',
    'remove-product-attribute-row',
    'add-product-variant-row',
    'remove-product-variant-row',
])

const { locale } = useI18n()

const copy = {
    de: {
        close: 'Produkt erstellen schließen',
        eyebrow: 'Verkaufen',
        title: 'Produkt erstellen',
        intro: 'Das Angebot geht danach zur Prüfung und wird erst nach Freigabe im Marketplace angezeigt.',
        validation: 'Bitte prüfe die markierten Angaben. Pflichtfelder wie Titel, Kategorie und Preis müssen ausgefüllt sein.',
        seller: 'Privat / Anbieter',
        productTitle: 'Titel',
        mainImageUrl: 'Hauptbild per URL',
        mainImageUpload: 'Hauptbild hochladen',
        uploadReplacesUrl: 'JPG, PNG oder WebP. Upload ersetzt die URL.',
        extraImageUrls: 'Weitere Bild-URLs',
        oneUrlPerLine: 'Eine URL pro Zeile',
        extraImagesUpload: 'Weitere Bilder hochladen',
        galleryLimit: 'Bis zu 8 Dateien, Galerie maximal 12 Bilder.',
        offerCategory: 'Kategorie des Angebots',
        physicalProduct: 'Produkt / Equipment',
        onlineCourse: 'Kurs / E-Learning',
        trainingPlan: 'Trainingsplan mit Feedback',
        camp: 'Camp / Workshop',
        offerCategoryHelp: 'Diese Auswahl bestimmt, in welchem Marketplace-Bereich das Angebot nach Freigabe erscheint.',
        marketplaceCategory: 'Marketplace-Kategorie',
        marketplaceCategoryHelp: 'Die Kategorie bestimmt die externe Marketplace-Provision. Dienstleistungen werden intern von Airmius angelegt.',
        sku: 'Artikelnummer',
        simpleProduct: 'Einfaches Produkt',
        variableProduct: 'Variables Produkt',
        digitalProduct: 'Immaterial / digital',
        digitalDelivery: 'Lieferinfo, z. B. Ticketcode oder Zugang wird per E-Mail versendet',
        linkCourse: 'Mit Sportschule-Kurs verknüpfen',
        noCourse: 'Keinen Kurs automatisch freischalten',
        courseAutoUnlock: 'Nach bezahlter Bestellung wird der verknüpfte Kurs automatisch für den Käufer freigeschaltet.',
        courseOutline: 'Kursinhalt / Module',
        lessonPerLine: 'Eine Lektion oder Trainingsphase pro Zeile',
        learningGoals: 'Lernziele',
        goalPerLine: 'Ein Lernziel pro Zeile',
        coachingEnabled: 'Athleten-Feedback aktivieren',
        coachingInstructions: 'Was sollen Athleten als Fortschritt senden? Bilder, Notizen, Belastung, Fragen ...',
        standardTax: 'Standardsteuer',
        reducedTax: 'Ermäßigt',
        zeroTax: 'Nullsatz',
        price: 'Preis in EUR, z. B. 10,99',
        commission: 'Airmius-Provision',
        commissionHelp: 'Die Provision wird nach der gewählten Kategorie berechnet und intern am Produkt gespeichert.',
        commissionAtPrice: 'Provision bei diesem Preis',
        payoutPreview: 'Voraussichtliche Auszahlung:',
        shippable: 'Versandpflichtig',
        manageStock: 'Lagerbestand verwalten',
        totalStock: 'Gesamtbestand *',
        atLeastOne: 'Mindestens 1',
        stock: 'Lagerbestand',
        countryInventory: 'Länderbestand',
        countryInventoryHelp: 'Nur Länder mit aktivem Bestand werden im internationalen Marketplace angeboten.',
        addCountry: 'Land hinzufügen',
        stockShort: 'Bestand',
        lowStock: 'Warnbestand',
        leadTime: 'Lieferzeit Tage',
        warehouseCity: 'Lagerstadt',
        remove: 'Entfernen',
        currentCountryStock: 'Aktueller Gesamtbestand aus Ländern:',
        attributes: 'Merkmale / Variantenoptionen',
        attributesHelp: 'Ein Merkmal pro Zeile. Werte mit Komma oder | trennen.',
        addAttribute: 'Merkmal hinzufügen',
        chooseAttribute: 'Merkmal wählen',
        variants: 'Varianten',
        variantsHelp: 'Eigener Preis, Bestand und Bild pro Variante.',
        addVariant: 'Variante',
        variantPrice: 'Preis in EUR',
        imageUrl: 'Bild-URL',
        removeVariant: 'Variante entfernen',
        description: 'Beschreibung',
        submit: 'Zur Prüfung einreichen',
    },
    en: {
        close: 'Close product creation',
        eyebrow: 'Sell',
        title: 'Create product',
        intro: 'The offer is submitted for review and appears in the marketplace only after approval.',
        validation: 'Please check the highlighted fields. Required fields such as title, category and price must be filled.',
        seller: 'Private / provider',
        productTitle: 'Title',
        mainImageUrl: 'Main image by URL',
        mainImageUpload: 'Upload main image',
        uploadReplacesUrl: 'JPG, PNG or WebP. Upload replaces the URL.',
        extraImageUrls: 'Additional image URLs',
        oneUrlPerLine: 'One URL per line',
        extraImagesUpload: 'Upload additional images',
        galleryLimit: 'Up to 8 files, gallery maximum 12 images.',
        offerCategory: 'Offer category',
        physicalProduct: 'Product / equipment',
        onlineCourse: 'Course / e-learning',
        trainingPlan: 'Training plan with feedback',
        camp: 'Camp / workshop',
        offerCategoryHelp: 'This selection defines where the offer appears in the marketplace after approval.',
        marketplaceCategory: 'Marketplace category',
        marketplaceCategoryHelp: 'The category defines the external marketplace commission. Services are created internally by Airmius.',
        sku: 'SKU',
        simpleProduct: 'Simple product',
        variableProduct: 'Variable product',
        digitalProduct: 'Intangible / digital',
        digitalDelivery: 'Delivery info, e.g. ticket code or access sent by email',
        linkCourse: 'Link Sportschule course',
        noCourse: 'Do not unlock a course automatically',
        courseAutoUnlock: 'After paid order, the linked course is automatically unlocked for the buyer.',
        courseOutline: 'Course content / modules',
        lessonPerLine: 'One lesson or training phase per line',
        learningGoals: 'Learning goals',
        goalPerLine: 'One learning goal per line',
        coachingEnabled: 'Enable athlete feedback',
        coachingInstructions: 'What should athletes send as progress? Images, notes, load, questions ...',
        standardTax: 'Standard tax',
        reducedTax: 'Reduced',
        zeroTax: 'Zero rate',
        price: 'Price in EUR, e.g. 10.99',
        commission: 'Airmius commission',
        commissionHelp: 'The commission is calculated by selected category and stored internally on the product.',
        commissionAtPrice: 'Commission at this price',
        payoutPreview: 'Estimated payout:',
        shippable: 'Requires shipping',
        manageStock: 'Manage stock',
        totalStock: 'Total stock *',
        atLeastOne: 'At least 1',
        stock: 'Stock',
        countryInventory: 'Country inventory',
        countryInventoryHelp: 'Only countries with active inventory are offered in the international marketplace.',
        addCountry: 'Add country',
        stockShort: 'Stock',
        lowStock: 'Low stock',
        leadTime: 'Lead time days',
        warehouseCity: 'Warehouse city',
        remove: 'Remove',
        currentCountryStock: 'Current total country stock:',
        attributes: 'Attributes / variant options',
        attributesHelp: 'One attribute per row. Separate values with comma or |.',
        addAttribute: 'Add attribute',
        chooseAttribute: 'Choose attribute',
        variants: 'Variants',
        variantsHelp: 'Own price, stock and image per variant.',
        addVariant: 'Variant',
        variantPrice: 'Price in EUR',
        imageUrl: 'Image URL',
        removeVariant: 'Remove variant',
        description: 'Description',
        submit: 'Submit for review',
    },
}

copy.fr = copy.en
copy.ar = copy.en

const labels = computed(() => copy[locale.value] || copy.de)
const c = (key) => labels.value[key] || copy.de[key] || key
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <form
            class="relative max-h-[90dvh] w-full max-w-4xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl"
            @submit.prevent="emit('store-product')"
        >
            <button
                type="button"
                class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                :aria-label="c('close')"
                @click="emit('close')"
            >
                <i class="las la-times text-xl"></i>
            </button>
            <div class="mb-4 pr-12">
                <p class="text-xs font-semibold uppercase text-air-blue">{{ c('eyebrow') }}</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">{{ c('title') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ c('intro') }}</p>
            </div>

            <div class="grid gap-3">
                <div v-if="Object.keys(productForm.errors).length" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                    {{ c('validation') }}
                </div>
                <select v-model="productForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="">{{ c('seller') }}</option>
                    <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                </select>
                <p v-if="productForm.errors.club_id" class="text-sm text-error">{{ productForm.errors.club_id }}</p>
                <input v-model="productForm.title" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('productTitle')">
                <p v-if="productForm.errors.title" class="text-sm text-error">{{ productForm.errors.title }}</p>

                <div class="grid gap-3 rounded-lg border border-border bg-bg p-3">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('mainImageUrl') }}</label>
                        <input v-model="productForm.image_url" type="url" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="$t('commerce.ui.url_placeholder')">
                        <p v-if="productForm.errors.image_url" class="mt-1 text-sm text-error">{{ productForm.errors.image_url }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('mainImageUpload') }}</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-product-image-upload', $event)">
                        <p class="mt-1 text-xs text-secondary">{{ c('uploadReplacesUrl') }}</p>
                        <p v-if="productForm.errors.image_upload" class="mt-1 text-sm text-error">{{ productForm.errors.image_upload }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('extraImageUrls') }}</label>
                        <textarea v-model="productForm.image_urls_text" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('oneUrlPerLine')"></textarea>
                        <p v-if="productForm.errors.image_urls_text" class="mt-1 text-sm text-error">{{ productForm.errors.image_urls_text }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('extraImagesUpload') }}</label>
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-product-gallery-uploads', $event)">
                        <p class="mt-1 text-xs text-secondary">{{ c('galleryLimit') }}</p>
                        <p v-if="productForm.errors.image_uploads" class="mt-1 text-sm text-error">{{ productForm.errors.image_uploads }}</p>
                    </div>
                </div>

                <label class="grid gap-1">
                    <span class="text-xs font-semibold uppercase text-secondary">{{ c('offerCategory') }}</span>
                    <select v-model="productForm.offer_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option value="physical_product">{{ c('physicalProduct') }}</option>
                        <option value="online_course">{{ c('onlineCourse') }}</option>
                        <option value="training_plan">{{ c('trainingPlan') }}</option>
                        <option value="camp">{{ c('camp') }}</option>
                    </select>
                    <span class="text-xs text-secondary">{{ c('offerCategoryHelp') }}</span>
                </label>
                <p v-if="productForm.errors.offer_type" class="text-sm text-error">{{ productForm.errors.offer_type }}</p>

                <label class="grid gap-1">
                    <span class="text-xs font-semibold uppercase text-secondary">{{ c('marketplaceCategory') }}</span>
                    <select v-model="productForm.category" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="category in sellerMarketplaceCategories" :key="category.category" :value="category.category">
                            {{ category.label }}
                        </option>
                    </select>
                    <span class="text-xs text-secondary">{{ c('marketplaceCategoryHelp') }}</span>
                </label>
                <p v-if="productForm.errors.category" class="text-sm text-error">{{ productForm.errors.category }}</p>

                <input v-model="productForm.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('sku')">
                <p v-if="productForm.errors.sku" class="text-sm text-error">{{ productForm.errors.sku }}</p>

                <select v-if="productForm.offer_type === 'physical_product'" v-model="productForm.product_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="single">{{ c('simpleProduct') }}</option>
                    <option value="variable">{{ c('variableProduct') }}</option>
                    <option value="digital">{{ c('digitalProduct') }}</option>
                </select>
                <p v-if="productForm.errors.product_type" class="text-sm text-error">{{ productForm.errors.product_type }}</p>

                <textarea
                    v-if="productForm.product_type === 'digital'"
                    v-model="productForm.digital_delivery_note"
                    rows="2"
                    class="rounded-lg border-border bg-inputBg text-sm text-primary"
                    :placeholder="c('digitalDelivery')"
                ></textarea>
                <p v-if="productForm.errors.digital_delivery_note" class="text-sm text-error">{{ productForm.errors.digital_delivery_note }}</p>

                <div v-if="isLearningOffer" class="grid gap-3 rounded-lg border border-border bg-bg p-3">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('linkCourse') }}</label>
                        <select v-model="productForm.learning_course_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="">{{ c('noCourse') }}</option>
                            <option v-for="course in learningCourses" :key="course.id" :value="course.id">
                                {{ course.title }} - {{ course.status }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-secondary">{{ c('courseAutoUnlock') }}</p>
                        <p v-if="productForm.errors.learning_course_id" class="mt-1 text-sm text-error">{{ productForm.errors.learning_course_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('courseOutline') }}</label>
                        <textarea v-model="productForm.course_outline_text" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('lessonPerLine')"></textarea>
                        <p v-if="productForm.errors.course_outline_text" class="mt-1 text-sm text-error">{{ productForm.errors.course_outline_text }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('learningGoals') }}</label>
                        <textarea v-model="productForm.learning_goals_text" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('goalPerLine')"></textarea>
                        <p v-if="productForm.errors.learning_goals_text" class="mt-1 text-sm text-error">{{ productForm.errors.learning_goals_text }}</p>
                    </div>
                    <label v-if="productForm.offer_type === 'training_plan'" class="flex items-center gap-2 text-sm text-primary">
                        <input v-model="productForm.coaching_enabled" type="checkbox" class="rounded border-border bg-inputBg">
                        {{ c('coachingEnabled') }}
                    </label>
                    <textarea
                        v-if="productForm.offer_type === 'training_plan'"
                        v-model="productForm.coach_feedback_instructions"
                        rows="3"
                        class="rounded-lg border-border bg-inputBg text-sm text-primary"
                        :placeholder="c('coachingInstructions')"
                    ></textarea>
                    <p v-if="productForm.errors.coach_feedback_instructions" class="text-sm text-error">{{ productForm.errors.coach_feedback_instructions }}</p>
                </div>

                <select v-model="productForm.tax_class" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option value="standard">{{ c('standardTax') }}</option>
                    <option value="reduced">{{ c('reducedTax') }}</option>
                    <option value="zero">{{ c('zeroTax') }}</option>
                </select>
                <p v-if="productForm.errors.tax_class" class="text-sm text-error">{{ productForm.errors.tax_class }}</p>
                <input v-model="productForm.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('price')">
                <p v-if="productForm.errors.price_cents" class="text-sm text-error">{{ productForm.errors.price_cents }}</p>

                <div class="rounded-lg border border-air-blue/30 bg-air-blue/10 p-3 text-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-air-blue">{{ c('commission') }}</p>
                            <p class="mt-1 font-semibold text-primary">
                                {{ selectedProductCommission.label }}: {{ selectedProductCommission.commission_percent }} %
                            </p>
                            <p class="mt-1 text-xs text-secondary">{{ c('commissionHelp') }}</p>
                        </div>
                        <div class="grid gap-1 text-right">
                            <p class="text-xs text-secondary">{{ c('commissionAtPrice') }}</p>
                            <p class="font-bold text-primary">{{ formatMoney(productCommissionPreviewCents) }}</p>
                            <p class="text-xs text-secondary">{{ c('payoutPreview') }} {{ formatMoney(productSellerPayoutPreviewCents) }}</p>
                        </div>
                    </div>
                </div>

                <label v-if="productForm.product_type !== 'digital'" class="flex items-center gap-2 text-sm text-primary">
                    <input v-model="productForm.is_shippable" type="checkbox" class="rounded border-border bg-inputBg">
                    {{ c('shippable') }}
                </label>
                <label v-if="productForm.product_type !== 'digital' && !productStockRequired" class="flex items-center gap-2 text-sm text-primary">
                    <input v-model="productForm.manages_stock" type="checkbox" class="rounded border-border bg-inputBg">
                    {{ c('manageStock') }}
                </label>
                <label v-if="productStockRequired" class="grid gap-1">
                    <span class="text-xs font-semibold uppercase text-secondary">{{ c('totalStock') }}</span>
                    <input v-model="productForm.stock_quantity" type="number" min="1" required class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('atLeastOne')">
                </label>
                <input v-else-if="productForm.manages_stock" v-model="productForm.stock_quantity" type="number" min="1" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('stock')">
                <p v-if="productForm.errors.stock_quantity" class="text-sm text-error">{{ productForm.errors.stock_quantity }}</p>

                <div v-if="productForm.manages_stock && productForm.product_type !== 'digital'" class="rounded-lg border border-border bg-bg p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">{{ c('countryInventory') }}</h3>
                            <p class="text-xs text-secondary">{{ c('countryInventoryHelp') }}</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-product-inventory-row')">
                            {{ c('addCountry') }}
                        </button>
                    </div>
                    <div class="mt-3 space-y-3">
                        <div v-for="(inventory, index) in productForm.inventories" :key="index" class="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-6">
                            <select v-model="inventory.country_code" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="country in inventoryCountries" :key="country" :value="country">{{ country }}</option>
                            </select>
                            <input v-model="inventory.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" :placeholder="c('stockShort')">
                            <input v-model="inventory.low_stock_threshold" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" :placeholder="c('lowStock')">
                            <input v-model="inventory.lead_time_days" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary md:col-span-1" :placeholder="c('leadTime')">
                            <input v-model="inventory.city" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('warehouseCity')">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-product-inventory-row', index)">
                                {{ c('remove') }}
                            </button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-secondary">{{ c('currentCountryStock') }} {{ inventoryTotalStock }}</p>
                    <p v-if="productForm.errors.inventories" class="mt-2 text-sm text-error">{{ productForm.errors.inventories }}</p>
                </div>

                <div class="rounded-lg border border-border bg-bg p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">{{ c('attributes') }}</h3>
                            <p class="text-xs text-secondary">{{ c('attributesHelp') }}</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-product-attribute-row')">
                            {{ c('addAttribute') }}
                        </button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="(row, index) in productAttributeRows" :key="index" class="grid gap-2">
                            <select v-model="row.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" @change="row.values = []">
                                <option value="">{{ c('chooseAttribute') }}</option>
                                <option v-for="preset in attributePresets" :key="preset.name" :value="preset.name">{{ preset.name }}</option>
                            </select>
                            <select v-model="row.values" multiple class="min-h-24 rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="value in presetValuesFor(row.name)" :key="value" :value="value">{{ value }}</option>
                            </select>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-product-attribute-row', index)">
                                {{ c('remove') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="productForm.product_type === 'variable'" class="rounded-lg border border-border bg-bg p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">{{ c('variants') }}</h3>
                            <p class="text-xs text-secondary">{{ c('variantsHelp') }}</p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-product-variant-row')">{{ c('addVariant') }}</button>
                    </div>
                    <div class="mt-3 space-y-3">
                        <div v-for="(variant, index) in productVariantRows" :key="index" class="grid gap-2 rounded-lg border border-border p-3">
                            <select
                                v-for="attribute in normalizeAttributeRows(productAttributeRows)"
                                :key="attribute.name"
                                v-model="variant.attributes[attribute.name]"
                                class="rounded-lg border-border bg-inputBg text-sm text-primary"
                            >
                                <option value="">{{ attribute.name }}</option>
                                <option v-for="value in attribute.values" :key="value" :value="value">{{ value }}</option>
                            </select>
                            <input v-model="variant.price_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('variantPrice')">
                            <input v-model="variant.stock_quantity" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('stockShort')">
                            <input v-model="variant.sku" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('sku')">
                            <input v-model="variant.image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('imageUrl')">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-secondary hover:text-primary" @click="emit('remove-product-variant-row', index)">{{ c('removeVariant') }}</button>
                        </div>
                    </div>
                </div>

                <textarea v-model="productForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('description')"></textarea>
                <p v-if="productForm.errors.description" class="text-sm text-error">{{ productForm.errors.description }}</p>
                <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ c('submit') }}</button>
            </div>
        </form>
    </div>
</template>






