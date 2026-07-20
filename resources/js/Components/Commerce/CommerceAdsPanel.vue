<script setup>
import { Link } from '@inertiajs/vue3'
import AppButton from '@/Components/UI/AppButton.vue'
import AppLoadingState from '@/Components/UI/AppLoadingState.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps({
    campaignForm: { type: Object, required: true },
    campaignCreativeRows: { type: Array, default: () => [] },
    myCampaigns: { type: Array, default: () => [] },
    adPlacements: { type: Array, default: () => [] },
    campaignAdFormats: { type: Array, default: () => [] },
    selectedAdPlacement: { type: Object, default: () => ({}) },
    selectedAdFormat: { type: Object, default: () => ({}) },
    adProvider: { type: String, default: 'bank_transfer' },
    adAcceptedTerms: { type: Boolean, default: false },
    campaignCreateError: { type: String, default: '' },
    campaignCreateErrors: { type: Array, default: () => [] },
    campaignActionError: { type: String, default: '' },
    pageCampaignStatusError: { type: String, default: '' },
    moneyInputAttrs: { type: Object, default: () => ({}) },
    formatDateTime: { type: Function, required: true },
    formatPercent: { type: Function, required: true },
    ctr: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
    adPlacementLabel: { type: Function, required: true },
    adGroupAudienceSportsText: { type: Function, required: true },
    adGroupAudienceText: { type: Function, required: true },
    campaignRootCreatives: { type: Function, required: true },
})

const emit = defineEmits([
    'store-campaign',
    'set-campaign-creative-upload',
    'add-campaign-creative-row',
    'remove-campaign-creative-row',
    'update:adProvider',
    'update:adAcceptedTerms',
    'open-edit-campaign-modal',
    'open-ad-group-modal',
    'open-ad-creative-modal',
    'update-own-campaign-status',
    'delete-own-campaign',
])

const { locale } = useI18n()

