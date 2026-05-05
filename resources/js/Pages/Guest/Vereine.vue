<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { useI18n } from 'vue-i18n'
import SeoHead from '@/Components/Guest/SeoHead.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    clubs: { type: Array, default: () => [] },
    sports: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const form = ref({
    search: props.filters.search || '',
    sport_type: props.filters.sport_type || '',
    location: props.filters.location || '',
})

const { t, te } = useI18n()
const initials = (name) => (name || '?').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase()
const page = usePage()
const storageUrl = (path) => path?.startsWith('http') ? path : `${page.props.uploads?.url || '/storage'}/${path}`
const sportLabel = (value) => {
    if (!value) return 'Sportart offen'

    const sport = props.sports.find((sport) => sport.slug === value || sport.name === value)
    const slug = sport?.slug || value
    const key = `sports.${slug}`

    return te(key) ? t(key) : (sport?.name || value)
}

const search = () => {
    router.get(route('guest.vereine'), {
        search: form.value.search || undefined,
        sport_type: form.value.sport_type || undefined,
        location: form.value.location || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
    <SeoHead
        title="Vereine finden"
        description="Finde Sportvereine nach Sportart, Standort und Teamangeboten. Entdecke Vereine auf Airmius und vernetze dich digital."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto max-w-6xl">
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">{{ $t('Vereine') }}</p>
                <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <h1 class="max-w-3xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        Finde Vereine nach Sportart und Standort.
                    </h1>
                    <Link
                        :href="page.props.auth?.user ? route('auth.teams.index') : (canRegister ? route('register') : route('login'))"
                        class="inline-flex w-fit items-center justify-center rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    >
                        Verein registrieren
                    </Link>
                </div>

                <form class="mt-8 grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-4" @submit.prevent="search">
                    <input v-model="form.search" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Verein" />
                    <SearchableSelect v-model="form.sport_type" :options="sports" value-key="slug" translation-prefix="sports" category-translation-prefix="sport_categories" placeholder="Sportart suchen" />
                    <input v-model="form.location" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Stadt, PLZ oder Land" />
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary">Suchen</button>
                </form>
            </section>

            <section class="mx-auto mt-10 grid max-w-6xl gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="club in clubs" :key="club.id" class="surface-card p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-inputBg font-bold">
                            <img v-if="club.logo" :src="storageUrl(club.logo)" :alt="club.name" class="h-full w-full object-cover">
                            <span v-else>{{ initials(club.name) }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="flex min-w-0 items-center gap-2">
                                <h2 class="truncate text-lg font-bold text-primary">{{ club.name }}</h2>
                                <span v-if="club.is_official" class="shrink-0 rounded-full bg-success/10 px-2 py-0.5 text-xs font-semibold text-success">Offiziell</span>
                            </div>
                            <p class="text-sm text-secondary">{{ sportLabel(club.sport_type) }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ club.postal_code }} {{ club.city }} · {{ club.country || 'Land offen' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-sm text-secondary">{{ club.teams_count }} Teams</span>
                        <Link :href="route('login')" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted">
                            Ansehen
                        </Link>
                    </div>
                </article>

                <div v-if="!clubs.length" class="surface-card p-8 text-center text-sm text-secondary md:col-span-2 xl:col-span-3">
                    Keine Vereine zu diesen Suchkriterien gefunden.
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
