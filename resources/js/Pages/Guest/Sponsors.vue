<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import AdSlot from '@/Components/Ads/AdSlot.vue'
import { useTheme } from '@/services/useTheme'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    sponsors: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, platform: 0, outfit_subscription: 0, club: 0 }) },
})

const query = ref('')
const scope = ref('all')
const { isDark } = useTheme()
const { t } = useI18n()

const filteredSponsors = computed(() => {
    const term = query.value.trim().toLowerCase()

    return props.sponsors.filter((sponsor) => {
        const matchesScope = scope.value === 'all' || sponsor.scope === scope.value
        const haystack = [
            sponsor.name,
            sponsor.club?.name,
            sponsor.website,
        ].filter(Boolean).join(' ').toLowerCase()

        return matchesScope && (!term || haystack.includes(term))
    })
})

const featuredSponsors = computed(() => filteredSponsors.value.slice(0, 3))
const remainingSponsors = computed(() => filteredSponsors.value.slice(3))
const benefits = [
    ['las la-map-marker-alt', 'Regionale Sichtbarkeit', 'Erreiche Mitglieder, Familien und Unterstützer direkt in ihrem Vereinsumfeld.'],
    ['las la-heart', 'Nachvollziehbare Wirkung', 'Zeige transparent, welche Ausrüstung, Jugendprojekte oder Veranstaltungen du ermöglichst.'],
    ['las la-handshake', 'Passende Partnerschaften', 'Finde Vereine und Engagements, die zu Marke, Region und Zielgruppe passen.'],
]
const opportunities = [
    ['las la-tshirt', 'Trikots & Ausrüstung', 'Teams konkret und sichtbar ausstatten.'],
    ['las la-child', 'Jugendförderung', 'Training, Camps und Teilhabe ermöglichen.'],
    ['las la-calendar-check', 'Events & Turniere', 'Lokale Sportmomente gemeinsam realisieren.'],
    ['las la-building', 'Langfristige Partnerschaft', 'Verein und Region dauerhaft begleiten.'],
]
const initials = (name) => (name || 'A').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase()
const sponsorLogoUrl = (sponsor) => isDark.value
    ? (sponsor.logo_dark_url || sponsor.logo_light_url || sponsor.logo_url)
    : (sponsor.logo_light_url || sponsor.logo_dark_url || sponsor.logo_url)

const scopeLabel = (sponsor) => ({
    platform: t('guest.sponsors.scope_platform'),
    outfit_subscription: t('guest.sponsors.scope_outfit'),
    club: t('guest.sponsors.scope_club'),
}[sponsor.scope] || t('guest.sponsors.scope_platform'))

const scopeDescription = (sponsor) => ({
    platform: t('guest.sponsors.scope_platform_text'),
    outfit_subscription: t('guest.sponsors.scope_outfit_text'),
    club: t('guest.sponsors.scope_club_text'),
}[sponsor.scope] || t('guest.sponsors.scope_platform_text'))
</script>

