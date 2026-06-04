<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    product: { type: Object, required: true },
})

const { t, locale } = useI18n()

const copy = {
    de: {
        confidence: 'Kauf-Sicherheit',
        score: 'Score',
        excellent: 'Sehr stark',
        ready: 'Bereit',
        watch: 'Prüfen',
        risk: 'Risiko',
        seller: 'Anbieter geprüft',
        moderation: 'Angebot moderiert',
        stock: 'Bestand klar',
        delivery: 'Lieferung klar',
        returns: 'Rückgabe klar',
        reviews: 'Bewertungen',
        strong: 'Stark',
        signalReady: 'Okay',
        signalWatch: 'Prüfen',
        signalRisk: 'Kritisch',
        noSignals: 'Noch keine Kaufsignale verfügbar.',
    },
    en: {
        confidence: 'Purchase confidence',
        score: 'Score',
        excellent: 'Very strong',
        ready: 'Ready',
        watch: 'Check',
        risk: 'Risk',
        seller: 'Seller verified',
        moderation: 'Listing reviewed',
        stock: 'Stock clear',
        delivery: 'Delivery clear',
        returns: 'Returns clear',
        reviews: 'Reviews',
        strong: 'Strong',
        signalReady: 'Ready',
        signalWatch: 'Check',
        signalRisk: 'Critical',
        noSignals: 'No purchase signals yet.',
    },
    fr: {
        confidence: 'Confiance achat',
        score: 'Score',
        excellent: 'Tres fort',
        ready: 'Pret',
        watch: 'Verifier',
        risk: 'Risque',
        seller: 'Vendeur verifie',
        moderation: 'Offre verifiee',
        stock: 'Stock clair',
        delivery: 'Livraison claire',
        returns: 'Retour clair',
        reviews: 'Avis',
        strong: 'Fort',
        signalReady: 'Pret',
        signalWatch: 'Verifier',
        signalRisk: 'Critique',
        noSignals: 'Aucun signal d achat disponible.',
    },
    ar: {
        confidence: 'ث�,ة ا�"شراء',
        score: 'ا�"�?ت�Sجة',
        excellent: '�,�^�S جدا',
        ready: 'جا�?ز',
        watch: 'تح�,�,',
        risk: '�.خاطرة',
        seller: 'ا�"بائع �.�^ث�,',
        moderation: 'ا�"عرض ت�.ت �.راجعت�?',
        stock: 'ا�"�.خز�^�? �^اضح',
        delivery: 'ا�"تس�"�S�. �^اضح',
        returns: 'ا�"إرجاع �^اضح',
        reviews: 'ا�"ت�,�S�S�.ات',
        strong: '�,�^�S',
        signalReady: 'جا�?ز',
        signalWatch: 'تح�,�,',
        signalRisk: 'حرج',
        noSignals: '�"ا ت�^جد �.ؤشرات شراء بعد.',
    },
}

const language = computed(() => String(locale.value || 'de').slice(0, 2))
const labels = computed(() => copy[language.value] || copy.de)

const providerProfile = computed(() => props.product.provider_profile || {})
const providerName = computed(() => providerProfile.value.name || props.product.provider_name || t('Airmius Anbieter'))
const providerType = computed(() => providerProfile.value.type || props.product.provider_type || t('Marketplace Anbieter'))
const providerLocation = computed(() => providerProfile.value.location || t('Online'))
const buyerProtectionItems = computed(() => props.product.buyer_protection?.items || [])
const providerLocations = computed(() => providerProfile.value.locations?.slice(0, 3) || [])
const purchaseConfidence = computed(() => props.product.purchase_confidence || { score: 0, level: 'watch', signals: [] })
const confidenceSignals = computed(() => purchaseConfidence.value.signals || [])
const confidenceLevelLabel = computed(() => labels.value[purchaseConfidence.value.level] || labels.value.watch)
const signalTitle = (signal) => labels.value[signal.key] || signal.key
const signalStateLabel = (signal) => ({
    strong: labels.value.strong,
    ready: labels.value.signalReady,
    watch: labels.value.signalWatch,
    risk: labels.value.signalRisk,
}[signal.state] || signal.state)
const signalStateClass = (state) => ({
    strong: 'border-success/30 bg-success/10 text-success',
    ready: 'border-buttonPrimary/30 bg-buttonPrimary/10 text-buttonPrimary',
    watch: 'border-warning/30 bg-warning/10 text-warning',
    risk: 'border-error/30 bg-error/10 text-error',
}[state] || 'border-border bg-muted text-secondary')
</script>

