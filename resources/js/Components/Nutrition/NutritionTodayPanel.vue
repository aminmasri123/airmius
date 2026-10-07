<script setup>
defineProps({
    caloriesLeft: { type: Number, default: 0 },
    drinkForm: { type: Object, required: true },
    formatNumber: { type: Function, required: true },
    formatWater: { type: Function, required: true },
    macroCards: { type: Array, default: () => [] },
    maxWeekCalories: { type: Number, default: 1 },
    mealMicronutrients: { type: Function, default: () => [] },
    mealTypeMeta: { type: Function, required: true },
    micronutrientAccess: { type: Object, default: () => ({}) },
    micronutrientAccessReason: { type: String, default: '' },
    progressValue: { type: Function, required: true },
    quickDrinkAmounts: { type: Array, default: () => [] },
    sortedMeals: { type: Array, default: () => [] },
    tAuto: { type: Function, required: true },
    todaySummary: { type: Object, default: () => ({}) },
    waterConsumedMl: { type: Number, default: 0 },
    waterLeftMl: { type: Number, default: 0 },
    waterProgress: { type: Number, default: 0 },
    waterTargetMl: { type: Number, default: 0 },
    weeklySummaries: { type: Array, default: () => [] },
})

defineEmits(['delete-meal', 'edit-meal', 'set-active-section', 'submit-drink'])
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-[minmax(0,0.95fr),minmax(360px,0.75fr)]">
        <div class="space-y-4">
            <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Heute') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ tAuto(`${formatNumber(todaySummary.calories || 0)} kcal gegessen`) }}</h2>
                        <p class="mt-1 text-sm text-secondary">
                            {{ tAuto(`${formatNumber(caloriesLeft)} kcal bis zu deinem Tagesziel.`) }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" @click="$emit('set-active-section', 'add')">
                            {{ tAuto('Mahlzeit erfassen') }}
                        </button>
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted" @click="$emit('set-active-section', 'ideas')">
                            {{ tAuto('Ideen') }}
                        </button>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-5 sm:gap-3 xl:grid-cols-4">
                    <article v-for="macro in macroCards" :key="macro.key" class="rounded-xl border border-border bg-inputBg p-3 sm:p-3">
                        <div class="flex items-start justify-between gap-2 sm:items-center sm:gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[11px] font-bold uppercase text-secondary sm:text-xs">{{ macro.label }}</p>
                                <p class="mt-1 text-base font-bold text-primary sm:text-lg">
                                    {{ formatNumber(macro.value, macro.key === 'calories' ? 0 : 1) }}
                                    <span class="text-xs text-secondary">{{ macro.unit }}</span>
                                </p>
                            </div>
                            <span :class="['flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white sm:h-9 sm:w-9', macro.color]">
                                <i :class="[macro.icon, 'text-base sm:text-lg']"></i>
                            </span>
                        </div>
                        <div class="mt-2 h-1.5 rounded-full bg-card sm:mt-3">
                            <div :class="['h-1.5 rounded-full bg-gradient-to-r', macro.color]" :style="{ width: `${progressValue(macro.value, macro.target)}%` }"></div>
                        </div>
                    </article>
                </div>

                <div class="mt-4 rounded-2xl border border-air-blue/25 bg-air-blue/10 p-4">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Trinken') }}</p>
                            <h3 class="mt-1 text-xl font-black text-primary">{{ tAuto(`${formatWater(waterConsumedMl)} getrunken`) }}</h3>
                            <p class="mt-1 text-sm text-secondary">
                                {{ tAuto(`${formatWater(waterLeftMl)} bis zu deinem Tagesziel von ${formatWater(waterTargetMl)}.`) }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                v-for="amount in quickDrinkAmounts"
                                :key="amount"
                                type="button"
                                class="rounded-xl border border-air-blue/40 bg-card px-3 py-2 text-sm font-bold text-primary hover:bg-air-blue/15 disabled:opacity-60"
                                :disabled="drinkForm.processing"
                                @click="$emit('submit-drink', amount)"
                            >
                                +{{ amount }} ml
                            </button>
                        </div>
                    </div>
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-card">
                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-air-blue" :style="{ width: `${waterProgress}%` }"></div>
                    </div>
                    <button type="button" class="mt-3 text-sm font-bold text-air-blue hover:text-primary" @click="$emit('set-active-section', 'drink')">
                        {{ tAuto('Trinken genau verwalten') }}
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Verlauf') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">{{ tAuto('Letzte 7 Tage') }}</h2>
                    </div>
                    <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-secondary">{{ tAuto(`${formatNumber(todaySummary.water_ml || 0)} ml Wasser`) }}</span>
                </div>
                <div class="mt-4 flex h-28 items-end gap-2">
                    <div v-for="day in weeklySummaries" :key="day.date" class="flex min-w-0 flex-1 flex-col items-center gap-2">
                        <div class="flex h-20 w-full items-end rounded-full bg-inputBg px-1">
                            <div class="w-full rounded-full bg-gradient-to-t from-air-blue to-emerald-300" :style="{ height: `${Math.max(6, (Number(day.calories || 0) / maxWeekCalories) * 100)}%` }"></div>
                        </div>
                        <span class="text-[11px] font-bold text-secondary">{{ day.label }}</span>
                    </div>
                </div>
            </section>
        </div>

        <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Tageslog') }}</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">{{ tAuto('Mahlzeiten') }}</h2>
                </div>
                <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-primary">{{ tAuto(`${sortedMeals.length} Einträge`) }}</span>
            </div>

            <div class="mt-4 space-y-3">
                <article v-for="meal in sortedMeals" :key="meal.id" class="rounded-xl border border-border bg-inputBg p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase text-secondary">
                                <i :class="[mealTypeMeta(meal.meal_type).icon, 'me-1']"></i>
                                {{ tAuto(mealTypeMeta(meal.meal_type).label) }}
                            </p>
                            <h3 class="mt-1 truncate text-base font-bold text-primary">{{ meal.title }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ tAuto(`${formatNumber(meal.calories)} kcal - ${formatNumber(meal.protein_g, 1)} g Protein`) }}</p>
                            <div v-if="mealMicronutrients(meal).length" class="mt-2 flex flex-wrap gap-1.5">
                                <span
                                    v-for="nutrient in mealMicronutrients(meal)"
                                    :key="`${meal.id}-${nutrient.key || nutrient.label}`"
                                    class="rounded-full border border-air-blue/30 bg-air-blue/10 px-2 py-1 text-[11px] font-bold text-air-blue"
                                >
                                    {{ nutrient.label }} {{ formatNumber(nutrient.amount, nutrient.amount < 10 ? 2 : 1) }} {{ nutrient.unit }}
                                </span>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" class="rounded-lg border border-border px-2.5 py-2 text-primary hover:bg-muted" :title="tAuto('Bearbeiten')" @click="$emit('edit-meal', meal)">
                                <i class="las la-pen"></i>
                            </button>
                            <button type="button" class="rounded-lg border border-danger/30 px-2.5 py-2 text-danger hover:bg-danger/10" :title="tAuto('Löschen')" @click="$emit('delete-meal', meal)">
                                <i class="las la-trash"></i>
                            </button>
                        </div>
                    </div>
                </article>

                <div v-if="!sortedMeals.length" class="rounded-xl border border-dashed border-border bg-inputBg p-6 text-center">
                    <i class="las la-apple-alt text-4xl text-air-blue"></i>
                    <p class="mt-3 text-base font-bold text-primary">{{ tAuto('Noch nichts erfasst.') }}</p>
                    <p class="mt-1 text-sm text-secondary">{{ tAuto('Eine grobe Mahlzeit reicht für den Anfang.') }}</p>
                </div>
                <div v-else-if="!micronutrientAccess.available && micronutrientAccessReason" class="rounded-xl border border-amber-300/30 bg-amber-300/10 p-3 text-sm font-semibold text-secondary">
                    {{ micronutrientAccessReason }}
                </div>
            </div>
        </section>
    </section>
</template>