const copy = {
    de: {
        step1: 'Schritt 1',
        createTitle: 'Kampagne erstellen',
        createHelp: 'Erstelle zuerst den Container. Anzeigegruppen, Zielgruppen, Anzeigen und Varianten kommen danach darunter.',
        campaignName: 'Kampagnenname',
        headline: 'Headline, max. 120 Zeichen',
        targetUrl: 'Ziel-URL',
        primaryText: 'Anzeigentext / Primary Text',
        description: 'Interne Beschreibung oder Kampagnenziel',
        objective: 'Ziel',
        traffic: 'Traffic',
        awareness: 'Reichweite',
        leads: 'Leads',
        sales: 'Sales',
        placement: 'Placement - wo erscheint die Ad?',
        creativeFormat: 'Creative Format und Bildmasse',
        formatNote: 'Placement entscheidet den Ort. Creative Format entscheidet nur Größe und Seitenverhaeltnis der Anzeige.',
        imageUrlOptional: 'Bild-URL optional',
        uploadImage: 'Bild hochladen',
        uploadHint: 'JPG, PNG oder WebP bis 8 MB. Empfohlen:',
        variants: 'A/B-Test Varianten',
        variantsHelp: 'Lege mindestens zwei Varianten mit unterschiedlicher Headline, Text oder Bild-URL an.',
        addVariant: 'Variante hinzufügen',
        variantA: 'Variante A',
        weight: 'Gewicht',
        active: 'Aktiv',
        variantHeadline: 'Headline dieser Variante',
        variantText: 'Anzeigentext dieser Variante',
        variantDescription: 'Beschreibung dieser Variante',
        optionalTargetUrl: 'Ziel-URL optional',
        optionalCta: 'CTA optional',
        variantImageUrl: 'Bild-URL dieser Variante',
        removeVariant: 'Variante entfernen',
        totalBudget: 'Gesamtbudget in EUR',
        dailyBudget: 'Tagesbudget in EUR',
        ageFrom: 'Alter von',
        ageTo: 'Alter bis',
        regions: 'Regionen, z. B. Berlin, NRW',
        interests: 'Interessen, z. B. Fußball, Fitness',
        cta: 'CTA, z. B. Jetzt ansehen',
        paymentType: 'Zahlungsart',
        bankTransfer: 'Überweisung',
        termsText: 'Ich akzeptiere AGB, Widerrufshinweise und nehme zur Kenntnis, dass die Kampagne erst nach Zahlung zur Prüfung eingereicht wird.',
        terms: 'AGB',
        withdrawal: 'Widerruf',
        saveCampaign: 'Kampagne speichern',
        saving: 'Speichert...',
        savingCampaign: 'Kampagne wird gespeichert...',
        ownCampaigns: 'eigene Kampagnen',
        myCampaigns: 'Meine Ads-Kampagnen',
        myCampaignsHelp: 'Status, Impressionen, Klicks und CTR deiner vorbereiteten oder aktiven Kampagnen.',
        campaign: 'Kampagne',
        status: 'Status',
        created: 'Erstellt',
        impressions: 'Impressionen',
        clicks: 'Klicks',
        ctrLabel: 'CTR',
        budget: 'Budget',
        actions: 'Aktionen',
        paymentOpen: 'Zahlung offen',
        paid: 'Bezahlt',
        edit: 'Bearbeiten',
        adGroup: 'Anzeigegruppe',
        pause: 'Pausieren',
        resume: 'Fortsetzen',
        toReview: 'Zur Prüfung',
        withdraw: 'Zurückziehen',
        delete: 'Löschen',
        adGroups: 'Anzeigegruppen',
        addGroup: 'Weitere Gruppe',
        adAndVariants: 'Anzeige + Varianten',
        sport: 'Sportart:',
        interestsLabel: 'Interessen:',
        age: 'Alter:',
        to: 'bis',
        gender: 'Geschlecht:',
        allGenders: 'all',
        placeRegion: 'Ort/Region:',
        zone: 'Zone:',
        dailyBudgetLabel: 'Tagesbudget:',
        adsVariants: 'Anzeigen / A-B Varianten',
        ad: 'Anzeige',
        weightLabel: 'Gewicht',
        views: 'Views',
        costs: 'Kosten',
        noCampaigns: 'Noch keine eigenen Ads-Kampagnen.',
    },
    en: {
        step1: 'Step 1',
        createTitle: 'Create campaign',
        createHelp: 'Create the container first. Ad groups, audiences, ads and variants come below it afterwards.',
        campaignName: 'Campaign name',
        headline: 'Headline, max. 120 characters',
        targetUrl: 'Target URL',
        primaryText: 'Ad text / primary text',
        description: 'Internal description or campaign goal',
        objective: 'Goal',
        traffic: 'Traffic',
        awareness: 'Reach',
        leads: 'Leads',
        sales: 'Sales',
        placement: 'Placement - where does the ad appear?',
        creativeFormat: 'Creative format and image size',
        formatNote: 'Placement decides the location. Creative format only decides size and aspect ratio.',
        imageUrlOptional: 'Image URL optional',
        uploadImage: 'Upload image',
        uploadHint: 'JPG, PNG or WebP up to 8 MB. Recommended:',
        variants: 'A/B test variants',
        variantsHelp: 'Create at least two variants with different headline, text or image URL.',
        addVariant: 'Add variant',
        variantA: 'Variant A',
        weight: 'Weight',
        active: 'Active',
        variantHeadline: 'Headline for this variant',
        variantText: 'Ad text for this variant',
        variantDescription: 'Description for this variant',
        optionalTargetUrl: 'Target URL optional',
        optionalCta: 'CTA optional',
        variantImageUrl: 'Image URL for this variant',
        removeVariant: 'Remove variant',
        totalBudget: 'Total budget in EUR',
        dailyBudget: 'Daily budget in EUR',
        ageFrom: 'Age from',
        ageTo: 'Age to',
        regions: 'Regions, e.g. Berlin, NRW',
        interests: 'Interests, e.g. football, fitness',
        cta: 'CTA, e.g. view now',
        paymentType: 'Payment type',
        bankTransfer: 'Bank transfer',
        termsText: 'I accept the terms, withdrawal information and understand that the campaign is submitted for review only after payment.',
        terms: 'Terms',
        withdrawal: 'Withdrawal',
        saveCampaign: 'Save campaign',
        saving: 'Saving...',
        savingCampaign: 'Saving campaign...',
        ownCampaigns: 'own campaigns',
        myCampaigns: 'My ads campaigns',
        myCampaignsHelp: 'Status, impressions, clicks and CTR of your prepared or active campaigns.',
        campaign: 'Campaign',
        status: 'Status',
        created: 'Created',
        impressions: 'Impressions',
        clicks: 'Clicks',
        ctrLabel: 'CTR',
        budget: 'Budget',
        actions: 'Actions',
        paymentOpen: 'Payment open',
        paid: 'Paid',
        edit: 'Edit',
        adGroup: 'Ad group',
        pause: 'Pause',
        resume: 'Resume',
        toReview: 'To review',
        withdraw: 'Withdraw',
        delete: 'Delete',
        adGroups: 'Ad groups',
        addGroup: 'Add group',
        adAndVariants: 'Ad + variants',
        sport: 'Sport:',
        interestsLabel: 'Interests:',
        age: 'Age:',
        to: 'to',
        gender: 'Gender:',
        allGenders: 'all',
        placeRegion: 'Place/region:',
        zone: 'Zone:',
        dailyBudgetLabel: 'Daily budget:',
        adsVariants: 'Ads / A-B variants',
        ad: 'Ad',
        weightLabel: 'Weight',
        views: 'Views',
        costs: 'Costs',
        noCampaigns: 'No own ads campaigns yet.',
    },
    fr: {},
    ar: {},
}

