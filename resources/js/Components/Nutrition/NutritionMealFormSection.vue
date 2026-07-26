<script setup>
import { computed } from 'vue'

const props = defineProps({
    editingMealId: { type: [Number, String], default: null },
    mealForm: { type: Object, required: true },
    foodMealTypes: { type: Array, default: () => [] },
    recentTraining: { type: Array, default: () => [] },
    showAdvancedMeal: { type: Boolean, default: false },
    aiMealImageAvailable: { type: Boolean, default: false },
    aiMealProviderLabel: { type: String, default: '' },
    aiMealImageAccessReason: { type: String, default: '' },
    aiMealAnalyzing: { type: Boolean, default: false },
    aiMealError: { type: [String, Object], default: null },
    aiMealSuggestion: { type: Object, default: null },
    aiMealConsent: { type: Boolean, default: false },
    foodSearchQuery: { type: String, default: '' },
    foodBarcode: { type: String, default: '' },
    foodLookupLoading: { type: Boolean, default: false },
    foodLookupError: { type: String, default: '' },
    foodSearchResults: { type: Array, default: () => [] },
    todaySummary: { type: Object, default: () => ({}) },
    formatNumber: { type: Function, required: true },
    tAuto: { type: Function, required: true },
})

const emit = defineEmits([
    'submit-meal',
    'reset-meal-form',
    'toggle-advanced-meal',
    'set-ai-meal-image-input',
    'ai-meal-image-selected',
    'analyze-meal-image',
    'update:aiMealConsent',
    'apply-ai-meal-suggestion',
    'add-item-row',
    'remove-item-row',
    'update:foodSearchQuery',
    'search-foods',
    'update:foodBarcode',
    'lookup-barcode',
    'apply-food-result',
])

const aiMealConsentModel = computed({
    get: () => props.aiMealConsent,
    set: (value) => emit('update:aiMealConsent', value),
})

const foodSearchQueryModel = computed({
    get: () => props.foodSearchQuery,
    set: (value) => emit('update:foodSearchQuery', value),
})

const foodBarcodeModel = computed({
    get: () => props.foodBarcode,
    set: (value) => emit('update:foodBarcode', value),
})