<template>
    <div class="rounded border border-border bg-bg p-4">
        <div class="rounded border border-buttonPrimary/20 bg-card p-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-wide text-secondary">{{ labels.confidence }}</p>
                    <p class="mt-1 text-lg font-black text-primary">{{ confidenceLevelLabel }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-[11px] font-black uppercase tracking-wide text-secondary">{{ labels.score }}</p>
                    <p class="text-2xl font-black text-buttonPrimary">{{ purchaseConfidence.score || 0 }}</p>
                </div>
            </div>

            <div v-if="confidenceSignals.length" class="mt-3 grid grid-cols-2 gap-2">
                <div
                    v-for="signal in confidenceSignals"
                    :key="signal.key"
                    class="rounded border p-2"
                    :class="signalStateClass(signal.state)"
                >
                    <p class="truncate text-[11px] font-black uppercase tracking-wide">{{ signalTitle(signal) }}</p>
                    <p class="mt-1 text-xs font-bold">{{ signalStateLabel(signal) }}</p>
                </div>
            </div>
            <p v-else class="mt-3 text-xs text-secondary">{{ labels.noSignals }}</p>
        </div>

        <p class="mt-4 text-xs font-black uppercase tracking-wide text-secondary">{{ $t("Anbieter") }}</p>
        <div class="mt-3 flex items-center gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded bg-buttonPrimary/10 text-sm font-black text-buttonPrimary">
                <img v-if="providerProfile.logo_url" :src="providerProfile.logo_url" :alt="providerName" class="h-full w-full object-cover" />
                <span v-else>{{ providerProfile.initials || 'AM' }}</span>
            </span>
            <span class="min-w-0">
                <span class="flex items-center gap-1 text-base font-black text-primary">
                    {{ providerName }}
                    <i v-if="providerProfile.verified" class="las la-check-circle text-lg text-success"></i>
                </span>
                <span class="mt-1 block text-xs font-semibold text-secondary">{{ providerType }}</span>
                <span class="mt-1 block text-xs text-secondary">{{ providerLocation }}</span>
            </span>
        </div>

        <div v-if="product.trust_badges?.length" class="mt-3 flex flex-wrap gap-1">
            <span
                v-for="badge in product.trust_badges"
                :key="badge"
                class="rounded bg-muted px-2 py-1 text-[11px] font-bold text-secondary"
            >
                {{ badge }}
            </span>
        </div>

        <div v-if="buyerProtectionItems.length" class="mt-4 rounded border border-buttonPrimary/20 bg-buttonPrimary/5 p-3">
            <p class="text-xs font-black uppercase text-secondary">{{ $t('Vertrauen & Schutz') }}</p>
            <div class="mt-3 grid gap-2">
                <div
                    v-for="item in buyerProtectionItems"
                    :key="item.key"
                    class="flex gap-2 rounded bg-card p-2"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-buttonPrimary/10 text-buttonPrimary">
                        <i :class="[item.icon, 'text-base']"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-black text-primary">{{ $t(item.label) }}</span>
                        <span class="block text-[11px] leading-5 text-secondary">
                            {{ $t(item.description) }}
                            <template v-if="item.key === 'returns_available' && item.value">
                                · {{ $t('{count} Tage Rückgabe', { count: item.value }) }}
                            </template>
                            <template v-else-if="item.key === 'verified_reviews' && item.value">
                                · {{ $t('{count} verifizierte Käufe', { count: item.value }) }}
                            </template>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div v-if="providerLocations.length" class="mt-4 space-y-2 rounded border border-border bg-card p-3">
            <p class="text-xs font-black uppercase text-secondary">{{ $t('Abholung & Standorte') }}</p>
            <div v-for="location in providerLocations" :key="location.id" class="text-xs text-secondary">
                <p class="font-bold text-primary">{{ location.name }}</p>
                <p>{{ location.address }}</p>
                <p v-if="location.opening_hours">{{ location.opening_hours }}</p>
            </div>
        </div>

        <Link
            v-if="providerProfile.url"
            :href="providerProfile.url"
            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded border border-buttonPrimary/40 px-3 py-2 text-xs font-black text-buttonPrimary hover:bg-buttonPrimary hover:text-buttonTextPrimary"
        >
            {{ $t("Anbieterprofil ansehen") }}
            <i class="las la-arrow-right text-base"></i>
        </Link>
    </div>
</template>






