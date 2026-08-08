<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    nextEvent: { type: Object, default: null },
    authorizationMessage: { type: String, default: '' },
    upcomingEventsCount: { type: Number, default: 0 },
    todayEventsCount: { type: Number, default: 0 },
    cancelledEventsCount: { type: Number, default: 0 },
    eventDateTimeLabel: { type: Function, required: true },
    openCreateModal: { type: Function, required: true },
})
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-border bg-card">
        <div class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary">
                        <i class="las la-calendar-check text-xl"></i>
                    </span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ $t('Events & Training') }}</p>
                </div>

                <h1 class="mt-3 text-2xl font-bold text-primary sm:text-3xl">
                    {{ $t('events.title') }}
                </h1>

                <p class="mt-2 max-w-2xl text-sm leading-6 text-secondary">
                    {{ $t('events.subtitle') }}
                </p>

                <div v-if="nextEvent" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="rounded-full bg-air-green/10 px-3 py-1 font-semibold text-air-green">
                        Nächstes Event
                    </span>
                    <span class="text-secondary">
                        {{ nextEvent.title }} - {{ eventDateTimeLabel(nextEvent) }}
                    </span>
                </div>
            </div>

            <button
                type="button"
                class="inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary transition hover:bg-buttonPrimaryHover sm:w-auto"
                @click="openCreateModal"
            >
                <i class="las la-plus text-lg"></i>
                {{ $t('events.create') }}
            </button>
        </div>

        <div class="grid grid-cols-3 gap-2 border-t border-border p-3 sm:gap-0 sm:p-0">
            <div class="rounded-xl border border-border bg-inputBg p-3 text-center sm:rounded-none sm:border-0 sm:border-r sm:bg-transparent sm:p-4 sm:text-left">
                <p class="text-[10px] font-semibold uppercase text-secondary sm:text-xs sm:tracking-wide">Kommend</p>
                <p class="mt-1 text-xl font-bold text-primary sm:text-2xl">{{ upcomingEventsCount }}</p>
            </div>
            <div class="rounded-xl border border-border bg-inputBg p-3 text-center sm:rounded-none sm:border-0 sm:border-r sm:bg-transparent sm:p-4 sm:text-left">
                <p class="text-[10px] font-semibold uppercase text-secondary sm:text-xs sm:tracking-wide">Heute</p>
                <p class="mt-1 text-xl font-bold text-primary sm:text-2xl">{{ todayEventsCount }}</p>
            </div>
            <div class="rounded-xl border border-border bg-inputBg p-3 text-center sm:rounded-none sm:border-0 sm:bg-transparent sm:p-4 sm:text-left">
                <p class="text-[10px] font-semibold uppercase text-secondary sm:text-xs sm:tracking-wide">Abgesagt</p>
                <p class="mt-1 text-xl font-bold text-primary sm:text-2xl">{{ cancelledEventsCount }}</p>
            </div>
        </div>
    </section>

    <div v-if="authorizationMessage" class="rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="font-semibold">
                {{ authorizationMessage }}
            </p>
            <Link :href="route('guest.pricing')" class="shrink-0 rounded-lg bg-buttonPrimary px-4 py-2 text-center text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover">
                Upgrade ansehen
            </Link>
        </div>
    </div>
</template>