const setAiMealImageInput = (element) => {
    emit('set-ai-meal-image-input', element)
}
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-[minmax(0,0.85fr),minmax(340px,0.65fr)]">
        <form class="rounded-2xl border border-border bg-card p-4 lg:p-5" @submit.prevent="emit('submit-meal')">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Mahlzeit') }}</p>
                    <h2 class="mt-1 text-xl font-bold text-primary">{{ editingMealId ? tAuto('Mahlzeit bearbeiten') : tAuto('Schnell erfassen') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ tAuto('Nur Name und Kalorien sind Pflicht. Details bleiben optional.') }}</p>
                </div>
                <button v-if="editingMealId" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted" @click="emit('reset-meal-form')">
                    {{ tAuto('Neu') }}
                </button>
            </div>

            <section class="mt-4 rounded-2xl border border-air-blue/25 bg-air-blue/10 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('KI-Fotoanalyse') }}</p>
                        <h3 class="mt-1 text-base font-black text-primary">{{ tAuto('Kalorien aus Bild schützen') }}</h3>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            {{ tAuto('Bild wird verkleinert, EXIF wird entfernt. Ergebnis bleibt ein Vorschlag und muss von dir bestätigt werden.') }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full bg-card px-3 py-1 text-xs font-black text-air-blue">
                        {{ aiMealImageAvailable ? aiMealProviderLabel : tAuto('Pro-Funktion') }}
                    </span>
                </div>

                <p v-if="!aiMealImageAvailable" class="mt-3 rounded-xl border border-warning/30 bg-warning/10 p-3 text-sm font-semibold text-warning">
                    {{ aiMealImageAccessReason }}
                </p>

                <div class="mt-3 grid gap-3 lg:grid-cols-[minmax(0,1fr),auto]">
                    <label class="block text-sm font-bold text-primary">
                        {{ tAuto('Essensbild') }}
                        <input
                            :ref="setAiMealImageInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-sm text-primary file:mr-3 file:rounded-lg file:border-0 file:bg-buttonPrimary file:px-3 file:py-2 file:text-sm file:font-bold file:text-buttonTextPrimary"
                            :disabled="aiMealAnalyzing"
                            @change="emit('ai-meal-image-selected', $event)"
                        >
                    </label>
                    <button
                        type="button"
                        class="self-end rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60"
                        :disabled="aiMealAnalyzing || !aiMealImageAvailable"
                        @click="emit('analyze-meal-image')"
                    >
                        <span v-if="aiMealAnalyzing" class="inline-flex items-center gap-2">
                            <i class="las la-sync-alt animate-spin"></i>
                            {{ tAuto('Analysiere') }}
                        </span>
                        <span v-else>{{ tAuto('Bild analysieren') }}</span>
                    </button>
                </div>

                <label class="mt-3 flex items-start gap-3 rounded-xl border border-border bg-card/70 p-3 text-sm text-secondary">
                    <input v-model="aiMealConsentModel" type="checkbox" class="mt-1 rounded border-border bg-inputBg text-air-blue">
                    <span>{{ tAuto('Ich möchte dieses Bild zur KI-Analyse senden. Es wird nur für den Vorschlag genutzt und nicht automatisch als Mahlzeit gespeichert.') }}</span>
                </label>

                <p v-if="aiMealError" class="mt-3 rounded-xl border border-danger/30 bg-danger/10 p-3 text-sm font-semibold text-danger">
                    {{ aiMealError }}
                </p>

                <div v-if="aiMealSuggestion" class="mt-3 rounded-xl border border-air-blue/30 bg-card p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-black text-primary">{{ aiMealSuggestion.title }}</p>
                            <p class="mt-1 text-xs leading-5 text-secondary">
                                {{ tAuto(`${formatNumber(aiMealSuggestion.calories || 0)} kcal · ${formatNumber(aiMealSuggestion.protein_g || 0, 1)} g Protein · Sicherheit ${Math.round((aiMealSuggestion.confidence || 0) * 100)}%`) }}
                            </p>
                        </div>
                        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted" @click="emit('apply-ai-meal-suggestion')">
                            {{ tAuto('Erneut übernehmen') }}
                        </button>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-secondary">
                        {{ tAuto(aiMealSuggestion.notes || 'Bitte Mengen prüfen, bevor du speicherst.') }}
                    </p>
                </div>
            </section>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
                <button
                    v-for="type in foodMealTypes"
                    :key="type.key"
                    type="button"
                    :class="[
                        'rounded-xl border px-3 py-3 text-start transition',
                        mealForm.meal_type === type.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:bg-muted'
                    ]"
                    @click="mealForm.meal_type = type.key"
                >
                    <i :class="[type.icon, 'text-lg']"></i>
                    <span class="mt-1 block text-xs font-bold">{{ tAuto(type.label) }}</span>
                </button>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="block text-sm font-bold text-primary sm:col-span-2">{{ tAuto('Was hast du gegessen?') }}
                    <input v-model="mealForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="tAuto('z. B. Bowl, Banane, Proteinshake')" required>
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Kalorien') }}
                    <input v-model="mealForm.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="tAuto('z. B. 520')" required>
                </label>
                <label class="block text-sm font-bold text-primary">{{ tAuto('Protein optional') }}
                    <input v-model="mealForm.protein_g" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="tAuto('z. B. 32')">
                </label>
            </div>

            <button type="button" class="mt-4 flex w-full items-center justify-between rounded-xl border border-border bg-inputBg px-4 py-3 text-sm font-bold text-primary hover:bg-muted" @click="emit('toggle-advanced-meal')">
                <span>{{ tAuto('Mehr Details') }}</span>
                <i :class="[showAdvancedMeal ? 'las la-angle-up' : 'las la-angle-down', 'text-lg']"></i>
            </button>

            <div v-if="showAdvancedMeal" class="mt-4 space-y-4 rounded-2xl border border-border bg-inputBg p-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm font-bold text-primary">{{ tAuto('Datum') }}
                        <input v-model="mealForm.eaten_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary">{{ tAuto('Training-Bezug') }}
                        <select v-model="mealForm.training_context" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                            <option value="">{{ tAuto('Ohne Bezug') }}</option>
                            <option value="pre_workout">{{ tAuto('Vor dem Training') }}</option>
                            <option value="post_workout">{{ tAuto('Nach dem Training') }}</option>
                            <option v-for="log in recentTraining" :key="log.id" :value="`training_log:${log.id}`">{{ log.title }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-bold text-primary">{{ tAuto('Kohlenhydrate g') }}
                        <input v-model="mealForm.carbs_g" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary">{{ tAuto('Fett g') }}
                        <input v-model="mealForm.fat_g" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary">{{ tAuto('Wasser ml') }}
                        <input v-model="mealForm.water_ml" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                    </label>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-bold text-primary">{{ tAuto('Zutaten / Portionen') }}</p>
                        <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-primary hover:bg-muted" @click="emit('add-item-row')">
                            {{ tAuto('Zeile') }}
                        </button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="(item, index) in mealForm.items" :key="index" class="grid gap-2 sm:grid-cols-[minmax(0,1fr),130px,auto]">
                            <input v-model="item.name" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="tAuto('Lebensmittel')">
                            <input v-model="item.amount" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="tAuto('Menge')">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-danger hover:bg-danger/10" @click="emit('remove-item-row', index)">
                                <i class="las la-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <label class="block text-sm font-bold text-primary">{{ tAuto('Notiz') }}
                    <textarea v-model="mealForm.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary" :placeholder="tAuto('Gefühl, Hunger, Timing, Besonderheiten')"></textarea>
                </label>
            </div>

            <div v-if="Object.keys(mealForm.errors).length" class="mt-4 rounded-xl border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                {{ tAuto('Bitte prüfe die Eingaben.') }}
            </div>

            <button type="submit" class="mt-4 w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="mealForm.processing">
                {{ editingMealId ? tAuto('Speichern') : tAuto('Hinzufügen') }}
            </button>
        </form>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-air-blue/25 bg-air-blue/10 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Suche') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">{{ tAuto('Lebensmittel finden') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tAuto('Name oder Barcode suchen, dann übernehmen.') }}</p>
                    </div>
                    <span class="rounded-full bg-card px-3 py-1 text-[11px] font-bold text-primary">{{ tAuto('kostenlos') }}</span>
                </div>
                <div class="mt-4 grid gap-2">
                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr),auto]">
                        <input v-model="foodSearchQueryModel" class="rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="tAuto('z. B. Skyr, Banane')">
                        <button type="button" class="rounded-xl border border-air-blue/40 px-4 py-2 text-sm font-bold text-primary hover:bg-air-blue/15 disabled:opacity-60" :disabled="foodLookupLoading" @click="emit('search-foods')">
                            {{ tAuto('Suchen') }}
                        </button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr),auto]">
                        <input v-model="foodBarcodeModel" class="rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="tAuto('Barcode')">
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted disabled:opacity-60" :disabled="foodLookupLoading" @click="emit('lookup-barcode')">
                            {{ tAuto('Barcode') }}
                        </button>
                    </div>
                </div>
                <p v-if="foodLookupError" class="mt-3 rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-xs font-semibold text-danger">
                    {{ foodLookupError }}
                </p>
                <div v-if="foodSearchResults.length" class="mt-3 space-y-2">
                    <article v-for="food in foodSearchResults" :key="food.code || food.title" class="flex gap-3 rounded-xl border border-border bg-card p-3">
                        <img v-if="food.image_url" :src="food.image_url" alt="" class="h-12 w-12 rounded-lg object-cover">
                        <div v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-inputBg">
                            <i class="las la-utensils text-xl text-air-blue"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-primary">{{ food.title }}</p>
                            <p class="truncate text-xs text-secondary">{{ tAuto(food.brand || 'Open Food Facts') }} - {{ food.quantity_label }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ tAuto(`${food.calories} kcal - ${food.protein_g} g Protein`) }}</p>
                        </div>
                        <button type="button" class="shrink-0 rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-bold text-buttonTextPrimary" @click="emit('apply-food-result', food)">
                            {{ tAuto('Übernehmen') }}
                        </button>
                    </article>
                </div>
            </section>

            <section class="rounded-2xl border border-border bg-card p-4">
                <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Heute bisher') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <span class="rounded-xl border border-border bg-inputBg p-3 text-xs text-secondary"><b class="block text-lg text-primary">{{ formatNumber(todaySummary.calories || 0) }}</b>kcal</span>
                    <span class="rounded-xl border border-border bg-inputBg p-3 text-xs text-secondary"><b class="block text-lg text-primary">{{ formatNumber(todaySummary.protein_g || 0, 1) }} g</b>{{ tAuto('Protein') }}</span>
                </div>
            </section>
        </aside>
    </section>
</template>

