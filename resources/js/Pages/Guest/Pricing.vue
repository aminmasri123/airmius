<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    plans: { type: Array, default: () => [] },
    planGroups: { type: Object, default: () => ({}) },
})

const audiences = [
    {
        key: 'sportler',
        label: 'Sportler',
        icon: 'las la-running',
        title: 'Kostenlos starten, sportlich sichtbar werden.',
        description: 'Sportler bleiben im Kern kostenlos. Pro-Funktionen kommen später für Portfolio, Statistiken und mehr Sichtbarkeit.',
    },
    {
        key: 'trainer',
        label: 'Trainer',
        icon: 'las la-chalkboard-teacher',
        title: 'Training organisieren, Teams begleiten, Wissen verkaufen.',
        description: 'Trainer arbeiten kostenlos im Vereinsplan. Solo-Trainer bekommen später eigene Gruppen, Camps und Kursverkauf.',
    },
    {
        key: 'verein',
        label: 'Vereine',
        icon: 'las la-users',
        title: 'Vereinsverwaltung, Community und Kommunikation in einem System.',
        description: 'Die Vereinspläne sind der Hauptumsatz: Mitglieder, Teams, Beiträge, Rechnungen, Dateien und Sponsoren.',
    },
    {
        key: 'eltern',
        label: 'Eltern',
        icon: 'las la-shield-alt',
        title: 'Sicherheit für Minderjährige bleibt kostenlos.',
        description: 'Elternzustimmung, Widerruf und Kinderüberblick sind Vertrauensfunktionen und werden nicht separat monetarisiert.',
    },
    {
        key: 'sponsor',
        label: 'Sponsoren',
        icon: 'las la-bullhorn',
        title: 'Regionale Sichtbarkeit im Sportumfeld.',
        description: 'Sponsorprofile, Kampagnen und lokale Anzeigen kommen als B2B-Umsatzsäule, sobald genug Aktivität vorhanden ist.',
    },
    {
        key: 'anbieter',
        label: 'Anbieter',
        icon: 'las la-store',
        title: 'Marketplace für Produkte, Kurse und Services.',
        description: 'Anbieter können später Produkte, Camps, Kurse und Dienstleistungen rund um Sport verkaufen.',
    },
    {
        key: 'enterprise',
        label: 'Enterprise',
        icon: 'las la-network-wired',
        title: 'Individuelle Lösungen für Verbände und große Organisationen.',
        description: 'Enterprise ist für Mandantenfähigkeit, Migration, Schnittstellen, Schulung und SLA gedacht.',
    },
]

const selectedAudience = ref(audiences.find((audience) => props.planGroups[audience.key]?.length)?.key || 'verein')
const page = usePage()
const couponCode = ref('')

const currentAudience = computed(() => audiences.find((audience) => audience.key === selectedAudience.value) || audiences[2])
const visiblePlans = computed(() => props.planGroups[selectedAudience.value] || [])

const formatPrice = (cents) => {
    if (!cents) return '0 EUR'

    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    }).format(cents / 100)
}

const priceCaption = (plan) => {
    if (plan.slug === 'enterprise-verband') return 'individuell'
    if (!plan.monthly_price_cents) return 'kostenlos'

    return 'pro Monat'
}

const ctaLabel = (plan) => plan.cta_label || (plan.monthly_price_cents ? 'Plan testen' : 'Kostenlos starten')

const startCheckout = (plan, provider) => {
    if (!page.props.auth?.user) {
        router.visit(route('login'))
        return
    }

    router.post(route('subscription-checkout.store', plan.id), {
        provider,
        billing_interval: 'monthly',
        coupon_code: couponCode.value,
    })
}
</script>

