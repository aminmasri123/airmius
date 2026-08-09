<script setup>
import Footer from '@/Components/Guest/Footer.vue'
import Nav from '@/Components/Guest/Nav.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    kind: { type: String, required: true },
    entity: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    membership_types: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
})

const { t, te, locale } = useI18n()
const localeCode = computed(() => ({ de: 'de-DE', en: 'en-US', fr: 'fr-FR', ar: 'ar-EG' })[locale.value] || 'de-DE')
const title = computed(() => props.kind === 'city'
    ? t('public_discovery.city.title', { city: props.entity.name })
    : props.entity.name || props.entity.title)
const subtitle = computed(() => t(`public_discovery.${props.kind}.subtitle`, {
    city: props.entity.city || props.entity.location_city || props.entity.name,
    sport: sportLabel(props.entity.sport_type || props.entity.name),
}))
const backRoute = computed(() => ({
    club: route('guest.vereine'),
    event: route('guest.events'),
    sport: route('guest.sports'),
    city: route('guest.cities'),
})[props.kind])
const statsOrder = computed(() => props.kind === 'club'
    ? ['teams', 'events', 'membership_types']
    : ['clubs', 'teams', 'events'])

const sportLabel = (value) => {
    if (!value) return t('public_discovery.common.sport_open')

    const key = `sports.${value}`
    return te(key) ? t(key) : value
}

const typeLabel = (value) => {
    const key = `events.types.${value}`
    return te(key) ? t(key) : value
}

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(localeCode.value, {
        dateStyle: 'full',
        timeStyle: 'short',
    }).format(new Date(value))
    : t('public_discovery.common.not_specified')

const formatMoney = (value) => new Intl.NumberFormat(localeCode.value, {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value || 0))

const intervalLabel = (interval) => t(`public_discovery.intervals.${interval || 'none'}`)
</script>

