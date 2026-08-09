<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'
import { useI18n } from 'vue-i18n'

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

const { t, locale } = useI18n()
const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

const typeLabels = {
    training: 'events.types.training',
    match: 'events.types.match',
    meeting: 'events.types.meeting',
    public: 'events.types.public',
}

const typeLabel = (type) => typeLabels[type] ? t(typeLabels[type]) : type
const dateLocale = () => locale.value === 'ar' ? 'ar' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE'))

const formatDate = (value) => {
    if (!value) return ''

    return new Intl.DateTimeFormat(dateLocale(), {
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
        only: ['events', 'filters'],
    })
}
</script>

<template>
    <SeoHead
        :title="t('guest.events.meta_title')"
        :description="t('guest.events.meta_description')"
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main id="main-content" class="px-4 pt-36 md:pt-44" tabindex="-1">
            <section class="mx-auto max-w-6xl">
                <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">{{ t('guest.events.eyebrow') }}</p>
                <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <h1 class="max-w-3xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        {{ t('guest.events.title') }}
                    </h1>
                    <Link
                        :href="canRegister ? route('register') : route('login')"
                        class="inline-flex w-fit items-center justify-center rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    >
                        {{ t('guest.events.plan') }}
                    </Link>
                </div>

                <form class="mt-8 grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-[1fr_180px_1fr_auto]" @submit.prevent="applyFilters">
                    <input v-model="form.search" class="rounded-lg border-border bg-inputBg text-primary" :placeholder="t('guest.events.search_placeholder')" :aria-label="t('guest.events.search_label')" />
                    <select v-model="form.type" class="rounded-lg border-border bg-inputBg text-primary" :aria-label="t('guest.events.type_label')">
                        <option value="">{{ t('guest.events.all_types') }}</option>
                        <option v-for="type in eventTypes" :key="type" :value="type">
                            {{ typeLabel(type) }}
                        </option>
                    </select>
                    <input v-model="form.location" class="rounded-lg border-border bg-inputBg text-primary" :placeholder="t('guest.events.location_placeholder')" :aria-label="t('guest.events.location_label')" />
                    <button type="submit" class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary">{{ t('guest.events.search') }}</button>
                </form>
            </section>

            <section class="mx-auto mt-10 grid max-w-6xl gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="event in events.data" :key="event.id" class="surface-card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ typeLabel(event.type) }}</p>
                            <Link :href="event.detail_url" class="mt-2 block text-xl font-bold text-primary hover:text-buttonPrimary hover:underline">{{ event.title }}</Link>
                        </div>
                        <span class="shrink-0 rounded-lg bg-buttonPrimary/10 px-3 py-2 text-xs font-bold text-buttonPrimary">
                            {{ formatDate(event.start_time) }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-2 text-sm text-secondary">
                        <p class="flex gap-2">
                            <i class="las la-map-marker-alt mt-0.5 text-buttonPrimary"></i>
                            <Link v-if="event.city_url" :href="event.city_url" class="hover:text-buttonPrimary hover:underline">{{ event.location_city || event.location }}</Link>
                            <span v-else>{{ event.location_city || event.location || t('events.places.no_city') }}</span>
                        </p>
                        <p v-if="event.club || event.team" class="flex gap-2">
                            <i class="las la-shield-alt mt-0.5 text-buttonPrimary"></i>
                            <Link v-if="event.club" :href="event.club.detail_url" class="hover:text-buttonPrimary hover:underline">{{ event.team?.name || event.club.name }}</Link>
                            <span v-else>{{ event.team?.name }}</span>
                        </p>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <Link :href="event.detail_url" class="rounded-lg border border-buttonPrimary/40 px-3 py-2 text-sm font-semibold text-buttonPrimary hover:bg-buttonPrimary/10">
                            {{ t('public_discovery.common.view_details') }}
                        </Link>
                        <Link :href="route('guest.vereine')" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted">
                            {{ t('guest.events.find_club') }}
                        </Link>
                        <Link :href="canRegister ? route('register') : route('login')" class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary">
                            {{ t('guest.events.join') }}
                        </Link>
                    </div>
                </article>

                <div v-if="!events.data?.length" class="surface-card p-8 text-center text-sm text-secondary md:col-span-2 xl:col-span-3">
                    {{ t('guest.events.empty') }}
                </div>
            </section>

            <section v-if="events.links?.length > 3" class="mx-auto mt-8 flex max-w-6xl flex-wrap justify-center gap-2">
                <Link
                    v-for="link in events.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    :only="['events', 'filters']"
                    preserve-state
                    preserve-scroll
                    class="rounded-lg border px-3 py-2 text-sm font-semibold"
                    :class="link.active ? 'border-air-blue bg-air-blue/15 text-air-blue' : 'border-border text-secondary hover:text-primary'"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
            </section>
        </main>

        <Footer />
    </div>
</template>
