<script setup>
defineProps({
    dietStyles: { type: Array, default: () => [] },
    formatWater: { type: Function, required: true },
    goalForm: { type: Object, required: true },
    goalTypes: { type: Array, default: () => [] },
    saveGoal: { type: Function, required: true },
    selectedGoal: { type: Object, default: () => ({}) },
    suggestedWaterTargetMl: { type: Number, default: 0 },
    tAuto: { type: Function, required: true },
    tips: { type: Array, default: () => [] },
    waterBaseMl: { type: Number, default: 0 },
    waterRecommendation: { type: Object, default: () => ({}) },
    waterTargetMl: { type: Number, default: 0 },
    waterTrainingExtraMl: { type: Number, default: 0 },
})
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-[minmax(0,0.8fr),minmax(320px,0.6fr)]">
        <form class="rounded-2xl border border-border bg-card p-4 lg:p-5" @submit.prevent="saveGoal">
            <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Ziel') }}</p>
            <h2 class="mt-1 text-xl font-bold text-primary">{{ tAuto(selectedGoal.label || 'Ernährungsziel') }}</h2>
            <p class="mt-1 text-sm text-secondary">{{ tAuto(selectedGoal.hint) }}</p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="block text-sm font-bold text-primary">{{ tAuto('Ziel') }}
                    <select v-model="goalForm.goal_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                        <option v-for="goalType in goalTypes" :key="goalType.key" :value="goalType.key">{{ tAuto(goalType.label) }}</option>
                    </select>
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Ernährungsstil') }}
                    <select v-model="goalForm.diet_style" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                        <option v-for="style in dietStyles" :key="style.key" :value="style.key">{{ tAuto(style.label) }}</option>
                    </select>
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Kalorienziel') }}
                    <input v-model="goalForm.daily_calories_target" type="number" min="800" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Proteinziel') }}
                    <input v-model="goalForm.protein_target_g" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Kohlenhydrate') }}
                    <input v-model="goalForm.carbs_target_g" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Fett') }}
                    <input v-model="goalForm.fat_target_g" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Trinkziel') }}
                    <select v-model="goalForm.water_target_mode" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                        <option value="auto">{{ tAuto('Automatisch nach Gewicht und Training') }}</option>
                        <option value="manual">{{ tAuto('Manuell festlegen') }}</option>
                    </select>
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Gewicht kg') }}
                    <input v-model="goalForm.body_weight_kg" type="number" min="20" max="300" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="tAuto('z. B. 75')">
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Manuelles Wasserziel ml') }}
                    <input v-model="goalForm.water_target_ml" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :disabled="goalForm.water_target_mode === 'auto'">
                    <span class="mt-1 block text-xs font-semibold text-secondary">
                        {{ goalForm.water_target_mode === 'auto' ? tAuto(`Heute empfohlen: ${formatWater(suggestedWaterTargetMl)}`) : tAuto('Dieses Ziel bleibt jeden Tag gleich.') }}
                    </span>
                </label>
                <label class="block text-sm font-bold text-primary sm:col-span-2">{{ tAuto('Notiz') }}
                    <textarea v-model="goalForm.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="tAuto('Allergien, Vorlieben, Trainer-Hinweise')"></textarea>
                </label>
            </div>
            <button type="submit" class="mt-4 w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="goalForm.processing">
                {{ tAuto('Ziel speichern') }}
            </button>
        </form>

        <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
            <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Trinkziel heute') }}</p>
            <div class="mt-3 rounded-2xl border border-air-blue/25 bg-air-blue/10 p-4">
                <p class="text-3xl font-black text-primary">{{ formatWater(waterTargetMl) }}</p>
                <p class="mt-2 text-sm leading-6 text-secondary">
                    {{ tAuto('Basis') }} {{ formatWater(waterBaseMl) }} + {{ tAuto('Training') }} {{ formatWater(waterTrainingExtraMl) }}.
                    {{ goalForm.water_target_mode === 'auto' ? tAuto('Airmius passt das Tagesziel automatisch an.') : tAuto('Manueller Modus nutzt dein festes Ziel.') }}
                </p>
            </div>
            <div v-if="waterRecommendation.details?.length" class="mt-3 space-y-2">
                <article v-for="detail in waterRecommendation.details" :key="`water-training-${detail.id}`" class="rounded-xl border border-border bg-inputBg p-3">
                    <p class="text-sm font-bold text-primary">{{ tAuto(detail.title) }}</p>
                    <p class="mt-1 text-xs text-secondary">
                        {{ detail.duration_minutes || 0 }} {{ tAuto('min') }} - {{ tAuto(detail.sport_type || 'Training') }} - +{{ formatWater(detail.extra_ml) }}
                    </p>
                </article>
            </div>
            <p v-else class="mt-3 rounded-xl border border-border bg-inputBg p-3 text-sm leading-6 text-secondary">
                {{ tAuto('Heute ist kein Training im Trinkziel eingerechnet.') }}
            </p>

            <p class="mt-5 text-xs font-bold uppercase text-air-blue">{{ tAuto('Tipps') }}</p>
            <div class="mt-3 space-y-2">
                <p v-for="tip in tips" :key="tip" class="rounded-xl border border-border bg-inputBg p-3 text-sm leading-6 text-secondary">
                    {{ tAuto(tip) }}
                </p>
            </div>
        </section>
    </section>
</template>
