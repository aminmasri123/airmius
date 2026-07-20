<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    events: { type: Object, default: () => ({ data: [], links: [] }) },
    eventTypes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const form = ref({
    search: props.filters.search || '',
    type: props.filters.type || '',
    location: props.filters.location || '',
})

const typeLabels = {
    training: 'Training',
    match: 'Spiel',
    meeting: 'Treffen',
    public: 'Öffentlich',
}

const formatDate = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat('de-DE', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

const applyFilters = () => {
    router.get(route('guest.events'), {
        search: form.value.search || undefined,
        type: form.value.type || undefined,
        location: form.value.location || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
    <SeoHead
        title="Sportevents entdecken"
        description="Finde oeffentliche Trainings, Spiele, Treffen und Sportveranstaltungen von Vereinen und Teams auf Airmius."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto max-w-6xl">
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Events</p>
                <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <h1 class="max-w-3xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        Öffentliche Sportevents, Trainings und Treffen finden.
                    </h1>
                    <Link
                        :href="canRegister ? route('register') : route('login')"
                        class="inline-flex w-fit items-center justify-center rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    >
                        Eigenes Event planen
                    </Link>
                </div>

                <form class="mt-8 grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-[1fr_180px_1fr_auto]" @submit.prevent="applyFilters">
                    <input v-model="form.search" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Event, Verein oder Team" />
                    <select v-model="form.type" class="rounded-lg border-border bg-inputBg text-primary">
                        <option value="">Alle Typen</option>
                        <option v-for="type in eventTypes" :key="type" :value="type">
                            {{ typeLabels[type] || type }}
                        </option>
                    </select>
                    <input v-model="form.location" class="rounded-lg border-border bg-inputBg text-primary" placeholder="Ort oder Land" />
                    <button type="submit" class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary">Suchen</button>
                </form>
            </section>

            <section class="mx-auto mt-10 grid max-w-6xl gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="event in events.data" :key="event.id" class="surface-card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ typeLabels[event.type] || event.type }}</p>
                            <h2 class="mt-2 text-xl font-bold text-primary">{{ event.title }}</h2>
                        </div>
                        <span class="shrink-0 rounded-lg bg-buttonPrimary/10 px-3 py-2 text-xs font-bold text-buttonPrimary">
                            {{ formatDate(event.start_time) }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-2 text-sm text-secondary">
                        <p class="flex gap-2">
                            <i class="las la-map-marker-alt mt-0.5 text-buttonPrimary"></i>
                            <span>{{ event.location_city || event.location || 'Ort offen' }}</span>
                        </p>
                        <p v-if="event.club || event.team" class="flex gap-2">
                            <i class="las la-shield-alt mt-0.5 text-buttonPrimary"></i>
                            <span>{{ event.team?.name || event.club?.name }}<span v-if="event.team?.club?.name"> · {{ event.team.club.name }}</span></span>
                        </p>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <Link :href="route('guest.vereine')" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted">
                            Verein finden
                        </Link>
                        <Link :href="canRegister ? route('register') : route('login')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                            Mitmachen
                        </Link>
                    </div>
                </article>

                <div v-if="!events.data?.length" class="surface-card p-8 text-center text-sm text-secondary md:col-span-2 xl:col-span-3">
                    Keine öffentlichen Events zu diesen Suchkriterien gefunden.
                </div>
            </section>

            <section v-if="events.links?.length > 3" class="mx-auto mt-8 flex max-w-6xl flex-wrap justify-center gap-2">
                <Link
                    v-for="link in events.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-lg border px-3 py-2 text-sm font-semibold"
                    :class="link.active ? 'border-air-blue bg-air-blue/15 text-air-blue' : 'border-border text-secondary hover:text-primary'"
                    v-html="link.label"
                />
            </section>
        </main>

        <Footer />
    </div>
</template>
