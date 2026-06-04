<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    eventItems: { type: Array, default: () => [] },
    calendarEventItems: { type: Array, default: () => [] },
    viewMode: { type: String, default: 'calendar' },
    calendarMonthLabel: { type: String, default: '' },
    calendarWeekdays: { type: Array, default: () => [] },
    calendarDays: { type: Array, default: () => [] },
    selectedCalendarDate: { type: String, default: '' },
    selectedCalendarEvents: { type: Array, default: () => [] },
    moveCalendarMonth: { type: Function, required: true },
    selectCalendarDay: { type: Function, required: true },
    jumpToToday: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    formatTime: { type: Function, required: true },
    eventDateTimeLabel: { type: Function, required: true },
    participantCapacityLabel: { type: Function, required: true },
})

const emit = defineEmits(['update:viewMode'])
</script>

<template>
    <section v-if="eventItems.length || calendarEventItems.length" class="rounded-lg border border-border bg-card">
        <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Ansicht</p>
                <h2 class="mt-1 text-lg font-bold text-primary">Kalender & Liste</h2>
            </div>

            <div class="grid grid-cols-2 gap-2 rounded-lg border border-border bg-inputBg p-1">
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-semibold transition"
                    :class="viewMode === 'calendar' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                    @click="emit('update:viewMode', 'calendar')"
                >
                    <i class="las la-calendar mr-1"></i>
                    Kalender
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-semibold transition"
                    :class="viewMode === 'list' ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-secondary hover:text-primary'"
                    @click="emit('update:viewMode', 'list')"
                >
                    <i class="las la-list mr-1"></i>
                    Liste
                </button>
            </div>
        </div>

        <div v-if="viewMode === 'calendar'" class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="border-b border-border p-4 lg:border-b-0 lg:border-r">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-secondary hover:border-borderHover hover:text-primary"
                        aria-label="Vorheriger Monat"
                        @click="moveCalendarMonth(-1)"
                    >
                        <i class="las la-angle-left text-xl"></i>
                    </button>

                    <div class="flex flex-col items-center gap-2 sm:flex-row">
                        <h3 class="text-center text-base font-bold capitalize text-primary">
                            {{ calendarMonthLabel }}
                        </h3>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-secondary hover:border-borderHover hover:text-primary"
                            @click="jumpToToday"
                        >
                            Heute
                        </button>
                    </div>

                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-secondary hover:border-borderHover hover:text-primary"
                        aria-label="Nächster Monat"
                        @click="moveCalendarMonth(1)"
                    >
                        <i class="las la-angle-right text-xl"></i>
                    </button>
                </div>

                <div class="grid grid-cols-7 gap-px overflow-hidden rounded-lg border border-border bg-border">
                    <div
                        v-for="weekday in calendarWeekdays"
                        :key="weekday"
                        class="bg-inputBg px-2 py-2 text-center text-xs font-bold uppercase text-secondary"
                    >
                        {{ weekday }}
                    </div>

                    <button
                        v-for="day in calendarDays"
                        :key="day.key"
                        type="button"
                        class="min-h-24 bg-card p-2 text-left transition hover:bg-inputBg"
                        :class="[
                            !day.isCurrentMonth ? 'opacity-45' : '',
                            selectedCalendarDate === day.key ? 'ring-2 ring-inset ring-buttonPrimary' : '',
                        ]"
                        @click="selectCalendarDay(day)"
                    >
                        <span
                            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                            :class="day.isToday ? 'bg-buttonPrimary text-buttonTextPrimary' : 'text-primary'"
                        >
                            {{ day.day }}
                        </span>

                        <div class="mt-2 space-y-1">
                            <span
                                v-for="event in day.events.slice(0, 2)"
                                :key="event.id"
                                class="block truncate rounded px-2 py-1 text-[11px] font-semibold"
                                :class="event.status === 'cancelled' ? 'bg-error/10 text-error line-through' : 'bg-buttonPrimary/10 text-buttonPrimary'"
                            >
                                {{ formatTime(event.start_time, event) }} {{ event.title }}
                            </span>
                            <span v-if="day.events.length > 2" class="block text-[11px] font-semibold text-secondary">
                                +{{ day.events.length - 2 }} mehr
                            </span>
                        </div>
                    </button>
                </div>
            </div>

            <aside class="p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                    Ausgewählter Tag
                </p>
                <h3 class="mt-1 text-lg font-bold text-primary">
                    {{ formatDate(`${selectedCalendarDate}T12:00:00`) }}
                </h3>

                <div class="mt-4 space-y-3">
                    <article
                        v-for="event in selectedCalendarEvents"
                        :key="event.id"
                        class="rounded-lg border border-border bg-inputBg p-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <Link :href="route('auth.events.show', event.id)" class="font-bold text-primary hover:text-buttonPrimary hover:underline">
                                    {{ event.title }}
                                </Link>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ eventDateTimeLabel(event) }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full bg-card px-2 py-1 text-xs font-semibold text-secondary">
                                {{ participantCapacityLabel(event) }}
                            </span>
                        </div>
                        <p class="mt-2 truncate text-sm text-secondary">
                            <i class="las la-map-marker-alt text-buttonPrimary"></i>
                            {{ event.location || 'Keine Eingabe' }}
                        </p>
                    </article>

                    <div v-if="!selectedCalendarEvents.length" class="rounded-lg border border-dashed border-border p-5 text-center text-sm text-secondary">
                        An diesem Tag sind keine Events im aktuellen Filter.
                    </div>
                </div>
            </aside>
        </div>
    </section>
</template>


