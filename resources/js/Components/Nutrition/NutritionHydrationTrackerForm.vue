<script setup>
defineProps({
    tAuto: { type: Function, required: true },
    formatWater: { type: Function, required: true },
    waterConsumedMl: { type: Number, required: true },
    waterTargetMl: { type: Number, required: true },
    waterLeftMl: { type: Number, required: true },
    waterProgress: { type: Number, required: true },
    waterBaseMl: { type: Number, required: true },
    waterTrainingExtraMl: { type: Number, required: true },
    goalForm: { type: Object, required: true },
    quickDrinkAmounts: { type: Array, default: () => [] },
    drinkVessels: { type: Array, default: () => [] },
    drinkForm: { type: Object, required: true },
    drinkSelectOpen: { type: Boolean, default: false },
    drinkSearchQuery: { type: String, default: '' },
    filteredDrinkOptions: { type: Array, default: () => [] },
    customDrinkNameAvailable: { type: Boolean, default: false },
    drinkError: { type: String, default: '' },
    setActiveSection: { type: Function, required: true },
    setDrinkSelectOpen: { type: Function, required: true },
    setDrinkSelectElement: { type: Function, required: true },
    updateDrinkSearch: { type: Function, required: true },
    selectDrinkOption: { type: Function, required: true },
    useCustomDrinkName: { type: Function, required: true },
    clearDrinkSearch: { type: Function, required: true },
    submitDrink: { type: Function, required: true },
    submitDrinkVessel: { type: Function, required: true },
})
</script>