copy.fr = copy.en
copy.ar = copy.en

const labels = computed(() => copy[locale.value] || copy.de)
const c = (key) => labels.value[key] || copy.de[key] || key
</script>

<template>
    <section class="grid gap-6 xl:grid-cols-[28rem_minmax(0,1fr)]">
            <article class="surface-card p-5">
                <p class="text-xs font-semibold uppercase text-air-blue">{{ c('step1') }}</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">{{ c('createTitle') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ c('createHelp') }}</p>
                <form class="mt-4 grid gap-3" @submit.prevent="emit('store-campaign')">
                    <input v-model="campaignForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('campaignName')">
                    <input v-model="campaignForm.headline" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('headline')">
                    <input v-model="campaignForm.target_url" type="url" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('targetUrl')">
                    <textarea v-model="campaignForm.primary_text" rows="3" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('primaryText')"></textarea>
                    <textarea v-model="campaignForm.description" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('description')"></textarea>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase text-secondary">{{ c('objective') }}</label>
                            <select v-model="campaignForm.objective" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option value="traffic">{{ c('traffic') }}</option>
                                <option value="awareness">{{ c('awareness') }}</option>
                                <option value="leads">{{ c('leads') }}</option>
                                <option value="sales">{{ c('sales') }}</option>
                            </select>
                        </div>
                        <div class="hidden">
                            <label class="text-xs font-semibold uppercase text-secondary">{{ c('placement') }}</label>
                            <select v-model="campaignForm.placement" class="mt-1 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                                <option v-for="placement in adPlacements" :key="placement.key" :value="placement.key">{{ placement.label }}</option>
                            </select>
                            <p class="mt-1 text-xs text-secondary">{{ selectedAdPlacement.hint }}</p>
                        </div>
                    </div>
                    <div class="hidden rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('creativeFormat') }}</label>
                        <select v-model="campaignForm.creative_format" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option v-for="format in campaignAdFormats" :key="format.key" :value="format.key">
                                {{ format.label }} - {{ format.size }}
                            </option>
                        </select>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ selectedAdFormat.size }} · {{ selectedAdFormat.ratio }}</p>
                        <p class="text-xs text-secondary">{{ selectedAdFormat.hint }}</p>
                        <p class="mt-2 rounded-lg border border-border bg-card px-3 py-2 text-xs text-secondary">
                            {{ c('formatNote') }}
                        </p>
                    </div>
                    <input v-model="campaignForm.creative_image_url" type="url" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('imageUrlOptional')">
                    <div class="hidden rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('uploadImage') }}</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-campaign-creative-upload', $event)">
                        <p class="mt-1 text-xs text-secondary">{{ c('uploadHint') }} {{ selectedAdFormat.size }}.</p>
                    </div>
                    <div class="hidden rounded-lg border border-border bg-bg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary">{{ c('variants') }}</label>
                                <p class="mt-1 text-xs text-secondary">{{ c('variantsHelp') }}</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-campaign-creative-row')">
                                Variante hinzufügen
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(creative, index) in campaignCreativeRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-card p-3">
                                <div class="grid gap-2 sm:grid-cols-[1fr_6rem_auto]">
                                    <input v-model="creative.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('variantA')">
                                    <input v-model.number="creative.weight" type="number" min="1" max="1000" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('weight')">
                                    <label class="flex items-center gap-2 text-xs font-semibold text-primary">
                                        <input v-model="creative.is_active" type="checkbox" class="rounded border-border bg-inputBg">
                                        {{ c('active') }}
                                    </label>
                                </div>
                                <input v-model="creative.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('variantHeadline')">
                                <textarea v-model="creative.primary_text" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('variantText')"></textarea>
                                <textarea v-model="creative.description" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('variantDescription')"></textarea>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input v-model="creative.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('optionalTargetUrl')">
                                    <input v-model="creative.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('optionalCta')">
                                </div>
                                <input v-model="creative.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('variantImageUrl')">
                                <button v-if="campaignCreativeRows.length > 1" type="button" class="justify-self-start rounded-lg border border-warning/40 px-3 py-2 text-xs font-semibold text-warning" @click="emit('remove-campaign-creative-row', index)">
                                    Variante entfernen
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="campaignForm.budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('totalBudget')">
                        <input v-model="campaignForm.daily_budget_cents" v-bind="moneyInputAttrs" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('dailyBudget')">
                        <input v-model="campaignForm.starts_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <input v-model="campaignForm.ends_at" type="datetime-local" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                    </div>
                    <div class="hidden grid gap-3 sm:grid-cols-2">
                        <input v-model="campaignForm.audience_age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('ageFrom')">
                        <input v-model="campaignForm.audience_age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('ageTo')">
                    </div>
                    <input v-model="campaignForm.audience_locations" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('regions')">
                    <input v-model="campaignForm.audience_interests" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('interests')">
                    <input v-model="campaignForm.cta_label" class="hidden rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('cta')">
                    <div class="hidden rounded-lg border border-border bg-bg p-3">
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('paymentType') }}</label>
                        <select :value="adProvider" @change="emit('update:adProvider', $event.target.value)" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                            <option value="bank_transfer">{{ c('bankTransfer') }}</option>
                            <option value="stripe">Stripe</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                    <label class="hidden items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-secondary">
                        <input :checked="adAcceptedTerms" @change="emit('update:adAcceptedTerms', $event.target.checked)" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            {{ c('termsText') }}
                            <Link :href="route('terms.show')" class="text-air-blue underline">{{ c('terms') }}</Link>
                            <span> - </span>
                            <Link :href="route('legal.withdrawal')" class="text-air-blue underline">{{ c('withdrawal') }}</Link>
                        </span>
                    </label>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <AppButton type="submit" :loading="campaignForm.processing" :disabled="campaignForm.processing">
                            {{ campaignForm.processing ? c('saving') : c('saveCampaign') }}
                        </AppButton>
                        <AppLoadingState v-if="campaignForm.processing" :label="c('savingCampaign')" inline />
                    </div>
                    <div v-if="campaignCreateError || campaignCreateErrors.length" class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        <p v-if="campaignCreateError" class="font-semibold">{{ campaignCreateError }}</p>
                        <ul v-if="campaignCreateErrors.length" class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="error in campaignCreateErrors" :key="error">{{ error }}</li>
                        </ul>
                    </div>
                </form>
                <p class="mt-4 text-sm text-secondary">{{ myCampaigns.length }} {{ c('ownCampaigns') }}</p>
            </article>

            <article class="surface-card overflow-hidden">
                <div class="border-b border-border p-5">
                    <h2 class="text-lg font-semibold text-primary">{{ c('myCampaigns') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ c('myCampaignsHelp') }}</p>
                    <div v-if="campaignActionError || pageCampaignStatusError" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                        {{ campaignActionError || pageCampaignStatusError }}
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-border text-xs uppercase text-secondary">
                            <tr>
                                <th class="px-5 py-3">{{ c('campaign') }}</th>
                                <th class="px-5 py-3">{{ c('status') }}</th>
                                <th class="px-5 py-3">{{ c('created') }}</th>
                                <th class="px-5 py-3">{{ c('impressions') }}</th>
                                <th class="px-5 py-3">{{ c('clicks') }}</th>
                                <th class="px-5 py-3">{{ c('ctrLabel') }}</th>
                                <th class="px-5 py-3">{{ c('budget') }}</th>
                                <th class="px-5 py-3 text-right">{{ c('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template v-for="campaign in myCampaigns" :key="campaign.id">
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-primary">{{ campaign.headline || campaign.name }}</p>
                                    <p class="text-xs text-secondary">{{ campaign.target_url || '-' }}</p>
                                    <p class="text-xs text-secondary">{{ campaign.placement }} · {{ campaign.creative_format }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-secondary">{{ campaign.status }}</p>
                                    <p v-if="campaign.payment_pending" class="mt-1 text-xs font-semibold text-warning">{{ c('paymentOpen') }}</p>
                                    <p v-else-if="campaign.payment_completed" class="mt-1 text-xs font-semibold text-success">{{ c('paid') }}</p>
                                </td>
                                <td class="px-5 py-3 text-secondary">{{ formatDateTime(campaign.created_at) }}</td>
                                <td class="px-5 py-3 text-secondary">{{ campaign.impressions || 0 }}</td>
                                <td class="px-5 py-3 text-secondary">{{ campaign.clicks || 0 }}</td>
                                <td class="px-5 py-3 text-secondary">{{ formatPercent(ctr(campaign.clicks, campaign.impressions)) }}</td>
                                <td class="px-5 py-3 text-secondary">{{ formatMoney(campaign.budget_cents) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button
                                            v-if="campaign.status !== 'completed'"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="emit('open-edit-campaign-modal', campaign)"
                                        >
                                            {{ c('edit') }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                                            @click="emit('open-ad-group-modal', campaign)"
                                        >
                                            {{ c('adGroup') }}
                                        </button>
                                        <button
                                            v-if="['active', 'pending_review'].includes(campaign.status)"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="emit('update-own-campaign-status', campaign, 'paused')"
                                        >
                                            {{ c('pause') }}
                                        </button>
                                        <button
                                            v-if="campaign.status === 'paused' && campaign.payment_completed && campaign.reviewed_at"
                                            type="button"
                                            class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                                            @click="emit('update-own-campaign-status', campaign, 'active')"
                                        >
                                            {{ c('resume') }}
                                        </button>
                                        <button
                                            v-if="campaign.status === 'paused' && campaign.payment_completed && !campaign.reviewed_at"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="emit('update-own-campaign-status', campaign, 'pending_review')"
                                        >
                                            {{ c('toReview') }}
                                        </button>
                                        <button
                                            v-if="['pending_review', 'paused'].includes(campaign.status)"
                                            type="button"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                            @click="emit('update-own-campaign-status', campaign, 'draft')"
                                        >
                                            {{ c('withdraw') }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger"
                                            @click="emit('delete-own-campaign', campaign)"
                                        >
                                            {{ c('delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="campaign.groups?.length">
                                <td colspan="8" class="bg-bg px-5 py-3">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold uppercase text-secondary">{{ c('adGroups') }}</p>
                                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('open-ad-group-modal', campaign)">
                                            {{ c('addGroup') }}
                                        </button>
                                    </div>
                                    <div class="grid gap-2 lg:grid-cols-2">
                                        <div v-for="group in campaign.groups" :key="group.id" class="rounded-lg border border-border bg-card p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-semibold text-primary">{{ group.name }}</p>
                                                    <p class="text-xs text-secondary">{{ adPlacementLabel(group.placement) }}</p>
                                                </div>
                                                <div class="flex flex-col items-end gap-2">
                                                    <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ group.status }}</span>
                                                    <button type="button" class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary" @click="emit('open-ad-creative-modal', campaign, group)">
                                                        {{ c('adAndVariants') }}
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                                                <p><span class="text-secondary">{{ c('sport') }}</span> <span class="text-primary">{{ adGroupAudienceSportsText(group) || '-' }}</span></p>
                                                <p><span class="text-secondary">{{ c('interestsLabel') }}</span> <span class="text-primary">{{ adGroupAudienceText(group, 'interests') || '-' }}</span></p>
                                                <p><span class="text-secondary">{{ c('age') }}</span> <span class="text-primary">{{ group.audience?.age_min || '-' }} {{ c('to') }} {{ group.audience?.age_max || '-' }}</span></p>
                                                <p><span class="text-secondary">{{ c('gender') }}</span> <span class="text-primary">{{ group.audience?.gender || c('allGenders') }}</span></p>
                                                <p><span class="text-secondary">{{ c('placeRegion') }}</span> <span class="text-primary">{{ adGroupAudienceText(group, 'locations') || '-' }}</span></p>
                                                <p><span class="text-secondary">{{ c('zone') }}</span> <span class="text-primary">{{ adGroupAudienceText(group, 'zones') || '-' }}</span></p>
                                            </div>
                                            <p class="mt-3 text-xs text-secondary">{{ c('dailyBudgetLabel') }} {{ formatMoney(group.daily_budget_cents) }}</p>
                                            <div v-if="group.creatives?.length" class="mt-4 space-y-2">
                                                <p class="text-xs font-semibold uppercase text-secondary">{{ c('adsVariants') }}</p>
                                                <div v-for="creative in group.creatives" :key="creative.id" class="rounded-lg border border-border bg-bg p-3">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <p class="text-xs font-semibold uppercase text-air-blue">{{ creative.ad_name || c('ad') }}</p>
                                                            <p class="font-semibold text-primary">{{ creative.name }}</p>
                                                            <p class="text-xs text-secondary">{{ creative.headline || campaign.headline || campaign.name }}</p>
                                                        </div>
                                                        <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ c('weightLabel') }} {{ creative.weight }}</span>
                                                    </div>
                                                    <div class="mt-3 grid grid-cols-4 gap-2 text-xs">
                                                        <div>
                                                            <p class="text-secondary">{{ c('views') }}</p>
                                                            <p class="font-semibold text-primary">{{ creative.impressions || 0 }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-secondary">{{ c('clicks') }}</p>
                                                            <p class="font-semibold text-primary">{{ creative.clicks || 0 }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-secondary">{{ c('ctrLabel') }}</p>
                                                            <p class="font-semibold text-primary">{{ formatPercent(ctr(creative.clicks, creative.impressions)) }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="text-secondary">{{ c('costs') }}</p>
                                                            <p class="font-semibold text-primary">{{ formatMoney(creative.spent_cents) }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="campaignRootCreatives(campaign).length">
                                <td colspan="8" class="bg-bg px-5 py-3">
                                    <div class="grid gap-2 md:grid-cols-2">
                                        <div v-for="creative in campaignRootCreatives(campaign)" :key="creative.id" class="rounded-lg border border-border bg-card p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-semibold text-primary">{{ creative.name }}</p>
                                                    <p class="text-xs text-secondary">{{ creative.headline || campaign.headline || campaign.name }}</p>
                                                </div>
                                                <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ c('weightLabel') }} {{ creative.weight }}</span>
                                            </div>
                                            <div class="mt-3 grid grid-cols-4 gap-2 text-xs">
                                                <div>
                                                    <p class="text-secondary">{{ c('views') }}</p>
                                                    <p class="font-semibold text-primary">{{ creative.impressions || 0 }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-secondary">{{ c('clicks') }}</p>
                                                    <p class="font-semibold text-primary">{{ creative.clicks || 0 }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-secondary">{{ c('ctrLabel') }}</p>
                                                    <p class="font-semibold text-primary">{{ formatPercent(ctr(creative.clicks, creative.impressions)) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-secondary">{{ c('costs') }}</p>
                                                    <p class="font-semibold text-primary">{{ formatMoney(creative.spent_cents) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                    </table>
                    <p v-if="!myCampaigns.length" class="px-5 py-6 text-sm text-secondary">{{ c('noCampaigns') }}</p>
                </div>
            </article>
        </section>
</template>