<template>
    <SeoHead
        title="Airmius Preise für Sportler, Trainer, Vereine und Partner"
        description="Faire Airmius Pläne für Sportler, Trainer, Vereine, Eltern, Sponsoren, Anbieter und Verbände: von kostenlos bis professioneller Vereinsverwaltung."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" />
        <Subnav />

        <main class="px-4 mb-8 pt-36 md:pt-44">
            <section class="mx-auto max-w-6xl text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Preise</p>
                <h1 class="mx-auto mt-3 max-w-4xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                    Ein Preismodell für alle, die Sport organisieren, erleben oder unterstützen.
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-secondary">
                    Sportler und Eltern starten kostenlos. Vereine zahlen fair nach Größe. Trainer, Sponsoren und Anbieter bekommen eigene Wege, wenn ihre Funktionen wachsen.
                </p>
            </section>

            <section class="mx-auto mt-10 max-w-7xl">
                <div class="flex gap-2 overflow-x-auto rounded-lg border border-border bg-card p-2">
                    <button
                        v-for="audience in audiences"
                        :key="audience.key"
                        type="button"
                        class="flex shrink-0 items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition"
                        :class="selectedAudience === audience.key ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:bg-muted hover:text-primary'"
                        @click="selectedAudience = audience.key"
                    >
                        <i :class="audience.icon"></i>
                        <span>{{ audience.label }}</span>
                    </button>
                </div>
            </section>

            <section class="mx-auto mt-8 max-w-7xl">
                <div class="grid gap-6 lg:grid-cols-[0.75fr_1.25fr]">
                    <aside class="rounded-lg border border-border bg-card p-6">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ currentAudience.label }}</p>
                        <h2 class="mt-2 text-2xl font-bold text-primary">{{ currentAudience.title }}</h2>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">{{ currentAudience.description }}</p>
                        <div class="mt-6 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                            <p class="font-semibold text-primary">Produktregel</p>
                            <p class="mt-2">
                                Airmius bleibt für Sportler und Eltern niedrigschwellig. Bezahlt wird dort, wo echte Verwaltung, Reichweite, Support oder Umsatz entsteht.
                            </p>
                        </div>
                        <div class="mt-4 rounded-lg border border-border bg-bg p-4">
                            <label class="text-xs font-semibold uppercase text-secondary">Rabattcode</label>
                            <input
                                v-model="couponCode"
                                type="text"
                                class="mt-2 w-full rounded-lg border-border bg-inputBg text-sm uppercase text-primary"
                                placeholder="z. B. BETA50"
                            >
                            <p class="mt-2 text-xs text-secondary">Der Code wird beim Bezahlen automatisch berücksichtigt.</p>
                        </div>
                    </aside>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="plan in visiblePlans"
                            :key="plan.id"
                            class="surface-card flex flex-col p-5"
                            :class="['club', 'trainer-pro', 'sportler-free'].includes(plan.slug) ? 'border-air-blue/60 shadow-lg shadow-air-blue/10' : ''"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="text-xl font-bold text-primary">{{ plan.name }}</h3>
                                    <span v-if="plan.badge" class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">{{ plan.badge }}</span>
                                </div>
                                <p class="mt-3 min-h-16 text-sm leading-relaxed text-secondary">{{ plan.description }}</p>
                            </div>

                            <div class="mt-5">
                                <p class="text-3xl font-900 text-primary">{{ formatPrice(plan.monthly_price_cents) }}</p>
                                <p class="text-sm text-secondary">{{ priceCaption(plan) }}</p>
                                <p v-if="plan.yearly_price_cents" class="mt-1 text-xs text-secondary">
                                    {{ formatPrice(plan.yearly_price_cents) }} pro Jahr
                                </p>
                            </div>

                            <dl class="mt-5 grid gap-2 text-sm">
                                <div v-if="plan.member_limit" class="flex justify-between gap-3 border-b border-border pb-2">
                                    <dt class="text-secondary">Mitglieder</dt>
                                    <dd class="font-semibold text-primary">{{ plan.member_limit }}</dd>
                                </div>
                                <div v-if="plan.team_limit" class="flex justify-between gap-3 border-b border-border pb-2">
                                    <dt class="text-secondary">Teams</dt>
                                    <dd class="font-semibold text-primary">{{ plan.team_limit }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 border-b border-border pb-2">
                                    <dt class="text-secondary">Speicher</dt>
                                    <dd class="font-semibold text-primary">{{ plan.storage_gb }} GB</dd>
                                </div>
                            </dl>

                            <ul class="mt-5 flex-1 space-y-2 text-sm text-secondary">
                                <li v-for="feature in plan.features" :key="feature" class="flex gap-2">
                                    <i class="las la-check mt-0.5 text-air-green"></i>
                                    <span>{{ feature }}</span>
                                </li>
                            </ul>

                            <div class="mt-6 grid gap-2">
                                <Link
                                    v-if="!plan.monthly_price_cents"
                                    :href="canRegister ? route('register') : route('login')"
                                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-center text-sm font-semibold text-buttonTextPrimary"
                                >
                                    {{ ctaLabel(plan) }}
                                </Link>

                                <template v-else>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-center text-sm font-semibold text-buttonTextPrimary"
                                        @click="startCheckout(plan, 'stripe')"
                                    >
                                        Mit Stripe zahlen
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-border px-4 py-2 text-center text-sm font-semibold text-primary hover:bg-muted"
                                        @click="startCheckout(plan, 'paypal')"
                                    >
                                        Mit PayPal zahlen
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-border px-4 py-2 text-center text-sm font-semibold text-primary hover:bg-muted"
                                        @click="startCheckout(plan, 'bank_transfer')"
                                    >
                                        Per Überweisung zahlen
                                    </button>
                                </template>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
