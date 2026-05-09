<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useTheme } from '@/services/useTheme'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    sponsors: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, platform: 0, club: 0 }) },
})

const query = ref('')
const scope = ref('all')
const { isDark } = useTheme()

const filteredSponsors = computed(() => {
    const term = query.value.trim().toLowerCase()

    return props.sponsors.filter((sponsor) => {
        const matchesScope = scope.value === 'all' || sponsor.scope === scope.value
        const haystack = [
            sponsor.name,
            sponsor.contact_name,
            sponsor.club?.name,
            sponsor.website,
        ].filter(Boolean).join(' ').toLowerCase()

        return matchesScope && (!term || haystack.includes(term))
    })
})

const featuredSponsors = computed(() => filteredSponsors.value.slice(0, 3))
const remainingSponsors = computed(() => filteredSponsors.value.slice(3))
const initials = (name) => (name || 'A').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase()
const sponsorLogoUrl = (sponsor) => isDark.value
    ? (sponsor.logo_dark_url || sponsor.logo_light_url || sponsor.logo_url)
    : (sponsor.logo_light_url || sponsor.logo_dark_url || sponsor.logo_url)
</script>

<template>
    <SeoHead
        title="Airmius Sponsoren"
        description="Entdecke die Sponsoren und Partner, die Airmius, Vereine und Sportangebote unterstuetzen."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[1.08fr_0.92fr] lg:items-end">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-air-blue">Airmius Partner</p>
                    <h1 class="mt-4 max-w-3xl font-heading text-4xl font-900 leading-tight sm:text-6xl">
                        Sponsoren, die Sport sichtbar und bezahlbarer machen.
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-secondary">
                        Hier finden Gaeste alle aktiven Airmius-Sponsoren und Vereins-Partner. Von Plattform-Deals bis zu lokalen Foerderern: Jede Karte zeigt, wer hinter Rabatten, Angeboten und Vereinsunterstuetzung steht.
                    </p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <Link
                            :href="route('guest.marketplace')"
                            class="inline-flex items-center justify-center rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                        >
                            Angebote ansehen
                        </Link>
                        <Link
                            :href="route('guest.werbeagentur')"
                            class="inline-flex items-center justify-center rounded-lg border border-border px-5 py-3 text-sm font-semibold text-primary transition hover:border-borderHover hover:bg-muted"
                        >
                            Sponsor werden
                        </Link>
                    </div>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 shadow-2xl shadow-black/10">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Gesamt</p>
                            <p class="mt-2 text-3xl font-black text-primary">{{ stats.total }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Plattform</p>
                            <p class="mt-2 text-3xl font-black text-primary">{{ stats.platform }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-inputBg p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Vereine</p>
                            <p class="mt-2 text-3xl font-black text-primary">{{ stats.club }}</p>
                        </div>
                    </div>
                    <div class="mt-4 rounded-lg border border-air-blue/30 bg-air-blue/10 p-4">
                        <p class="text-sm font-semibold text-primary">Sichtbar für Gaeste</p>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            Sponsoren aus dem Adminbereich erscheinen hier automatisch, solange ihre Laufzeit nicht beendet ist.
                        </p>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-10 max-w-6xl rounded-xl border border-border bg-card p-4">
                <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-center">
                    <label class="relative block">
                        <i class="las la-search absolute left-4 top-1/2 -translate-y-1/2 text-xl text-secondary"></i>
                        <input
                            v-model="query"
                            class="w-full rounded-lg border-border bg-inputBg py-3 pl-11 pr-4 text-primary"
                            placeholder="Sponsor, Verein oder Website suchen"
                        >
                    </label>
                    <div class="grid grid-cols-3 rounded-lg border border-border bg-inputBg p-1 text-sm font-semibold">
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 transition"
                            :class="scope === 'all' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                            @click="scope = 'all'"
                        >
                            Alle
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 transition"
                            :class="scope === 'platform' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                            @click="scope = 'platform'"
                        >
                            Airmius
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 transition"
                            :class="scope === 'club' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                            @click="scope = 'club'"
                        >
                            Vereine
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="featuredSponsors.length" class="mx-auto mt-8 grid max-w-6xl gap-4 lg:grid-cols-3">
                <article
                    v-for="sponsor in featuredSponsors"
                    :key="sponsor.id"
                    class="group overflow-hidden rounded-xl border border-border bg-card shadow-xl shadow-black/10 transition hover:-translate-y-1 hover:border-borderHover"
                >
                    <div class="flex h-40 items-center justify-center bg-inputBg p-6">
                        <img v-if="sponsorLogoUrl(sponsor)" :src="sponsorLogoUrl(sponsor)" :alt="sponsor.name" class="max-h-full max-w-full object-contain">
                        <div v-else class="flex h-20 w-20 items-center justify-center rounded-lg bg-buttonPrimary text-2xl font-black text-buttonTextPrimary">
                            {{ initials(sponsor.name) }}
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-air-blue/10 px-3 py-1 text-xs font-semibold text-air-blue">
                                {{ sponsor.scope === 'platform' ? 'Airmius Plattform' : 'Vereins-Sponsor' }}
                            </span>
                            <span v-if="sponsor.club" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                                {{ sponsor.club.name }}
                            </span>
                        </div>
                        <h2 class="mt-4 text-xl font-black text-primary">{{ sponsor.name }}</h2>
                        <p class="mt-2 text-sm text-secondary">
                            {{ sponsor.scope === 'platform' ? 'Unterstuetzt Airmius-Angebote und Plattform-Vorteile.' : 'Unterstuetzt Vereinsangebote und lokale Sportprojekte.' }}
                        </p>
                        <a
                            v-if="sponsor.website"
                            :href="sponsor.website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-air-blue hover:text-borderHover"
                        >
                            Website besuchen
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
                            <img v-if="sponsorLogoUrl(sponsor)" :src="sponsorLogoUrl(sponsor)" :alt="sponsor.name" class="h-full w-full object-contain p-2">
                            <span v-else class="text-lg font-black text-primary">{{ initials(sponsor.name) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-bold text-primary">{{ sponsor.name }}</h2>
                                <span class="rounded-full bg-inputBg px-2 py-0.5 text-xs font-semibold text-secondary">
                                    {{ sponsor.scope === 'platform' ? 'Airmius' : sponsor.club?.name || 'Verein' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-secondary">
                                {{ sponsor.scope === 'platform' ? 'Airmius-Partner' : 'Vereins-Partner' }}
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
                    <h2 class="mt-4 text-xl font-bold text-primary">Keine Sponsoren gefunden.</h2>
                    <p class="mt-2 text-sm text-secondary">Passe die Suche oder den Filter an.</p>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