<template>
    <SeoHead
        :title="seo.title || title"
        :description="seo.description || subtitle"
        :canonical="seo.canonical"
        :image="seo.image"
        :type="seo.type || 'website'"
        :schema="seo.schema"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="px-4 pb-16 pt-36 md:pt-44" tabindex="-1">
            <section class="mx-auto max-w-6xl overflow-hidden rounded-3xl border border-border bg-card">
                <div
                    v-if="kind === 'club' && entity.cover_image_url"
                    class="h-48 bg-cover bg-center md:h-64"
                    :style="{ backgroundImage: `linear-gradient(to top, rgba(7,16,29,.78), rgba(7,16,29,.12)), url('${entity.cover_image_url}')` }"
                    role="img"
                    :aria-label="entity.name"
                ></div>
                <div class="p-6 md:p-9">
                    <Link :href="backRoute" class="inline-flex items-center gap-2 text-sm font-bold text-buttonPrimary hover:underline">
                        <i class="las la-arrow-left rtl:rotate-180" aria-hidden="true"></i>
                        {{ t('public_discovery.common.back') }}
                    </Link>
                    <div class="mt-5 flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                        <div class="flex min-w-0 items-start gap-4">
                            <img v-if="kind === 'club' && entity.logo_url" :src="entity.logo_url" :alt="entity.name" decoding="async" fetchpriority="high" class="h-20 w-20 rounded-2xl border border-border bg-bg object-cover">
                            <span v-else class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-buttonPrimary/10 text-buttonPrimary">
                                <i :class="kind === 'event' ? 'las la-calendar-alt' : (kind === 'city' ? 'las la-city' : 'las la-running')" class="text-3xl" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-buttonPrimary">{{ t(`public_discovery.${kind}.eyebrow`) }}</p>
                                <h1 class="mt-2 break-words font-heading text-3xl font-black leading-tight sm:text-5xl">{{ title }}</h1>
                                <p class="mt-3 max-w-3xl text-sm leading-7 text-secondary sm:text-base">{{ subtitle }}</p>
                            </div>
                        </div>
                        <span v-if="kind === 'club' && entity.is_official" class="w-fit rounded-full bg-success/15 px-3 py-1.5 text-xs font-black text-success">
                            {{ t('public_discovery.common.verified') }}
                        </span>
                    </div>

                    <div v-if="kind !== 'event'" class="mt-7 grid gap-3 sm:grid-cols-3">
                        <article v-for="key in statsOrder" :key="key" class="rounded-2xl border border-border bg-bg p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ t(`public_discovery.common.${key}`) }}</p>
                            <p class="mt-2 text-3xl font-black">{{ stats[key] || 0 }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section v-if="kind === 'event'" class="mx-auto mt-6 grid max-w-6xl gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="surface-card p-5">
                    <p class="text-xs font-bold uppercase text-secondary">{{ t('public_discovery.common.date') }}</p>
                    <p class="mt-2 font-bold">{{ formatDate(entity.start_time) }}</p>
                </article>
                <article class="surface-card p-5">
                    <p class="text-xs font-bold uppercase text-secondary">{{ t('public_discovery.common.location') }}</p>
                    <p class="mt-2 font-bold">{{ entity.location || entity.location_city || t('public_discovery.common.not_specified') }}</p>
                    <Link v-if="entity.city_url" :href="entity.city_url" class="mt-1 inline-block text-sm text-buttonPrimary hover:underline">{{ entity.location_city }}</Link>
                </article>
                <article class="surface-card p-5">
                    <p class="text-xs font-bold uppercase text-secondary">{{ t('public_discovery.common.organizer') }}</p>
                    <Link v-if="entity.club" :href="entity.club.detail_url" class="mt-2 block font-bold text-buttonPrimary hover:underline">{{ entity.team?.name || entity.club.name }}</Link>
                    <p v-else class="mt-2 font-bold">Airmius</p>
                </article>
                <article class="surface-card p-5">
                    <p class="text-xs font-bold uppercase text-secondary">{{ t('public_discovery.common.sport') }}</p>
                    <p class="mt-2 font-bold">{{ sportLabel(entity.sport_type) }}</p>
                    <p v-if="stats.max_participants" class="mt-1 text-sm text-secondary">{{ t('public_discovery.common.capacity', { count: stats.max_participants }) }}</p>
                </article>
            </section>

            <section v-if="clubs.length" class="mx-auto mt-10 max-w-6xl">
                <h2 class="text-2xl font-black">{{ t(`public_discovery.${kind}.clubs_title`) }}</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Link v-for="club in clubs" :key="club.id" :href="club.detail_url" class="surface-card p-5 transition hover:border-buttonPrimary/50">
                        <div class="flex items-start gap-3">
                            <img v-if="club.logo_url" :src="club.logo_url" :alt="club.name" loading="lazy" decoding="async" class="h-12 w-12 rounded-xl border border-border object-cover">
                            <span v-else class="grid h-12 w-12 place-items-center rounded-xl bg-buttonPrimary/10 text-buttonPrimary"><i class="las la-shield-alt text-xl"></i></span>
                            <div>
                                <h3 class="font-black">{{ club.name }}</h3>
                                <p class="mt-1 text-sm text-secondary">{{ sportLabel(club.sport_type) }} · {{ club.city || t('public_discovery.common.location_open') }}</p>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>

            <section v-if="teams.length" class="mx-auto mt-10 max-w-6xl">
                <h2 class="text-2xl font-black">{{ t('public_discovery.club.teams_title') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="team in teams" :key="team.id" class="surface-card p-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-buttonPrimary">{{ sportLabel(team.sport_type) }}</p>
                        <h3 class="mt-2 text-lg font-black">{{ team.name }}</h3>
                    </article>
                </div>
            </section>

            <section v-if="membership_types.length" class="mx-auto mt-10 max-w-6xl">
                <h2 class="text-2xl font-black">{{ t('public_discovery.club.memberships_title') }}</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="membership in membership_types" :key="membership.id" class="surface-card p-5">
                        <h3 class="text-lg font-black">{{ membership.name }}</h3>
                        <p v-if="membership.description" class="mt-2 text-sm leading-6 text-secondary">{{ membership.description }}</p>
                        <p class="mt-4 font-black text-buttonPrimary">
                            <template v-if="membership.amount !== null && membership.amount !== undefined">{{ formatMoney(membership.amount) }} / {{ intervalLabel(membership.billing_interval) }}</template>
                            <template v-else>{{ t('public_discovery.club.contribution_on_request') }}</template>
                        </p>
                    </article>
                </div>
            </section>

            <section v-if="events.length" class="mx-auto mt-10 max-w-6xl">
                <h2 class="text-2xl font-black">{{ t(`public_discovery.${kind}.events_title`) }}</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Link v-for="event in events" :key="event.id" :href="event.detail_url" class="surface-card p-5 transition hover:border-buttonPrimary/50">
                        <p class="text-xs font-black uppercase tracking-wide text-buttonPrimary">{{ typeLabel(event.type) }}</p>
                        <h3 class="mt-2 text-lg font-black">{{ event.title }}</h3>
                        <p class="mt-3 text-sm text-secondary">{{ formatDate(event.start_time) }}</p>
                        <p class="mt-1 text-sm text-secondary">{{ event.location_city || event.location || t('public_discovery.common.not_specified') }}</p>
                    </Link>
                </div>
            </section>

            <section class="mx-auto mt-10 flex max-w-6xl flex-col items-start justify-between gap-4 rounded-3xl border border-buttonPrimary/30 bg-buttonPrimary/10 p-6 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-xl font-black">{{ t('public_discovery.common.cta_title') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ t('public_discovery.common.cta_help') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('guest.cities')" class="rounded-xl border border-border bg-card px-4 py-3 text-sm font-bold">{{ t('public_discovery.common.discover_cities') }}</Link>
                    <Link :href="canRegister ? route('register') : route('login')" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary">{{ t('public_discovery.common.join') }}</Link>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
