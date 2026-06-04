<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    nutrition: { type: Object, required: true },
    nutritionChart: { type: Array, default: () => [] },
    hasNutritionChart: { type: Boolean, default: false },
    chartMaxCalories: { type: Number, default: 1 },
    barHeight: { type: Function, required: true },
    formatNumber: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-orange-300">{{ $t('Ernährung') }}</p>
                <h2 class="mt-1 text-lg font-black text-primary">{{ $t('Heute') }}</h2>
            </div>
            <Link :href="route('auth.nutrition.index')" class="rounded-xl border border-border px-3 py-2 text-xs font-bold text-primary hover:border-air-blue hover:text-air-blue">
                {{ $t('Öffnen') }}
            </Link>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
            <div>
                <p class="text-lg font-black text-primary">{{ formatNumber(nutrition.today_calories) }}</p>
                <p class="text-xs text-secondary">kcal</p>
            </div>
            <div>
                <p class="text-lg font-black text-primary">{{ formatNumber(Math.round(nutrition.today_protein_g || 0)) }} g</p>
                <p class="text-xs text-secondary">{{ $t('Protein') }}</p>
            </div>
            <div>
                <p class="text-lg font-black text-primary">{{ formatNumber(nutrition.meals_today) }}</p>
                <p class="text-xs text-secondary">{{ $t('Mahlzeiten') }}</p>
            </div>
        </div>

        <div class="mt-5 h-24">
            <div v-if="hasNutritionChart" class="flex h-full items-end gap-2">
                <div v-for="day in nutritionChart" :key="day.date" class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                    <div class="flex h-16 w-full items-end rounded-full bg-inputBg/80 px-1">
                        <div
                            class="w-full rounded-full bg-gradient-to-t from-orange-500 to-amber-300"
                            :style="{ height: barHeight(day.calories, chartMaxCalories) }"
                        ></div>
                    </div>
                    <span class="text-[10px] font-semibold text-secondary">{{ day.label }}</span>
                </div>
            </div>
            <div v-else class="flex h-full items-center justify-center rounded-xl border border-dashed border-border text-center text-xs text-secondary">
                {{ $t('Noch keine Mahlzeiten eingetragen.') }}
            </div>
        </div>
    </div>
</template>