<template>
    <form class="rounded-2xl border border-border bg-card p-3 sm:p-4 lg:p-5" @submit.prevent="submitDrink()">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Trinken') }}</p>
                <div class="mt-1 flex items-center gap-2">
                    <h2 class="text-xl font-black text-primary sm:text-2xl">{{ tAuto(`${formatWater(waterConsumedMl)} heute`) }}</h2>
                    <button
                        type="button"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border bg-inputBg text-secondary hover:border-air-blue hover:text-primary sm:h-9 sm:w-9"
                        :title="tAuto('Wasserziel einstellen')"
                        :aria-label="tAuto('Wasserziel einstellen')"
                        @click="setActiveSection('goals')"
                    >
                        <i class="las la-cog text-xl"></i>
                    </button>
                </div>
                <p class="mt-1 text-sm leading-5 text-secondary sm:leading-6">
                    {{ tAuto(`Ziel: ${formatWater(waterTargetMl)}. Noch ${formatWater(waterLeftMl)} offen.`) }}
                </p>
            </div>
        </div>

        <div class="mt-3 rounded-2xl border border-air-blue/25 bg-air-blue/10 p-3 sm:mt-5 sm:p-4">
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-bold text-primary">{{ waterProgress }}%</span>
                <span class="text-sm font-semibold text-secondary">{{ formatWater(waterConsumedMl) }} / {{ formatWater(waterTargetMl) }}</span>
            </div>
            <div class="mt-2 h-3 overflow-hidden rounded-full bg-card sm:mt-3 sm:h-4">
                <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-air-blue" :style="{ width: `${waterProgress}%` }"></div>
            </div>
        </div>

        <div class="mt-4 hidden gap-3 md:grid md:grid-cols-3">
            <div class="rounded-2xl border border-border bg-inputBg p-3">
                <p class="text-xs font-bold uppercase text-secondary">{{ tAuto('Basis') }}</p>
                <p class="mt-1 text-lg font-black text-primary">{{ formatWater(waterBaseMl) }}</p>
                <p class="mt-1 text-xs leading-5 text-secondary">
                    {{ goalForm.body_weight_kg ? tAuto('Aus deinem Gewicht berechnet.') : tAuto('Standard, bis Gewicht gepflegt ist.') }}
                </p>
            </div>
            <div class="rounded-2xl border border-border bg-inputBg p-3">
                <p class="text-xs font-bold uppercase text-secondary">{{ tAuto('Training heute') }}</p>
                <p class="mt-1 text-lg font-black text-primary">+{{ formatWater(waterTrainingExtraMl) }}</p>
                <p class="mt-1 text-xs leading-5 text-secondary">{{ tAuto('Dauer, Sportart und Intensität werden berücksichtigt.') }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-inputBg p-3">
                <p class="text-xs font-bold uppercase text-secondary">{{ tAuto('Modus') }}</p>
                <p class="mt-1 text-lg font-black text-primary">{{ goalForm.water_target_mode === 'auto' ? tAuto('Automatisch') : tAuto('Manuell') }}</p>
                <p class="mt-1 text-xs leading-5 text-secondary">{{ tAuto('Änderbar unter Ziele.') }}</p>
            </div>
        </div>

        <div class="mt-4 sm:hidden">
            <p class="text-sm font-bold text-primary">{{ tAuto('Schnelle Menge') }}</p>
            <div class="mt-3 grid grid-cols-4 gap-2">
                <button
                    v-for="amount in quickDrinkAmounts"
                    :key="`mobile-${amount}`"
                    type="button"
                    class="rounded-xl border border-border bg-inputBg px-2 py-3 text-sm font-black text-primary hover:border-air-blue hover:bg-air-blue/10 disabled:opacity-60"
                    :disabled="drinkForm.processing"
                    @click="submitDrink(amount)"
                >
                    +{{ amount }}
                </button>
            </div>
        </div>

        <div class="mt-4 sm:mt-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-primary">{{ tAuto('Nach Tasse oder Glas eintragen') }}</p>
                    <p class="hidden text-xs leading-5 text-secondary sm:block">{{ tAuto('Wähle die Größe, die am besten passt. Die Menge wird direkt gespeichert.') }}</p>
                </div>
                <span class="hidden text-xs font-bold uppercase text-air-blue sm:inline">{{ tAuto('Airmius Quick Drink') }}</span>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
                <button
                    v-for="vessel in drinkVessels"
                    :key="vessel.key"
                    type="button"
                    class="group rounded-2xl border bg-inputBg p-3 text-left transition hover:-translate-y-0.5 hover:bg-air-blue/10 disabled:opacity-60"
                    :class="vessel.ring"
                    :disabled="drinkForm.processing"
                    @click="submitDrinkVessel(vessel)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black text-primary">{{ tAuto(vessel.label) }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ tAuto(vessel.hint) }}</p>
                        </div>
                        <span class="rounded-full bg-card px-2.5 py-1 text-xs font-black text-air-blue">{{ vessel.amount }} ml</span>
                    </div>
                    <div class="mt-3 hidden items-end justify-center sm:flex">
                        <div class="relative h-24 w-16">
                            <div class="absolute left-2 top-2 h-20 w-11 overflow-hidden rounded-b-2xl rounded-t-md border-2 border-white/45 bg-white/10 shadow-inner">
                                <div
                                    class="absolute bottom-0 left-0 right-0 rounded-b-2xl bg-gradient-to-t opacity-95 transition-all group-hover:opacity-100"
                                    :class="vessel.gradient"
                                    :style="{ height: `${vessel.fill}%` }"
                                ></div>
                                <div class="absolute inset-x-1 top-2 h-2 rounded-full bg-white/30"></div>
                            </div>
                            <div class="absolute right-0 top-7 h-9 w-5 rounded-r-full border-2 border-l-0 border-white/40"></div>
                            <div class="absolute bottom-0 left-0 right-0 h-px bg-white/25"></div>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <div class="mt-5 hidden sm:block">
            <p class="text-sm font-bold text-primary">{{ tAuto('Oder schnelle Menge eintragen') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button
                    v-for="amount in quickDrinkAmounts"
                    :key="amount"
                    type="button"
                    class="rounded-xl border border-border bg-inputBg px-4 py-4 text-base font-black text-primary hover:border-air-blue hover:bg-air-blue/10 disabled:opacity-60"
                    :disabled="drinkForm.processing"
                    @click="submitDrink(amount)"
                >
                    +{{ amount }} ml
                </button>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1.2fr),minmax(0,0.8fr),auto]">
            <div :ref="setDrinkSelectElement" class="relative block text-sm font-bold text-primary">
                <span>{{ tAuto('Getränk') }}</span>
                <div class="mt-2 flex rounded-xl border border-border bg-inputBg focus-within:border-air-blue">
                    <input
                        :value="drinkSearchQuery"
                        class="min-w-0 flex-1 rounded-l-xl border-0 bg-transparent px-3 py-3 text-primary placeholder-secondary focus:ring-0"
                        :placeholder="tAuto('Getränk suchen oder eigenes schreiben')"
                        autocomplete="off"
                        @focus="setDrinkSelectOpen(true)"
                        @input="updateDrinkSearch($event.target.value)"
                        @keydown.escape="setDrinkSelectOpen(false)"
                        @keydown.enter.prevent="useCustomDrinkName"
                    >
                    <button
                        v-if="drinkSearchQuery"
                        type="button"
                        class="px-2 text-secondary hover:text-primary"
                        :title="tAuto('Leeren')"
                        @click="clearDrinkSearch"
                    >
                        <i class="las la-times"></i>
                    </button>
                    <button
                        type="button"
                        class="rounded-r-xl px-3 text-secondary hover:text-primary"
                        :title="tAuto('Getränke anzeigen')"
                        @click="setDrinkSelectOpen(!drinkSelectOpen)"
                    >
                        <i class="las la-angle-down"></i>
                    </button>
                </div>

                <div
                    v-if="drinkSelectOpen"
                    class="absolute left-0 right-0 z-40 mt-2 max-h-72 overflow-y-auto rounded-2xl border border-border bg-card p-2 shadow-2xl shadow-black/30"
                >
                    <button
                        v-if="customDrinkNameAvailable"
                        type="button"
                        class="mb-2 flex w-full items-center gap-3 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-3 text-left hover:bg-air-blue/15"
                        @mousedown.prevent="useCustomDrinkName"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-air-blue/20 text-air-blue">
                            <i class="las la-plus"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-black text-primary">{{ tAuto(`"${drinkSearchQuery.trim()}" verwenden`) }}</span>
                            <span class="block text-xs font-semibold text-secondary">{{ tAuto('Eigenes Getränk speichern') }}</span>
                        </span>
                    </button>

                    <button
                        v-for="drink in filteredDrinkOptions"
                        :key="drink.label"
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-3 text-left hover:bg-muted"
                        @mousedown.prevent="selectDrinkOption(drink)"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-black text-primary">{{ tAuto(drink.label) }}</span>
                            <span class="block text-xs font-semibold text-secondary">{{ tAuto(drink.category) }}</span>
                        </span>
                        <span class="shrink-0 rounded-full bg-inputBg px-2.5 py-1 text-xs font-black text-air-blue">{{ drink.amount }} ml</span>
                    </button>

                    <div v-if="!filteredDrinkOptions.length && !customDrinkNameAvailable" class="rounded-xl border border-dashed border-border px-3 py-4 text-sm font-semibold text-secondary">
                        {{ tAuto('Kein Getränk gefunden. Schreibe einfach dein eigenes.') }}
                    </div>
                </div>
            </div>
            <label class="block text-sm font-bold text-primary">{{ tAuto('Menge in ml') }}
                <input v-model="drinkForm.amount_ml" type="number" min="1" max="5000" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="250">
            </label>
            <button type="submit" class="self-end rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="drinkForm.processing">
                {{ tAuto('Eintragen') }}
            </button>
        </div>
        <input v-model="drinkForm.eaten_on" type="hidden">

        <p v-if="drinkError || drinkForm.errors.amount_ml || drinkForm.errors.title" class="mt-3 rounded-xl border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
            {{ tAuto(drinkError || 'Bitte Menge zwischen 1 und 5000 ml eingeben.') }}
        </p>
    </form>
</template>