<template>
    <SeoHead
        :title="t('guest.sponsors.meta_title')"
        :description="t('guest.sponsors.meta_description')"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="px-4 pt-36 md:pt-44" tabindex="-1">
            <section class="relative mx-auto min-h-[32rem] max-w-6xl overflow-hidden rounded-xl border border-border bg-card sm:min-h-[36rem]">
                <img src="/images/sponsors/community-impact-hero.png" alt="Jugendliche Sportler, Trainer und eine Unternehmensvertreterin bei einem lokalen Vereinsprojekt" class="absolute inset-0 h-full w-full object-cover object-center" fetchpriority="high">
                <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/65 to-black/10"></div>
                <div class="relative flex min-h-[32rem] max-w-3xl flex-col justify-end p-6 sm:min-h-[36rem] sm:p-10 lg:p-14">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-300">Partnerschaften mit Wirkung</p>
                    <h1 class="mt-4 font-heading text-4xl font-900 leading-tight text-white sm:text-6xl">
                        Sport fördern. Region stärken. Marke sichtbar machen.
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-white/85 sm:text-lg">
                        Entdecke Vereine und Projekte, die zu deinem Unternehmen passen, und mache dein Engagement transparent und messbar.
                    </p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="#partnerschaften" class="inline-flex items-center justify-center rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover">
                            Partnerschaften entdecken
                        </a>
                        <Link
                            :href="route('auth.sponsor-applications.create')"
                            class="inline-flex items-center justify-center rounded-lg border border-white/50 bg-black/20 px-5 py-3 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-black/35"
                        >
                            {{ t('Sponsor werden') }}
                        </Link>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-10 max-w-6xl">
                <div class="grid gap-6 md:grid-cols-3">
                    <article v-for="benefit in benefits" :key="benefit[1]" class="border-t border-border pt-5">
                        <i :class="benefit[0]" class="text-3xl text-air-blue" aria-hidden="true"></i>
                        <h2 class="mt-3 text-lg font-bold text-primary">{{ benefit[1] }}</h2>
                        <p class="mt-2 text-sm leading-6 text-secondary">{{ benefit[2] }}</p>
                    </article>
                </div>
            </section>

            <section class="mx-auto mt-12 max-w-6xl border-y border-border py-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase text-air-blue">So kannst du unterstützen</p>
                        <h2 class="mt-2 text-2xl font-black text-primary">Engagement, das zu deinem Unternehmen passt</h2>
                    </div>
                    <p class="max-w-xl text-sm leading-6 text-secondary">Vom konkreten Jugendprojekt bis zur langfristigen Vereinspartnerschaft: Wähle einen klaren Einstieg und stimme die Leistung anschließend direkt ab.</p>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article v-for="item in opportunities" :key="item[1]" class="rounded-lg border border-border bg-card p-5">
                        <i :class="item[0]" class="text-2xl text-air-blue" aria-hidden="true"></i>
                        <h3 class="mt-4 font-bold text-primary">{{ item[1] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-secondary">{{ item[2] }}</p>
                    </article>
                </div>
            </section>

            <section id="partnerschaften" class="mx-auto mt-12 max-w-6xl rounded-xl border border-border bg-card p-4 scroll-mt-28">
                <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-center">
                    <label class="relative block">
                        <i class="las la-search absolute left-4 top-1/2 -translate-y-1/2 text-xl text-secondary"></i>
                        <input
                            v-model="query"
                            class="w-full rounded-lg border-border bg-inputBg py-3 pl-11 pr-4 text-primary"
                            :placeholder="t('guest.sponsors.search_placeholder')"
                            :aria-label="t('guest.sponsors.search_placeholder')"
                        >
                    </label>
                    <div class="grid grid-cols-4 rounded-lg border border-border bg-inputBg p-1 text-sm font-semibold">
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 transition"
                            :class="scope === 'all' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                            @click="scope = 'all'"
                        >
                            {{ t('guest.sponsors.all') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 transition"
                            :class="scope === 'platform' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                            @click="scope = 'platform'"
                        >
                            {{ t('guest.sponsors.airmius') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 transition"
                            :class="scope === 'outfit_subscription' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                            @click="scope = 'outfit_subscription'"
                        >
                            {{ t('guest.sponsors.outfit') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 transition"
                            :class="scope === 'club' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                            @click="scope = 'club'"
                        >
                            {{ t('guest.sponsors.clubs') }}
                        </button>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-8 max-w-6xl">
                <AdSlot placement="sponsor_section" variant="banner" :fallback="false" />
            </section>

            <section v-if="featuredSponsors.length" class="mx-auto mt-8 grid max-w-6xl gap-4 lg:grid-cols-3">
                <article
                    v-for="sponsor in featuredSponsors"
                    :key="sponsor.id"
                    class="group overflow-hidden rounded-xl border border-border bg-card shadow-xl shadow-black/10 transition hover:-translate-y-1 hover:border-borderHover"
                >
                    <div class="flex h-40 items-center justify-center bg-inputBg p-6">
                        <img v-if="sponsorLogoUrl(sponsor)" :src="sponsorLogoUrl(sponsor)" :alt="sponsor.name" loading="lazy" decoding="async" class="max-h-full max-w-full object-contain">
                        <div v-else class="flex h-20 w-20 items-center justify-center rounded-lg bg-buttonPrimary text-2xl font-black text-buttonTextPrimary">
                            {{ initials(sponsor.name) }}
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-air-blue/10 px-3 py-1 text-xs font-semibold text-air-blue">
                                {{ scopeLabel(sponsor) }}
                            </span>
                            <span v-if="sponsor.club" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                                {{ sponsor.club.name }}
                            </span>
                        </div>
                        <h2 class="mt-4 text-xl font-black text-primary">{{ sponsor.name }}</h2>
                        <p class="mt-2 text-sm text-secondary">
                            {{ scopeDescription(sponsor) }}
                        </p>
                        <a
                            v-if="sponsor.website"
                            :href="sponsor.website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-air-blue hover:text-borderHover"
                        >
                            {{ t('guest.sponsors.website') }}
                            <i class="las la-arrow-right transition group-hover:translate-x-1"></i>
                        </a>
                    </div>
                </article>
            </section>

            <section class="mx-auto mt-8 max-w-6xl">
                <div v-if="remainingSponsors.length" class="grid gap-3 md:grid-cols-2">
                    <article
                        v-for="sponsor in remainingSponsors"
                        :key="sponsor.id"
                        class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 transition hover:border-borderHover hover:bg-muted"
                    >
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-inputBg">
                            <img v-if="sponsorLogoUrl(sponsor)" :src="sponsorLogoUrl(sponsor)" :alt="sponsor.name" loading="lazy" decoding="async" class="h-full w-full object-contain p-2">
                            <span v-else class="text-lg font-black text-primary">{{ initials(sponsor.name) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-bold text-primary">{{ sponsor.name }}</h2>
                                <span class="rounded-full bg-inputBg px-2 py-0.5 text-xs font-semibold text-secondary">
                                    {{ sponsor.scope === 'club' ? sponsor.club?.name || t('Verein') : scopeLabel(sponsor) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-secondary">
                                {{ scopeLabel(sponsor) }}
                            </p>
                        </div>
                        <a
                            v-if="sponsor.website"
                            :href="sponsor.website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-inputBg"
                        >
                            <i class="las la-external-link-alt text-lg"></i>
                        </a>
                    </article>
                </div>

                <div v-if="!filteredSponsors.length" class="rounded-xl border border-border bg-card p-10 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg bg-inputBg">
                        <i class="las la-handshake text-3xl text-secondary"></i>
                    </div>
                    <h2 class="mt-4 text-xl font-bold text-primary">{{ t('guest.sponsors.empty_title') }}</h2>
                    <p class="mt-2 text-sm text-secondary">{{ t('guest.sponsors.empty_text') }}</p>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>


