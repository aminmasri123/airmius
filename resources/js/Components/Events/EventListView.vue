<script setup>
import { Link } from '@inertiajs/vue3'

const paginationLabel = (label) => String(label || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&amp;/g, '&')

defineProps({
    viewMode: { type: String, default: 'calendar' },
    eventItems: { type: Array, default: () => [] },
    eventPaginationLinks: { type: Array, default: () => [] },
    typeLabels: { type: Object, default: () => ({}) },
    visibilityLabels: { type: Object, default: () => ({}) },
    rsvpOptions: { type: Array, default: () => [] },
    formatWeekdayShort: { type: Function, required: true },
    formatDay: { type: Function, required: true },
    formatMonthShort: { type: Function, required: true },
    eventDateTimeLabel: { type: Function, required: true },
    hasParticipantLimit: { type: Function, required: true },
    isEventFullForYes: { type: Function, required: true },
    participantCapacityLabel: { type: Function, required: true },
    rsvpButtonClass: { type: Function, required: true },
    setParticipation: { type: Function, required: true },
})
</script>

<template>
    <div v-if="viewMode === 'list' && eventItems.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="event in eventItems" :key="event.id"
            class="group overflow-hidden rounded-lg border border-border bg-card p-4 transition hover:-translate-y-0.5 hover:border-borderHover hover:shadow-xl">
            <div class="flex items-start gap-3">
                <div class="flex w-16 shrink-0 flex-col items-center justify-center rounded-lg border border-border bg-inputBg py-2">
                    <span class="text-[10px] font-semibold uppercase text-secondary">
                        {{ formatWeekdayShort(event.start_time, event) }}
                    </span>

                    <span class="text-xl font-bold leading-tight text-primary">
                        {{ formatDay(event.start_time, event) }}
                    </span>

                    <span class="text-[10px] font-semibold uppercase text-secondary">
                        {{ formatMonthShort(event.start_time, event) }}
                    </span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link :href="route('auth.events.show', event.id)"
                                class="line-clamp-2 text-base font-bold text-primary transition group-hover:text-buttonPrimary hover:underline">
                                <span>{{ event.title }}</span>
                            </Link>

                            <p class="mt-1 text-sm text-secondary">
                                {{ event.club?.name || event.team?.name || $t('events.public_scope') }}
                            </p>
                        </div>

                        <span class="shrink-0 rounded-full bg-inputBg px-2 py-1 text-xs text-secondary">
                            {{ participantCapacityLabel(event) }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="rounded-full bg-buttonPrimary/10 px-2 py-1 text-xs font-semibold text-buttonPrimary">
                            {{ $t(typeLabels[event.type] || event.type) }}
                        </span>
                        <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                            {{ $t(visibilityLabels[event.visibility] || event.visibility) }}
                        </span>
                        <span v-if="event.comments_count" class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                            {{ event.comments_count }} Kommentare
                        </span>
                        <span v-if="hasParticipantLimit(event)" class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                            Max. {{ event.max_participants }}
                        </span>
                        <span v-if="event.can_update" class="rounded-full bg-buttonPrimary/10 px-2 py-1 text-xs font-semibold text-buttonPrimary">
                            Bearbeitbar
                        </span>
                        <span v-if="event.status === 'cancelled'" class="rounded-full bg-error/10 px-2 py-1 text-xs font-semibold text-error">
                            Abgesagt
                        </span>
                    </div>

                    <div class="mt-3 space-y-1">
                        <p class="text-sm font-semibold text-primary">
                            {{ eventDateTimeLabel(event) }}
                        </p>

                        <p class="text-sm text-secondary">
                            {{ event.team?.name || event.club?.name || $t('events.public_scope') }}
                        </p>

                        <p class="flex items-center gap-1 text-sm text-secondary">
                            <i class="las la-map-marker-alt text-base text-buttonPrimary"></i>
                            <span class="truncate">{{ event.location || 'Keine Eingabe' }}</span>
                        </p>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <button v-for="option in rsvpOptions" :key="option.value" type="button"
                            class="rounded-lg border px-2 py-2 text-xs font-semibold transition"
                            :class="rsvpButtonClass(event, option.value)"
                            :disabled="event.status === 'cancelled' || (option.value === 'yes' && isEventFullForYes(event))"
                            :title="event.status === 'cancelled' ? 'Event ist abgesagt' : option.value === 'yes' && isEventFullForYes(event) ? 'Dieses Event ist voll' : ''"
                            @click="setParticipation(event, option.value)">
                            {{ $t(option.label) }}
                        </button>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            :href="route('auth.events.show', event.id)"
                            class="inline-flex flex-1 items-center justify-center rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover"
                        >
                            <i class="las la-eye mr-1"></i>
                            Details
                        </Link>
                        <Link
                            v-if="event.can_update"
                            :href="`${route('auth.events.show', event.id)}?edit=1`"
                            class="inline-flex flex-1 items-center justify-center rounded-lg border border-border px-3 py-2 text-sm font-semibold text-primary transition hover:border-borderHover hover:bg-muted"
                        >
                            <i class="las la-edit mr-1"></i>
                            Bearbeiten
                        </Link>
                    </div>
                </div>
            </div>
        </article>
    </div>

    <nav v-if="viewMode === 'list' && eventPaginationLinks.length > 3" class="flex flex-wrap items-center justify-center gap-2">
        <Link
            v-for="link in eventPaginationLinks"
            :key="link.label"
            :href="link.url || '#'"
            preserve-scroll
            class="min-w-10 rounded-lg border px-3 py-2 text-center text-sm font-semibold transition"
            :class="[
                link.active ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-card text-secondary hover:border-borderHover hover:text-primary',
                !link.url ? 'pointer-events-none opacity-45' : '',
            ]"
        >
            {{ paginationLabel(link.label) }}
        </Link>
    </nav>
</template>
