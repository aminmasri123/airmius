<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps({
    campaignForm: { type: Object, required: true },
    campaignCreativeRows: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    adFormats: { type: Array, default: () => [] },
    selectedAdFormat: { type: Object, default: () => ({}) },
    adReport: { type: Object, default: () => ({}) },
    adPlacementReport: { type: Array, default: () => [] },
    adDiagnostics: { type: Array, default: () => [] },
    campaignStatusError: { type: String, default: '' },
    pageCampaignStatusError: { type: String, default: '' },
    moneyInputAttrs: { type: Object, default: () => ({}) },
    formatMoney: { type: Function, required: true },
    formatPercent: { type: Function, required: true },
    ctr: { type: Function, required: true },
    budgetUsage: { type: Function, required: true },
})

const emit = defineEmits([
    'store-campaign',
    'set-campaign-creative-upload',
    'add-campaign-creative-row',
    'remove-campaign-creative-row',
    'update-campaign-status',
])

const { locale } = useI18n()

const copy = {
    de: {
        createTitle: 'Ads-Kampagne erstellen',
        internalAd: 'Interne Airmius Ad',
        internalAdHelp: 'Nur Admins sehen diese Steuerung. Die Anzeige selbst wird normal ausgespielt.',
        forcePriority: 'Immer priorisieren',
        forcePriorityHelp: 'Wird vor bezahlten Ads gewählt, solange aktiv und im Zeitraum.',
        name: 'Name',
        headline: 'Headline',
        targetUrl: 'Ziel-URL',
        cta: 'CTA',
        traffic: 'Traffic',
        awareness: 'Reichweite',
        leads: 'Leads',
        sales: 'Sales',
        marketplaceCard: 'Marketplace Karte',
        feed: 'Feed',
        sidebar: 'Sidebar',
        sponsorSection: 'Sponsor-Bereich',
        imageFormat: 'Bildformat',
        imageSize: 'Empfohlene Bildmasse:',
        imageUrl: 'Bild-URL',
        variants: 'A/B-Test Varianten',
        variantsHelp: 'Lege mehrere Anzeigenvarianten mit eigener Headline, Text, Bild-URL und Gewichtung an.',
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
        draft: 'Entwurf',
        pendingPayment: 'Wartet auf Zahlung',
        pendingReview: 'Wartet auf Freigabe',
        paused: 'Pausiert',
        completed: 'Abgeschlossen',
        rejected: 'Abgelehnt',
        clicks: 'Klicks',
        regions: 'Regionen',
        interests: 'Interessen',
        excludedRegions: 'Regionen ausschließen',
        excludedInterests: 'Interessen ausschließen',
        devices: 'Geraete: desktop, mobile, tablet',
        languages: 'Sprachen: de, en, fr',
        hours: 'Zeitfenster: 08-22, 18:30-23:00',
        ageFrom: 'Alter von',
        ageTo: 'Alter bis',
        primaryText: 'Anzeigentext',
        description: 'Beschreibung',
        reviewNote: 'Review-Notiz / Ablehnungsgrund',
        save: 'Speichern',
        prepared: 'Kampagnen vorbereitet.',
        reportingEyebrow: 'Ads Reporting',
        performanceTitle: 'Kampagnenleistung',
        performanceHelp: 'Impressionen, Klicks, CTR und Budgetverbrauch für aktive Sponsor- und Ads-Kampagnen.',
        activeMetric: 'Aktiv',
        impressions: 'Impressionen',
        budget: 'Budget',
        placementPerformance: 'Placement Performance',
        placementTitle: 'Welche Flaechen Ergebnisse liefern',
        views: 'Views',
        costs: 'Kosten',
        diagnostics: 'Admin-Diagnose',
        diagnosticsTitle: 'Warum Ads ausgespielt oder gebremst werden',
        internal: 'Intern',
        prioritized: 'Priorisiert',
        today: 'Heute:',
        pacing: 'Pacing erlaubt ca.',
        campaign: 'Kampagne',
        status: 'Status',
        actions: 'Aktionen',
        airmiusInternal: 'Airmius intern',
        paymentOpen: 'Zahlung offen',
        noPayment: 'Keine Zahlung gefunden',
        approveAfterPayment: 'Erst nach Zahlung freigeben.',
        approve: 'Freigeben',
        pause: 'Pausieren',
        reject: 'Ablehnen',
        toReview: 'Zur Prüfung',
        noCampaigns: 'Noch keine Ads-Kampagnen.',
    },
    en: {
        createTitle: 'Create ads campaign',
        internalAd: 'Internal Airmius ad',
        internalAdHelp: 'Only admins see this control. The ad itself is delivered normally.',
        forcePriority: 'Always prioritize',
        forcePriorityHelp: 'Chosen before paid ads while active and within the time window.',
        name: 'Name',
        headline: 'Headline',
        targetUrl: 'Target URL',
        cta: 'CTA',
        traffic: 'Traffic',
        awareness: 'Reach',
        leads: 'Leads',
        sales: 'Sales',
        marketplaceCard: 'Marketplace card',
        feed: 'Feed',
        sidebar: 'Sidebar',
        sponsorSection: 'Sponsor section',
        imageFormat: 'Image format',
        imageSize: 'Recommended image size:',
        imageUrl: 'Image URL',
        variants: 'A/B test variants',
        variantsHelp: 'Create multiple ad variants with their own headline, text, image URL and weight.',
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
        draft: 'Draft',
        pendingPayment: 'Waiting for payment',
        pendingReview: 'Waiting for review',
        paused: 'Paused',
        completed: 'Completed',
        rejected: 'Rejected',
        clicks: 'Clicks',
        regions: 'Regions',
        interests: 'Interests',
        excludedRegions: 'Exclude regions',
        excludedInterests: 'Exclude interests',
        devices: 'Devices: desktop, mobile, tablet',
        languages: 'Languages: de, en, fr',
        hours: 'Time windows: 08-22, 18:30-23:00',
        ageFrom: 'Age from',
        ageTo: 'Age to',
        primaryText: 'Ad text',
        description: 'Description',
        reviewNote: 'Review note / rejection reason',
        save: 'Save',
        prepared: 'campaigns prepared.',
        reportingEyebrow: 'Ads reporting',
        performanceTitle: 'Campaign performance',
        performanceHelp: 'Impressions, clicks, CTR and budget usage for active sponsor and ads campaigns.',
        activeMetric: 'Active',
        impressions: 'Impressions',
        budget: 'Budget',
        placementPerformance: 'Placement performance',
        placementTitle: 'Which surfaces deliver results',
        views: 'Views',
        costs: 'Costs',
        diagnostics: 'Admin diagnostics',
        diagnosticsTitle: 'Why ads are delivered or throttled',
        internal: 'Internal',
        prioritized: 'Prioritized',
        today: 'Today:',
        pacing: 'Pacing allows approx.',
        campaign: 'Campaign',
        status: 'Status',
        actions: 'Actions',
        airmiusInternal: 'Airmius internal',
        paymentOpen: 'Payment open',
        noPayment: 'No payment found',
        approveAfterPayment: 'Approve only after payment.',
        approve: 'Approve',
        pause: 'Pause',
        reject: 'Reject',
        toReview: 'To review',
        noCampaigns: 'No ads campaigns yet.',
    },
    fr: {
        createTitle: 'Créer une campagne publicitaire',
        internalAd: 'Publicité interne Airmius',
        internalAdHelp: 'Seuls les admins voient ce contrôle. La publicité est diffusée normalement.',
        forcePriority: 'Toujours prioriser',
        forcePriorityHelp: 'Choisie avant les publicités payantes tant qu�?Telle est active et dans la période.',
        name: 'Nom',
        headline: 'Titre',
        targetUrl: 'URL cible',
        cta: 'CTA',
        traffic: 'Trafic',
        awareness: 'Portée',
        leads: 'Leads',
        sales: 'Ventes',
        marketplaceCard: 'Carte marketplace',
        feed: 'Fil',
        sidebar: 'Barre latérale',
        sponsorSection: 'Section sponsor',
        imageFormat: 'Format image',
        imageSize: 'Taille recommandée :',
        imageUrl: 'URL image',
        variants: 'Variantes A/B',
        variantsHelp: 'Crée plusieurs variantes avec leur propre titre, texte, URL image et pondération.',
        addVariant: 'Ajouter une variante',
        variantA: 'Variante A',
        weight: 'Poids',
        active: 'Active',
        variantHeadline: 'Titre de cette variante',
        variantText: 'Texte de cette variante',
        variantDescription: 'Description de cette variante',
        optionalTargetUrl: 'URL cible facultative',
        optionalCta: 'CTA facultatif',
        variantImageUrl: 'URL image de cette variante',
        removeVariant: 'Supprimer la variante',
        totalBudget: 'Budget total en EUR',
        dailyBudget: 'Budget quotidien en EUR',
        draft: 'Brouillon',
        pendingPayment: 'En attente de paiement',
        pendingReview: 'En attente de validation',
        paused: 'En pause',
        completed: 'Terminée',
        rejected: 'Refusée',
        clicks: 'Clics',
        regions: 'Régions',
        interests: 'Intérêts',
        excludedRegions: 'Exclure des régions',
        excludedInterests: 'Exclure des intérêts',
        devices: 'Appareils : desktop, mobile, tablette',
        languages: 'Langues : de, en, fr',
        hours: 'Créneaux : 08-22, 18:30-23:00',
        ageFrom: '�,ge min.',
        ageTo: '�,ge max.',
        primaryText: 'Texte publicitaire',
        description: 'Description',
        reviewNote: 'Note de revue / motif de refus',
        save: 'Enregistrer',
        prepared: 'campagnes préparées.',
        reportingEyebrow: 'Reporting publicitaire',
        performanceTitle: 'Performance des campagnes',
        performanceHelp: 'Impressions, clics, CTR et budget utilisé pour les campagnes sponsorisées actives.',
        activeMetric: 'Actives',
        impressions: 'Impressions',
        budget: 'Budget',
        placementPerformance: 'Performance des emplacements',
        placementTitle: 'Quelles surfaces génèrent des résultats',
        views: 'Vues',
        costs: 'Coûts',
        diagnostics: 'Diagnostic admin',
        diagnosticsTitle: 'Pourquoi les publicités sont diffusées ou ralenties',
        internal: 'Interne',
        prioritized: 'Priorisée',
        today: 'Aujourd�?Thui :',
        pacing: 'Pacing autorisé env.',
        campaign: 'Campagne',
        status: 'Statut',
        actions: 'Actions',
        airmiusInternal: 'Interne Airmius',
        paymentOpen: 'Paiement ouvert',
        noPayment: 'Aucun paiement trouvé',
        approveAfterPayment: 'Valider seulement après paiement.',
        approve: 'Valider',
        pause: 'Mettre en pause',
        reject: 'Refuser',
        toReview: '�? vérifier',
        noCampaigns: 'Aucune campagne publicitaire.',
    },
    ar: {
        createTitle: 'إ�?شاء ح�.�"ة إع�"ا�?�Sة',
        internalAd: 'إع�"ا�? داخ�"�S �.�? Airmius',
        internalAdHelp: '�Sر�? ا�"�.شرف�^�? �?ذا ا�"تح�f�. ف�,ط. �Sت�. عرض ا�"إع�"ا�? �?فس�? بش�f�" عاد�S.',
        forcePriority: 'إعطاء أ�^�"�^�Sة دائ�.ة',
        forcePriorityHelp: '�Sُختار �,ب�" ا�"إع�"ا�?ات ا�"�.دف�^عة طا�"�.ا أ�?�? �?شط �^ض�.�? ا�"فترة.',
        name: 'ا�"اس�.',
        headline: 'ا�"ع�?�^ا�?',
        targetUrl: 'رابط ا�"�?دف',
        cta: 'دع�^ة ا�"إجراء',
        traffic: 'ز�Sارات',
        awareness: '�^ص�^�"',
        leads: 'ع�.�"اء �.حت�.�"�^�?',
        sales: '�.ب�Sعات',
        marketplaceCard: 'بطا�,ة ا�"س�^�,',
        feed: 'ا�"خ�"اصة',
        sidebar: 'ا�"شر�Sط ا�"جا�?ب�S',
        sponsorSection: '�,س�. ا�"رعاة',
        imageFormat: 'ت�?س�S�, ا�"ص�^رة',
        imageSize: 'حج�. ا�"ص�^رة ا�"�.�^ص�? ب�?:',
        imageUrl: 'رابط ا�"ص�^رة',
        variants: '�.تغ�Sرات اختبار A/B',
        variantsHelp: 'أ�?شئ عدة �.تغ�Sرات بإعدادات ع�?�^ا�? �^�?ص �^ص�^رة �^�^ز�? �.خت�"فة.',
        addVariant: 'إضافة �.تغ�Sر',
        variantA: 'ا�"�.تغ�Sر A',
        weight: 'ا�"�^ز�?',
        active: '�?شط',
        variantHeadline: 'ع�?�^ا�? �?ذا ا�"�.تغ�Sر',
        variantText: '�?ص ا�"إع�"ا�? �"�?ذا ا�"�.تغ�Sر',
        variantDescription: '�^صف �?ذا ا�"�.تغ�Sر',
        optionalTargetUrl: 'رابط �?دف اخت�Sار�S',
        optionalCta: 'دع�^ة إجراء اخت�Sار�Sة',
        variantImageUrl: 'رابط ص�^رة �?ذا ا�"�.تغ�Sر',
        removeVariant: 'إزا�"ة ا�"�.تغ�Sر',
        totalBudget: 'ا�"�.�Sزا�?�Sة ا�"�f�"�Sة با�"�S�^ر�^',
        dailyBudget: 'ا�"�.�Sزا�?�Sة ا�"�S�^�.�Sة با�"�S�^ر�^',
        draft: '�.س�^دة',
        pendingPayment: 'با�?تظار ا�"دفع',
        pendingReview: 'با�?تظار ا�"�.راجعة',
        paused: '�.ت�^�,ف �.ؤ�,تا�<',
        completed: '�.�fت�.�"',
        rejected: '�.رف�^ض',
        clicks: 'ا�"�?�,رات',
        regions: 'ا�"�.�?اط�,',
        interests: 'ا�"ا�?ت�.ا�.ات',
        excludedRegions: 'استبعاد �.�?اط�,',
        excludedInterests: 'استبعاد ا�?ت�.ا�.ات',
        devices: 'ا�"أج�?زة: desktop, mobile, tablet',
        languages: 'ا�"�"غات: de, en, fr',
        hours: 'ا�"فترات: 08-22, 18:30-23:00',
        ageFrom: 'ا�"ع�.ر �.�?',
        ageTo: 'ا�"ع�.ر إ�"�?',
        primaryText: '�?ص ا�"إع�"ا�?',
        description: 'ا�"�^صف',
        reviewNote: '�.�"احظة ا�"�.راجعة / سبب ا�"رفض',
        save: 'حفظ',
        prepared: 'ح�.�"ات جا�?زة.',
        reportingEyebrow: 'ت�,ار�Sر ا�"إع�"ا�?ات',
        performanceTitle: 'أداء ا�"ح�.�"ات',
        performanceHelp: 'ا�"ظ�?�^ر �^ا�"�?�,رات �^CTR �^است�?�"ا�f ا�"�.�Sزا�?�Sة �"�"ح�.�"ات ا�"�?شطة.',
        activeMetric: '�?شط',
        impressions: 'ا�"ظ�?�^ر',
        budget: 'ا�"�.�Sزا�?�Sة',
        placementPerformance: 'أداء ا�"�.�^اضع',
        placementTitle: 'ا�"�.�^اضع ا�"ت�S تح�,�, ا�"�?تائج',
        views: 'ا�"�.شا�?دات',
        costs: 'ا�"ت�fا�"�Sف',
        diagnostics: 'تشخ�Sص ا�"�.شرف',
        diagnosticsTitle: '�"�.اذا تُعرض ا�"إع�"ا�?ات أ�^ �Sت�. ت�,�S�Sد�?ا',
        internal: 'داخ�"�S',
        prioritized: 'ذ�^ أ�^�"�^�Sة',
        today: 'ا�"�S�^�.:',
        pacing: 'ا�"حد ا�"ت�,ر�Sب�S ا�"�.س�.�^ح:',
        campaign: 'ا�"ح�.�"ة',
        status: 'ا�"حا�"ة',
        actions: 'ا�"إجراءات',
        airmiusInternal: 'داخ�"�S Airmius',
        paymentOpen: 'ا�"دفع �.فت�^ح',
        noPayment: '�"�. �Sت�. ا�"عث�^ر ع�"�? دفع',
        approveAfterPayment: 'ا�"�.�^اف�,ة بعد ا�"دفع ف�,ط.',
        approve: '�.�^اف�,ة',
        pause: 'إ�S�,اف �.ؤ�,ت',
        reject: 'رفض',
        toReview: 'إ�"�? ا�"�.راجعة',
        noCampaigns: '�"ا ت�^جد ح�.�"ات إع�"ا�?�Sة بعد.',
    },
}

