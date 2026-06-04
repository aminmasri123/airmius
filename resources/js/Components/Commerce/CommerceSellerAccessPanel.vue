<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const emit = defineEmits([
    'create-product',
    'import-products',
    'set-product-import-file',
    'store-seller-application',
])

const props = defineProps({
    productImportForm: { type: Object, required: true },
    sellerApplication: { type: Object, default: null },
    sellerReadiness: { type: Object, default: null },
    sellerApplicationForm: { type: Object, required: true },
    sellerApplicationStatusLabel: { type: Function, required: true },
    sellerCanSell: { type: Boolean, default: false },
})

const page = usePage()
const locale = computed(() => page.props.locale || page.props.auth?.user?.language || (typeof document !== 'undefined' ? document.documentElement?.lang : null) || 'de')
const copy = {
    de: {
        readiness: 'Verkaufsbereitschaft',
        required: 'Pflicht',
        recommended: 'Empfohlen',
        complete: 'bereit',
        blocked: 'offen',
        profileHint: 'Profil, Regeln und Kontakt prüfen, damit Käufer Vertrauen haben.',
    },
    en: {
        readiness: 'Seller readiness',
        required: 'Required',
        recommended: 'Recommended',
        complete: 'ready',
        blocked: 'open',
        profileHint: 'Check profile, rules and contact details so buyers can trust the seller.',
    },
    fr: {
        readiness: 'Preparation vendeur',
        required: 'Obligatoire',
        recommended: 'Recommande',
        complete: 'pret',
        blocked: 'ouvert',
        profileHint: 'Verifie le profil, les regles et les contacts pour renforcer la confiance.',
    },
    ar: {
        readiness: 'جا�?ز�Sة ا�"بائع',
        required: '�.ط�"�^ب',
        recommended: '�.�^ص�? ب�?',
        complete: 'جا�?ز',
        blocked: '�.فت�^ح',
        profileHint: 'راجع ا�"�.�"ف �^ا�"�,�^اعد �^ب�Sا�?ات ا�"ت�^اص�" �"ز�Sادة ث�,ة ا�"�.شتر�S�?.',
    },
}
const t = (key) => (copy[locale.value]?.[key] || copy.de[key] || key)
const readiness = computed(() => props.sellerReadiness || props.sellerApplication?.readiness || null)
const readinessChecks = computed(() => (readiness.value?.checklist || []).slice(0, 6))
const readinessTone = computed(() => readiness.value?.can_approve
    ? 'border-success/30 bg-success/10 text-success'
    : 'border-warning/30 bg-warning/10 text-warning')
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-primary">Eigene Angebote verwalten</h2>
            <p class="mt-1 text-sm text-secondary">Erst nach einem freigegebenen Shop-Antrag kannst du Produkte im Marketplace verkaufen.</p>
        </div>
        <button
            v-if="sellerCanSell"
            type="button"
            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
            @click="emit('create-product')"
        >
            Produkt erstellen
        </button>
    </div>

    <div v-if="sellerCanSell" class="mt-5 grid gap-3 rounded-lg border border-border bg-bg p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-air-blue">Excel-Import</p>
                <h3 class="mt-1 font-semibold text-primary">Viele Angebote auf einmal hochladen</h3>
                <p class="mt-1 text-sm text-secondary">
                    Lade die Vorlage herunter. Preise bleiben in EUR, die Airmius-Provision wird in der Tabelle automatisch je Kategorie mitgerechnet.
                </p>
                <p class="mt-1 text-xs text-secondary">Bilder werden per Hauptbild-URL und Galerie-URLs importiert.</p>
            </div>
            <a :href="route('auth.commerce.products.import-template')" class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                Vorlage herunterladen
            </a>
        </div>
        <form class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="emit('import-products')">
            <input
                type="file"
                accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain"
                class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary"
                @change="emit('set-product-import-file', $event)"
            />
            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="productImportForm.processing || !productImportForm.import_file">
                Importieren
            </button>
        </form>
        <p v-if="productImportForm.errors.import_file" class="text-sm text-error">{{ productImportForm.errors.import_file }}</p>
    </div>

    <div v-if="!sellerCanSell" class="mt-5 rounded-lg border border-border bg-bg p-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-air-blue">Shop-Antrag</p>
                <h3 class="mt-1 font-semibold text-primary">Verkäufer-Zugang beantragen</h3>
                <p class="mt-1 text-sm text-secondary">Status: {{ sellerApplicationStatusLabel(sellerApplication?.status) }}</p>
                <p v-if="sellerApplication?.review_note" class="mt-2 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning">{{ sellerApplication.review_note }}</p>
            </div>
        </div>

        <div v-if="readiness" class="mt-4 rounded-xl border border-border bg-card p-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase text-air-blue">{{ t('readiness') }}</p>
                    <p class="mt-1 text-sm text-secondary">{{ t('profileHint') }}</p>
                </div>
                <span :class="['inline-flex w-fit items-center rounded-full border px-3 py-1 text-xs font-semibold', readinessTone]">
                    {{ readiness.score || 0 }}% · {{ readiness.can_approve ? t('complete') : t('blocked') }}
                </span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full bg-buttonPrimary" :style="{ width: `${Math.min(100, Math.max(0, readiness.score || 0))}%` }"></div>
            </div>
            <div class="mt-3 grid gap-2 text-xs text-secondary sm:grid-cols-2">
                <p>{{ t('required') }}: {{ readiness.required_done || 0 }}/{{ readiness.required_total || 0 }}</p>
                <p>{{ t('recommended') }}: {{ readiness.recommended_done || 0 }}/{{ readiness.recommended_total || 0 }}</p>
            </div>
            <div class="mt-3 grid gap-2">
                <div
                    v-for="check in readinessChecks"
                    :key="check.key"
                    class="flex items-start gap-2 rounded-lg border border-border bg-bg px-3 py-2 text-sm"
                >
                    <i :class="['las mt-0.5 text-base', check.done ? 'la-check-circle text-success' : 'la-circle text-warning']"></i>
                    <div class="min-w-0">
                        <p class="text-primary">{{ check.label }}</p>
                        <p class="text-xs text-secondary">{{ check.severity === 'required' ? t('required') : t('recommended') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <form class="mt-4 grid gap-3" @submit.prevent="emit('store-seller-application')">
            <select v-model="sellerApplicationForm.applicant_type" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                <option value="private">Privatperson</option>
                <option value="business">Gewerblicher Anbieter</option>
                <option value="club">Verein / Organisation</option>
            </select>
            <p v-if="sellerApplicationForm.errors.applicant_type" class="text-sm text-error">{{ sellerApplicationForm.errors.applicant_type }}</p>
            <input v-model="sellerApplicationForm.business_name" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Shop-/Firmenname optional" />
            <textarea v-model="sellerApplicationForm.notes" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" placeholder="Kurz beschreiben, was du verkaufen möchtest"></textarea>

            <div class="grid gap-2 rounded-lg border border-border bg-card p-3 text-sm text-primary">
                <label class="flex items-start gap-2">
                    <input v-model="sellerApplicationForm.rule_product_truth" type="checkbox" class="mt-1 rounded border-border bg-inputBg" />
                    <span>Ich bestätige, dass Preise, Bilder, Bestand und Beschreibung korrekt sind.</span>
                </label>
                <label class="flex items-start gap-2">
                    <input v-model="sellerApplicationForm.rule_rights" type="checkbox" class="mt-1 rounded border-border bg-inputBg" />
                    <span>Ich habe die Rechte an Bildern, Texten und angebotenen Leistungen.</span>
                </label>
                <label class="flex items-start gap-2">
                    <input v-model="sellerApplicationForm.rule_shipping_returns" type="checkbox" class="mt-1 rounded border-border bg-inputBg" />
                    <span>Ich beachte Versand-, Rückgabe- und Kundenservice-Pflichten.</span>
                </label>
                <label class="flex items-start gap-2">
                    <input v-model="sellerApplicationForm.rule_commission" type="checkbox" class="mt-1 rounded border-border bg-inputBg" />
                    <span>Ich akzeptiere Marketplace-Provisionen und Auszahlungsprüfung.</span>
                </label>
                <label class="flex items-start gap-2">
                    <input v-model="sellerApplicationForm.rule_data_privacy" type="checkbox" class="mt-1 rounded border-border bg-inputBg" />
                    <span>Ich gehe sorgsam mit Kundendaten um und nutze sie nur für die Bestellung.</span>
                </label>
            </div>
            <div v-if="Object.keys(sellerApplicationForm.errors).length" class="rounded-lg border border-error/40 bg-error/10 p-3 text-sm text-error">
                Bitte bestätige alle Regeln, bevor du den Shop-Antrag absendest.
            </div>
            <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="sellerApplicationForm.processing">
                Shop-Antrag senden
            </button>
        </form>
    </div>
</template>



