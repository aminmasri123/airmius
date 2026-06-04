<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    training: { type: Object, required: true },
    trainingChart: { type: Array, default: () => [] },
    hasTrainingChart: { type: Boolean, default: false },
    chartMaxMinutes: { type: Number, default: 1 },
    barHeight: { type: Function, required: true },
    formatDistance: { type: Function, required: true },
    formatNumber: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-4 sm:p-5 xl:col-span-7">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-air-blue">{{ $t('Wochenübersicht') }}</p>
                <h2 class="mt-1 text-xl font-black text-primary">{{ $t('Training') }}</h2>
            </div>
            <Link :href="route('auth.training.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                {{ $t('Öffnen') }}
            </Link>
        </div>

        <div class="mt-5 h-44">
            <div v-if="hasTrainingChart" class="flex h-full items-end gap-2">
                <div v-for="day in trainingChart" :key="day.date" class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                    <div class="flex h-32 w-full items-end rounded-full bg-inputBg/80 px-1">
                        <div
                            class="w-full rounded-full bg-gradient-to-t from-air-blue to-cyan-300"
                            :style="{ height: barHeight(day.minutes, chartMaxMinutes) }"
                            :title="`${day.minutes} min`"
                        ></div>
                    </div>
                    <span class="text-[11px] font-semibold text-secondary">{{ day.label }}</span>
                </div>
            </div>
            <div v-else class="flex h-full items-center justify-center rounded-2xl border border-dashed border-border text-center text-sm text-secondary">
                {{ $t('Noch keine Trainingsdaten für diese Woche.') }}
            </div>
        </div>

        <div class="mt-5 grid grid-cols-3 gap-2 text-center">
            <div>
                <p class="text-lg font-black text-primary">{{ formatDistance(training.week_distance_meters) }}</p>
                <p class="text-xs text-secondary">{{ $t('Distanz') }}</p>
            </div>
            <div>
                <p class="text-lg font-black text-primary">{{ formatNumber(training.week_calories) }}</p>
                <p class="text-xs text-secondary">{{ $t('Kalorien') }}</p>
            </div>
            <div>
                <p class="text-lg font-black text-primary">{{ formatNumber(training.plan_count) }}</p>
                <p class="text-xs text-secondary">{{ $t('Pläne') }}</p>
            </div>
        </div>
    </div>
</template>