const labels = computed(() => copy[locale.value] || copy.de)
const c = (key) => labels.value[key] || copy.de[key] || key
</script>

<template>
    <section class="surface-card p-5">
        <h2 class="text-lg font-semibold text-primary">{{ c('createTitle') }}</h2>
        <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="emit('store-campaign')">
            <div class="md:col-span-2 grid gap-3 rounded-lg border border-air-blue/30 bg-air-blue/10 p-3 sm:grid-cols-2">
                <label class="flex items-start gap-3 text-sm text-primary">
                    <input v-model="campaignForm.is_internal" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                    <span>
                        <span class="block font-semibold">{{ c('internalAd') }}</span>
                        <span class="text-xs text-secondary">{{ c('internalAdHelp') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 text-sm text-primary" :class="{ 'opacity-50': !campaignForm.is_internal }">
                    <input v-model="campaignForm.force_priority" type="checkbox" class="mt-1 rounded border-border bg-inputBg" :disabled="!campaignForm.is_internal">
                    <span>
                        <span class="block font-semibold">{{ c('forcePriority') }}</span>
                        <span class="text-xs text-secondary">{{ c('forcePriorityHelp') }}</span>
                    </span>
                </label>
            </div>
            <input v-model="campaignForm.name" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('name')">
            <input v-model="campaignForm.headline" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('headline')">
            <input v-model="campaignForm.target_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('targetUrl')">
            <input v-model="campaignForm.cta_label" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('cta')">
            <select v-model="campaignForm.objective" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                <option value="traffic">{{ c('traffic') }}</option>
                <option value="awareness">{{ c('awareness') }}</option>
                <option value="leads">{{ c('leads') }}</option>
                <option value="sales">{{ c('sales') }}</option>
            </select>
            <select v-model="campaignForm.placement" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                <option value="marketplace_card">{{ c('marketplaceCard') }}</option>
                <option value="feed">{{ c('feed') }}</option>
                <option value="sidebar">{{ c('sidebar') }}</option>
                <option value="sponsor_section">{{ c('sponsorSection') }}</option>
            </select>
            <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                <label class="text-xs font-semibold uppercase text-secondary">{{ c('imageFormat') }}</label>
                <select v-model="campaignForm.creative_format" class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm text-primary">
                    <option v-for="format in adFormats" :key="format.key" :value="format.key">{{ format.label }} - {{ format.size }}</option>
                </select>
                <p class="mt-2 text-xs text-secondary">{{ c('imageSize') }} {{ selectedAdFormat.size }}</p>
            </div>
            <input v-model="campaignForm.creative_image_url" type="url" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('imageUrl')">
            <input type="file" accept="image/jpeg,image/png,image/webp" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary file:mr-3 file:rounded file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="emit('set-campaign-creative-upload', $event)">
            <div class="md:col-span-2 rounded-lg border border-border bg-bg p-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <label class="text-xs font-semibold uppercase text-secondary">{{ c('variants') }}</label>
                        <p class="mt-1 text-xs text-secondary">{{ c('variantsHelp') }}</p>
                    </div>
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="emit('add-campaign-creative-row')">
                        {{ c('addVariant') }}
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
                            {{ c('removeVariant') }}
                        </button>
                    </div>
                </div>
            </div>
            <input v-model="campaignForm.budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('totalBudget')">
            <input v-model="campaignForm.daily_budget_cents" v-bind="moneyInputAttrs" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('dailyBudget')">
            <select v-model="campaignForm.status" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                <option value="draft">{{ c('draft') }}</option>
                <option value="pending_payment">{{ c('pendingPayment') }}</option>
                <option value="pending_review">{{ c('pendingReview') }}</option>
                <option value="active">{{ c('active') }}</option>
                <option value="paused">{{ c('paused') }}</option>
                <option value="completed">{{ c('completed') }}</option>
                <option value="rejected">{{ c('rejected') }}</option>
            </select>
            <input v-model="campaignForm.clicks" type="number" min="0" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('clicks')">
            <input v-model="campaignForm.audience_locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('regions')">
            <input v-model="campaignForm.audience_interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('interests')">
            <input v-model="campaignForm.audience_excluded_locations" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('excludedRegions')">
            <input v-model="campaignForm.audience_excluded_interests" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('excludedInterests')">
            <input v-model="campaignForm.audience_devices" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('devices')">
            <input v-model="campaignForm.audience_languages" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('languages')">
            <input v-model="campaignForm.audience_hours" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('hours')">
            <input v-model="campaignForm.audience_age_min" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('ageFrom')">
            <input v-model="campaignForm.audience_age_max" type="number" min="13" max="100" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('ageTo')">
            <textarea v-model="campaignForm.primary_text" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('primaryText')"></textarea>
            <textarea v-model="campaignForm.description" rows="3" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('description')"></textarea>
            <textarea v-model="campaignForm.review_note" rows="2" class="md:col-span-2 rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="c('reviewNote')"></textarea>
            <button class="md:col-span-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">{{ c('save') }}</button>
        </form>
        <p class="mt-4 text-sm text-secondary">{{ campaigns.length }} {{ c('prepared') }}</p>
    </section>

    <section class="surface-card overflow-hidden">
        <div class="border-b border-border p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ c('reportingEyebrow') }}</p>
            <h2 class="mt-1 text-lg font-semibold text-primary">{{ c('performanceTitle') }}</h2>
            <p class="mt-1 text-sm text-secondary">{{ c('performanceHelp') }}</p>
        </div>

        <div class="grid gap-4 border-b border-border p-5 md:grid-cols-5">
            <div class="rounded-lg border border-border bg-bg p-4">
                <p class="text-xs uppercase text-secondary">{{ c('activeMetric') }}</p>
                <p class="mt-2 text-xl font-bold text-primary">{{ adReport.active || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-4">
                <p class="text-xs uppercase text-secondary">{{ c('impressions') }}</p>
                <p class="mt-2 text-xl font-bold text-primary">{{ adReport.impressions || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-4">
                <p class="text-xs uppercase text-secondary">{{ c('clicks') }}</p>
                <p class="mt-2 text-xl font-bold text-primary">{{ adReport.clicks || 0 }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-4">
                <p class="text-xs uppercase text-secondary">CTR</p>
                <p class="mt-2 text-xl font-bold text-primary">{{ formatPercent(ctr(adReport.clicks, adReport.impressions)) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-bg p-4">
                <p class="text-xs uppercase text-secondary">{{ c('budget') }}</p>
                <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(adReport.budget_cents) }}</p>
            </div>
        </div>

        <div v-if="adPlacementReport.length" class="border-b border-border p-5">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ c('placementPerformance') }}</p>
                <h3 class="font-semibold text-primary">{{ c('placementTitle') }}</h3>
            </div>
            <div class="mt-3 grid gap-3 lg:grid-cols-4">
                <article v-for="row in adPlacementReport" :key="row.placement" class="rounded-lg border border-border bg-bg p-4">
                    <p class="text-sm font-semibold text-primary">{{ row.placement }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-secondary">
                        <span>{{ c('views') }}</span>
                        <strong class="text-right text-primary">{{ row.impressions }}</strong>
                        <span>{{ c('clicks') }}</span>
                        <strong class="text-right text-primary">{{ row.clicks }}</strong>
                        <span>{{ c('leads') }}</span>
                        <strong class="text-right text-primary">{{ row.leads }}</strong>
                        <span>{{ c('sales') }}</span>
                        <strong class="text-right text-primary">{{ row.sales }}</strong>
                        <span>CTR</span>
                        <strong class="text-right text-primary">{{ row.ctr }}%</strong>
                        <span>{{ c('costs') }}</span>
                        <strong class="text-right text-primary">{{ formatMoney(row.cost_cents) }}</strong>
                    </div>
                </article>
            </div>
        </div>

        <div v-if="adDiagnostics.length" class="border-b border-border p-5">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ c('diagnostics') }}</p>
                <h3 class="font-semibold text-primary">{{ c('diagnosticsTitle') }}</h3>
            </div>
            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                <article v-for="diagnostic in adDiagnostics.slice(0, 8)" :key="diagnostic.id" class="rounded-lg border border-border bg-bg p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-semibold text-primary">{{ diagnostic.name }}</p>
                            <p class="text-xs text-secondary">{{ diagnostic.placement }} - {{ diagnostic.status }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span v-if="diagnostic.is_internal" class="rounded-full border border-air-blue/40 bg-air-blue/10 px-2 py-0.5 text-[11px] font-semibold text-air-blue">{{ c('internal') }}</span>
                            <span v-if="diagnostic.force_priority" class="rounded-full border border-warning/40 bg-warning/10 px-2 py-0.5 text-[11px] font-semibold text-warning">{{ c('prioritized') }}</span>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <span v-for="reason in diagnostic.reasons" :key="reason" class="rounded-full bg-card px-2 py-1 text-secondary">
                            {{ reason }}
                        </span>
                    </div>
                    <p v-if="diagnostic.daily_budget_cents" class="mt-3 text-xs text-secondary">
                        {{ c('today') }} {{ formatMoney(diagnostic.today_spent_cents) }} / {{ formatMoney(diagnostic.daily_budget_cents) }}
                        <span v-if="diagnostic.allowed_spend_cents"> - {{ c('pacing') }} {{ formatMoney(diagnostic.allowed_spend_cents) }}</span>
                    </p>
                </article>
            </div>
        </div>

        <div
            v-if="campaignStatusError || pageCampaignStatusError"
            class="mx-5 mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"
        >
            {{ campaignStatusError || pageCampaignStatusError }}
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-border text-xs uppercase text-secondary">
                    <tr>
                        <th class="px-5 py-3">{{ c('campaign') }}</th>
                        <th class="px-5 py-3">{{ c('status') }}</th>
                        <th class="px-5 py-3">{{ c('impressions') }}</th>
                        <th class="px-5 py-3">{{ c('clicks') }}</th>
                        <th class="px-5 py-3">CTR</th>
                        <th class="px-5 py-3">{{ c('budget') }}</th>
                        <th class="px-5 py-3 text-right">{{ c('actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <template v-for="campaign in campaigns" :key="campaign.id">
                        <tr>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-primary">{{ campaign.name }}</p>
                                    <span v-if="campaign.is_internal" class="rounded-full border border-air-blue/40 bg-air-blue/10 px-2 py-0.5 text-[11px] font-semibold text-air-blue">{{ c('internal') }}</span>
                                    <span v-if="campaign.force_priority" class="rounded-full border border-warning/40 bg-warning/10 px-2 py-0.5 text-[11px] font-semibold text-warning">{{ c('prioritized') }}</span>
                                </div>
                                <p class="text-xs text-secondary">{{ campaign.target_url || '-' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-secondary">{{ campaign.status }}</p>
                                <p v-if="campaign.is_internal" class="mt-1 text-xs font-semibold text-air-blue">
                                    {{ c('airmiusInternal') }}
                                </p>
                                <p v-else-if="campaign.user_id && !campaign.payment_completed" class="mt-1 text-xs font-semibold text-warning">
                                    {{ campaign.payment_pending ? c('paymentOpen') : c('noPayment') }}
                                </p>
                            </td>
                            <td class="px-5 py-3 text-secondary">{{ campaign.impressions || 0 }}</td>
                            <td class="px-5 py-3 text-secondary">{{ campaign.clicks || 0 }}</td>
                            <td class="px-5 py-3 text-secondary">{{ formatPercent(ctr(campaign.clicks, campaign.impressions)) }}</td>
                            <td class="px-5 py-3">
                                <p class="text-secondary">{{ formatMoney(campaign.spent_cents) }} / {{ formatMoney(campaign.budget_cents) }}</p>
                                <div class="mt-2 h-2 rounded-full bg-muted">
                                    <div class="h-2 rounded-full bg-air-blue" :style="{ width: `${budgetUsage(campaign.spent_cents, campaign.budget_cents)}%` }"></div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <p v-if="campaign.user_id && !campaign.payment_completed" class="w-full text-xs text-warning">
                                        {{ c('approveAfterPayment') }}
                                    </p>
                                    <button
                                        v-if="['pending_review', 'paused'].includes(campaign.status)"
                                        type="button"
                                        class="rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary"
                                        @click="emit('update-campaign-status', campaign, 'active')"
                                    >
                                        {{ c('approve') }}
                                    </button>
                                    <button
                                        v-if="campaign.status === 'active'"
                                        type="button"
                                        class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                        @click="emit('update-campaign-status', campaign, 'paused')"
                                    >
                                        {{ c('pause') }}
                                    </button>
                                    <button
                                        v-if="['draft', 'pending_payment', 'pending_review', 'paused', 'active'].includes(campaign.status)"
                                        type="button"
                                        class="rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger"
                                        @click="emit('update-campaign-status', campaign, 'rejected')"
                                    >
                                        {{ c('reject') }}
                                    </button>
                                    <button
                                        v-if="campaign.status === 'rejected'"
                                        type="button"
                                        class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary"
                                        @click="emit('update-campaign-status', campaign, 'pending_review')"
                                    >
                                        {{ c('toReview') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="campaign.creatives?.length">
                            <td colspan="7" class="bg-bg px-5 py-3">
                                <div class="grid gap-2 md:grid-cols-2">
                                    <div v-for="creative in campaign.creatives" :key="creative.id" class="rounded-lg border border-border bg-card p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-semibold text-primary">{{ creative.name }}</p>
                                                <p class="text-xs text-secondary">{{ creative.headline || campaign.headline || campaign.name }}</p>
                                            </div>
                                            <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">{{ c('weight') }} {{ creative.weight }}</span>
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
                                                <p class="text-secondary">CTR</p>
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
            <p v-if="!campaigns.length" class="px-5 py-6 text-sm text-secondary">{{ c('noCampaigns') }}</p>
        </div>
    </section>
</template>



