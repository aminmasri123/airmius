<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    tAuto: { type: Function, required: true },
    formatWater: { type: Function, required: true },
    deletingDrinkEntries: { type: Array, default: () => [] },
    drinkEntries: { type: Array, default: () => [] },
    weeklySummaries: { type: Array, default: () => [] },
    maxWeekWater: { type: Number, required: true },
    setShowDrinkTips: { type: Function, required: true },
    deleteDrinkEntry: { type: Function, required: true },
})
</script>

<template>
    <aside class="space-y-4">
        <section class="rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4 lg:p-5">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-cyan-400/20 text-cyan-100">
                    <i class="las la-lightbulb text-2xl"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase text-cyan-200">{{ tAuto('Trink-Tipps') }}</p>
                    <h3 class="mt-1 text-lg font-black text-primary">{{ tAuto('Kurz wissen, besser tracken') }}</h3>
                    <p class="mt-1 text-sm leading-6 text-secondary">
                        {{ tAuto('Orientierung zu Wasserziel, Training und Alltag.') }}
                    </p>
                </div>
            </div>
            <div class="mt-4 grid gap-2">
                <button
                    type="button"
                    class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary"
                    @click="setShowDrinkTips(true)"
                >
                    {{ tAuto('Tipps öffnen') }}
                </button>
                <Link
                    :href="route('guest.blog.index', { search: 'Trinken' })"
                    class="rounded-xl border border-border bg-card px-4 py-3 text-center text-sm font-bold text-primary hover:border-cyan-300 hover:bg-cyan-400/10"
                >
                    {{ tAuto('Blog zu Trinken') }}
                </Link>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Heute') }}</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">{{ tAuto('Getränke') }}</h2>
                </div>
                <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-primary">{{ drinkEntries.length }}</span>
            </div>

            <div v-if="deletingDrinkEntries.length" class="mt-4 flex items-center gap-2 rounded-xl border border-cyan-300/30 bg-cyan-400/10 px-3 py-2 text-sm font-semibold text-cyan-100">
                <i class="las la-sync-alt animate-spin"></i>
                <span>{{ tAuto(`${deletingDrinkEntries.length} Eintrag wird gelöscht...`) }}</span>
            </div>

            <div v-if="drinkEntries.length" class="mt-4 space-y-2">
                <article
                    v-for="entry in drinkEntries"
                    :key="entry.id"
                    class="flex items-center justify-between gap-3 rounded-xl border bg-inputBg p-3"
                    :class="entry.is_pending ? 'border-cyan-300/50 bg-cyan-400/10' : 'border-border'"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-primary">{{ tAuto(entry.title) }}</p>
                        <p class="text-xs text-secondary">
                            {{ formatWater(entry.water_ml) }}
                            <span v-if="entry.is_pending" class="ml-1 font-bold text-cyan-200">{{ tAuto('wird gespeichert...') }}</span>
                        </p>
                    </div>
                    <button v-if="!entry.is_pending" type="button" class="rounded-lg border border-danger/30 px-2.5 py-2 text-danger hover:bg-danger/10" :title="tAuto('Löschen')" @click="deleteDrinkEntry(entry)">
                        <i class="las la-trash"></i>
                    </button>
                    <span v-else class="rounded-lg border border-cyan-300/30 px-2.5 py-2 text-cyan-200" :title="tAuto('Wird gespeichert')">
                        <i class="las la-sync-alt animate-spin"></i>
                    </span>
                </article>
            </div>
            <div v-else class="mt-4 rounded-xl border border-dashed border-border bg-inputBg p-6 text-center">
                <i class="las la-tint text-4xl text-cyan-200"></i>
                <p class="mt-3 text-base font-bold text-primary">{{ tAuto('Noch nichts getrunken eingetragen.') }}</p>
                <p class="mt-1 text-sm text-secondary">{{ tAuto('Ein Tippen auf +250 ml reicht während des Tages.') }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
            <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('7 Tage Wasser') }}</p>
            <div class="mt-4 flex h-28 items-end gap-2">
                <div v-for="day in weeklySummaries" :key="`water-${day.date}`" class="flex min-w-0 flex-1 flex-col items-center gap-2">
                    <div class="flex h-20 w-full items-end rounded-full bg-inputBg px-1">
                        <div class="w-full rounded-full bg-gradient-to-t from-cyan-400 to-air-blue" :style="{ height: `${Math.max(6, (Number(day.water_ml || 0) / maxWeekWater) * 100)}%` }"></div>
                    </div>
                    <span class="text-[11px] font-bold text-secondary">{{ day.label }}</span>
                </div>
            </div>
        </section>
    </aside>
</template>

