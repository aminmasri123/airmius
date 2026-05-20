<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    selectedDate: { type: String, required: true },
    goal: { type: Object, default: () => ({}) },
    meals: { type: Array, default: () => [] },
    todaySummary: { type: Object, default: () => ({}) },
    weeklySummaries: { type: Array, default: () => [] },
    catalog: { type: Object, default: () => ({}) },
    recipes: { type: Array, default: () => [] },
    tips: { type: Array, default: () => [] },
    trainingSuggestions: { type: Array, default: () => [] },
    recentTraining: { type: Array, default: () => [] },
})

const selectedDateValue = ref(props.selectedDate)
const editingMealId = ref(null)
const deleteCandidate = ref(null)
const recipeFilter = ref('all')
const foodSearchQuery = ref('')
const foodBarcode = ref('')
const foodLookupLoading = ref(false)
const foodLookupError = ref('')
const foodSearchResults = ref([])

const emptyItems = () => ([
    { name: '', amount: '' },
])

const mealForm = useForm({
    eaten_on: props.selectedDate,
    meal_type: 'breakfast',
    title: '',
    calories: '',
    protein_g: '',
    carbs_g: '',
    fat_g: '',
    fiber_g: '',
    sugar_g: '',
    water_ml: '',
    source: 'manual',
    training_context: '',
    items: emptyItems(),
    notes: '',
})

const goalForm = useForm({
    goal_type: props.goal?.goal_type || 'maintain',
    daily_calories_target: props.goal?.daily_calories_target || 2200,
    protein_target_g: props.goal?.protein_target_g || 120,
    carbs_target_g: props.goal?.carbs_target_g || 260,
    fat_target_g: props.goal?.fat_target_g || 75,
    water_target_ml: props.goal?.water_target_ml || 2500,
    diet_style: props.goal?.diet_style || 'balanced',
    allergies: props.goal?.allergies || [],
    notes: props.goal?.notes || '',
})

const mealTypes = computed(() => props.catalog?.meal_types || [])
const goalTypes = computed(() => props.catalog?.goal_types || [])
const dietStyles = computed(() => props.catalog?.diet_styles || [])
const sortedMeals = computed(() => [...(props.meals || [])].sort((a, b) => (a.meal_type || '').localeCompare(b.meal_type || '')))
const selectedGoal = computed(() => goalTypes.value.find((goal) => goal.key === goalForm.goal_type) || goalTypes.value[0] || {})
const maxWeekCalories = computed(() => Math.max(1, ...props.weeklySummaries.map((day) => Number(day.calories || 0))))
const caloriesLeft = computed(() => Math.max(0, Number(goalForm.daily_calories_target || 0) - Number(props.todaySummary.calories || 0)))
const filteredRecipes = computed(() => {
    if (recipeFilter.value === 'goal') {
        return props.recipes.filter((recipe) => recipe.goal_type === goalForm.goal_type)
    }

    if (recipeFilter.value === 'style') {
        return props.recipes.filter((recipe) => (recipe.diet_styles || []).includes(goalForm.diet_style))
    }

    return props.recipes
})

const macroCards = computed(() => [
    {
        key: 'calories',
        label: 'Kalorien',
        value: Number(props.todaySummary.calories || 0),
        target: Number(goalForm.daily_calories_target || 0),
        unit: 'kcal',
        icon: 'las la-fire',
        color: 'from-rose-500 to-orange-400',
    },
    {
        key: 'protein_g',
        label: 'Protein',
        value: Number(props.todaySummary.protein_g || 0),
        target: Number(goalForm.protein_target_g || 0),
        unit: 'g',
        icon: 'las la-dumbbell',
        color: 'from-sky-500 to-cyan-400',
    },
    {
        key: 'carbs_g',
        label: 'Kohlenhydrate',
        value: Number(props.todaySummary.carbs_g || 0),
        target: Number(goalForm.carbs_target_g || 0),
        unit: 'g',
        icon: 'las la-bolt',
        color: 'from-amber-400 to-yellow-300',
    },
    {
        key: 'fat_g',
        label: 'Fett',
        value: Number(props.todaySummary.fat_g || 0),
        target: Number(goalForm.fat_target_g || 0),
        unit: 'g',
        icon: 'las la-seedling',
        color: 'from-emerald-500 to-lime-400',
    },
])

const progressValue = (value, target) => {
    const max = Number(target || 0)
    if (max <= 0) return 0

    return Math.min(100, Math.round((Number(value || 0) / max) * 100))
}

const formatNumber = (value, digits = 0) => {
    const number = Number(value || 0)

    return number.toLocaleString('de-DE', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    })
}

const mealTypeMeta = (key) => mealTypes.value.find((type) => type.key === key) || { label: key || 'Mahlzeit', icon: 'las la-utensils' }

const changeDate = () => {
    router.get(route('auth.nutrition.index'), { date: selectedDateValue.value }, {
        preserveScroll: true,
        preserveState: false,
    })
}

const addItemRow = () => {
    mealForm.items.push({ name: '', amount: '' })
}

const removeItemRow = (index) => {
    mealForm.items.splice(index, 1)
    if (!mealForm.items.length) {
        mealForm.items = emptyItems()
    }
}

const resetMealForm = () => {
    editingMealId.value = null
    mealForm.reset()
    mealForm.eaten_on = props.selectedDate
    mealForm.meal_type = 'breakfast'
    mealForm.source = 'manual'
    mealForm.items = emptyItems()
}

const submitMeal = () => {
    const options = {
        preserveScroll: true,
        onSuccess: resetMealForm,
    }

    if (editingMealId.value) {
        mealForm.patch(route('auth.nutrition.meals.update', editingMealId.value), options)
        return
    }

    mealForm.post(route('auth.nutrition.meals.store'), options)
}

const editMeal = (meal) => {
    editingMealId.value = meal.id
    mealForm.eaten_on = meal.eaten_on || props.selectedDate
    mealForm.meal_type = meal.meal_type || 'snack'
    mealForm.title = meal.title || ''
    mealForm.calories = meal.calories ?? ''
    mealForm.protein_g = meal.protein_g ?? ''
    mealForm.carbs_g = meal.carbs_g ?? ''
    mealForm.fat_g = meal.fat_g ?? ''
    mealForm.fiber_g = meal.fiber_g ?? ''
    mealForm.sugar_g = meal.sugar_g ?? ''
    mealForm.water_ml = meal.water_ml ?? ''
    mealForm.source = meal.source || 'manual'
    mealForm.training_context = meal.training_context || ''
    mealForm.items = meal.items?.length ? meal.items.map((item) => ({ name: item.name || '', amount: item.amount || '' })) : emptyItems()
    mealForm.notes = meal.notes || ''
}

const saveGoal = () => {
    goalForm.patch(route('auth.nutrition.goal.update'), { preserveScroll: true })
}

const applyRecipe = (recipe) => {
    editingMealId.value = null
    mealForm.eaten_on = props.selectedDate
    mealForm.meal_type = recipe.category === 'Vor dem Lauf' ? 'breakfast' : 'lunch'
    mealForm.title = recipe.title
    mealForm.calories = recipe.calories
    mealForm.protein_g = recipe.protein_g
    mealForm.carbs_g = recipe.carbs_g
    mealForm.fat_g = recipe.fat_g
    mealForm.source = 'recipe'
    mealForm.items = (recipe.ingredients || []).map((name) => ({ name, amount: '' }))
    mealForm.notes = `Rezept: ${(recipe.steps || []).join(' ')}`
}

const applyTrainingSuggestion = (suggestion) => {
    mealForm.meal_type = suggestion.meal_type || 'snack'
    mealForm.training_context = suggestion.training_context || ''
    mealForm.notes = suggestion.body || ''
}

const applyFoodResult = (food) => {
    editingMealId.value = null
    mealForm.eaten_on = props.selectedDate
    mealForm.title = food.brand ? `${food.title} (${food.brand})` : food.title
    mealForm.calories = food.calories ?? ''
    mealForm.protein_g = food.protein_g ?? ''
    mealForm.carbs_g = food.carbs_g ?? ''
    mealForm.fat_g = food.fat_g ?? ''
    mealForm.fiber_g = food.fiber_g ?? ''
    mealForm.sugar_g = food.sugar_g ?? ''
    mealForm.source = food.code ? 'barcode' : 'manual'
    mealForm.items = [{ name: food.title, amount: food.quantity_label || food.serving_size || '1 Portion / 100 g' }]
    mealForm.notes = `Quelle: ${food.attribution || 'Open Food Facts'}. Werte bitte pruefen, da offene Daten unvollstaendig sein koennen.`
}

const searchFoods = async () => {
    foodLookupError.value = ''
    foodSearchResults.value = []

    if (foodSearchQuery.value.trim().length < 2) {
        foodLookupError.value = 'Bitte mindestens 2 Zeichen eingeben.'
        return
    }

    foodLookupLoading.value = true

    try {
        const response = await window.axios.get(route('auth.nutrition.foods.search'), {
            params: { q: foodSearchQuery.value.trim() },
        })
        foodSearchResults.value = response.data?.data || []
        if (!foodSearchResults.value.length) {
            foodLookupError.value = 'Keine passenden Lebensmittel gefunden.'
        }
    } catch (error) {
        foodLookupError.value = error.response?.data?.message || 'Lebensmittel-Suche ist gerade nicht verfuegbar.'
    } finally {
        foodLookupLoading.value = false
    }
}

const lookupBarcode = async () => {
    foodLookupError.value = ''
    foodSearchResults.value = []

    if (foodBarcode.value.trim().length < 6) {
        foodLookupError.value = 'Bitte einen gueltigen Barcode eingeben.'
        return
    }

    foodLookupLoading.value = true

    try {
        const response = await window.axios.get(route('auth.nutrition.foods.barcode'), {
            params: { barcode: foodBarcode.value.trim() },
        })
        if (response.data?.data) {
            foodSearchResults.value = [response.data.data]
            applyFoodResult(response.data.data)
        }
    } catch (error) {
        foodLookupError.value = error.response?.data?.message || 'Barcode konnte nicht gefunden werden.'
    } finally {
        foodLookupLoading.value = false
    }
}

const confirmDelete = () => {
    if (!deleteCandidate.value) return

    router.delete(route('auth.nutrition.meals.destroy', deleteCandidate.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteCandidate.value = null
        },
    })
}
</script>

<template>
    <Head title="Ernaehrung" />

    <div class="space-y-5">
        <section class="overflow-hidden rounded-2xl border border-border bg-card">
            <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1.3fr),minmax(300px,0.7fr)] lg:p-6">
                <div>
                    <p class="text-xs font-bold uppercase text-air-blue">Airmius Fuel</p>
                    <h1 class="mt-2 text-2xl font-bold leading-tight text-primary sm:text-3xl">
                        Ernaehrung, die zu deinem Training passt.
                    </h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                        Tracke Mahlzeiten, Makros und Wasser kostenlos. Foto-Kalorien und Barcode koennen spaeter als Anbieter-Funktion angebunden werden.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                            Kostenloses MVP
                        </span>
                        <span class="rounded-full border border-air-blue/30 bg-air-blue/10 px-3 py-1 text-xs font-bold text-air-blue">
                            Flutter/API vorbereitet
                        </span>
                        <span class="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-bold text-amber-200">
                            Keine externe Bild-KI aktiv
                        </span>
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-inputBg p-4">
                    <label class="text-xs font-bold uppercase text-secondary">Tag auswaehlen</label>
                    <div class="mt-2 flex gap-2">
                        <input v-model="selectedDateValue" type="date" class="min-w-0 flex-1 rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary" @change="changeDate">
                        <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" @click="changeDate">
                            Laden
                        </button>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-border bg-card p-3">
                            <p class="text-xs text-secondary">Noch offen</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ formatNumber(caloriesLeft) }} kcal</p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-3">
                            <p class="text-xs text-secondary">Wasser</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ formatNumber(todaySummary.water_ml || 0) }} ml</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="macro in macroCards" :key="macro.key" class="rounded-2xl border border-border bg-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-secondary">{{ macro.label }}</p>
                        <p class="mt-1 text-2xl font-bold text-primary">
                            {{ formatNumber(macro.value, macro.key === 'calories' ? 0 : 1) }}
                            <span class="text-sm text-secondary">{{ macro.unit }}</span>
                        </p>
                    </div>
                    <span :class="['flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg', macro.color]">
                        <i :class="[macro.icon, 'text-2xl']"></i>
                    </span>
                </div>
                <div class="mt-4 h-2 rounded-full bg-inputBg">
                    <div :class="['h-2 rounded-full bg-gradient-to-r', macro.color]" :style="{ width: `${progressValue(macro.value, macro.target)}%` }"></div>
                </div>
                <p class="mt-2 text-xs text-secondary">
                    Ziel: {{ formatNumber(macro.target, macro.key === 'calories' ? 0 : 0) }} {{ macro.unit }}
                </p>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(320px,0.95fr),minmax(0,1.1fr),minmax(320px,0.95fr)]">
            <form class="rounded-2xl border border-border bg-card p-4" @submit.prevent="submitMeal">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">Mahlzeit</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ editingMealId ? 'Mahlzeit bearbeiten' : 'Schnell erfassen' }}</h2>
                    </div>
                    <button v-if="editingMealId" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted" @click="resetMealForm">
                        Neu
                    </button>
                </div>

                <section class="mt-4 rounded-2xl border border-air-blue/25 bg-air-blue/10 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-air-blue">Lebensmittel finden</p>
                            <p class="mt-1 text-sm text-secondary">Open Food Facts: Suche per Name oder Barcode und pruefe die Werte vor dem Speichern.</p>
                        </div>
                        <span class="rounded-full bg-card px-3 py-1 text-[11px] font-bold text-primary">kostenlos</span>
                    </div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr),auto]">
                        <input v-model="foodSearchQuery" class="rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="z. B. Skyr, Banane, Proteinriegel">
                        <button type="button" class="rounded-xl border border-air-blue/40 px-4 py-2 text-sm font-bold text-primary hover:bg-air-blue/15 disabled:opacity-60" :disabled="foodLookupLoading" @click="searchFoods">
                            Suchen
                        </button>
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr),auto]">
                        <input v-model="foodBarcode" class="rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="Barcode eingeben">
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted disabled:opacity-60" :disabled="foodLookupLoading" @click="lookupBarcode">
                            Barcode
                        </button>
                    </div>
                    <p v-if="foodLookupError" class="mt-2 rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-xs font-semibold text-danger">
                        {{ foodLookupError }}
                    </p>
                    <div v-if="foodSearchResults.length" class="mt-3 space-y-2">
                        <article v-for="food in foodSearchResults" :key="food.code || food.title" class="flex gap-3 rounded-xl border border-border bg-card p-3">
                            <img v-if="food.image_url" :src="food.image_url" alt="" class="h-14 w-14 rounded-lg object-cover">
                            <div v-else class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-inputBg">
                                <i class="las la-utensils text-2xl text-air-blue"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-primary">{{ food.title }}</p>
                                <p class="truncate text-xs text-secondary">{{ food.brand || 'Open Food Facts' }} - {{ food.quantity_label }}</p>
                                <div class="mt-2 flex flex-wrap gap-1 text-[11px] text-secondary">
                                    <span class="rounded bg-inputBg px-2 py-1"><b class="text-primary">{{ food.calories }}</b> kcal</span>
                                    <span class="rounded bg-inputBg px-2 py-1"><b class="text-primary">{{ food.protein_g }}g</b> Protein</span>
                                    <span v-if="food.nutriscore_grade" class="rounded bg-inputBg px-2 py-1">Nutri {{ food.nutriscore_grade }}</span>
                                </div>
                            </div>
                            <button type="button" class="shrink-0 rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-bold text-buttonTextPrimary" @click="applyFoodResult(food)">
                                Uebernehmen
                            </button>
                        </article>
                    </div>
                </section>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm font-bold text-primary">Datum
                        <input v-model="mealForm.eaten_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary">Typ
                        <select v-model="mealForm.meal_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option v-for="type in mealTypes" :key="type.key" :value="type.key">{{ type.label }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-bold text-primary sm:col-span-2">Name
                        <input v-model="mealForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. Reis mit Haehnchen" required>
                    </label>
                    <label class="block text-sm font-bold text-primary">Kalorien
                        <input v-model="mealForm.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required>
                    </label>
                    <label class="block text-sm font-bold text-primary">Protein g
                        <input v-model="mealForm.protein_g" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required>
                    </label>
                    <label class="block text-sm font-bold text-primary">Kohlenhydrate g
                        <input v-model="mealForm.carbs_g" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required>
                    </label>
                    <label class="block text-sm font-bold text-primary">Fett g
                        <input v-model="mealForm.fat_g" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required>
                    </label>
                    <label class="block text-sm font-bold text-primary">Wasser ml
                        <input v-model="mealForm.water_ml" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary">Training-Bezug
                        <select v-model="mealForm.training_context" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            <option value="">Ohne Bezug</option>
                            <option value="pre_workout">Vor dem Training</option>
                            <option value="post_workout">Nach dem Training</option>
                            <option v-for="log in recentTraining" :key="log.id" :value="`training_log:${log.id}`">{{ log.title }}</option>
                        </select>
                    </label>
                </div>

                <div class="mt-4 rounded-xl border border-border bg-inputBg p-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-bold text-primary">Zutaten / Portionen</p>
                        <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-primary hover:bg-muted" @click="addItemRow">
                            Zeile
                        </button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="(item, index) in mealForm.items" :key="index" class="grid gap-2 sm:grid-cols-[minmax(0,1fr),130px,auto]">
                            <input v-model="item.name" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="Lebensmittel">
                            <input v-model="item.amount" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="Menge">
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-danger hover:bg-danger/10" @click="removeItemRow(index)">
                                <i class="las la-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <label class="mt-4 block text-sm font-bold text-primary">Notiz
                    <textarea v-model="mealForm.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Gefuehl, Hunger, Timing, Besonderheiten"></textarea>
                </label>

                <div v-if="Object.keys(mealForm.errors).length" class="mt-4 rounded-xl border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                    Bitte pruefe die Eingaben.
                </div>

                <button type="submit" class="mt-4 w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="mealForm.processing">
                    {{ editingMealId ? 'Mahlzeit speichern' : 'Mahlzeit hinzufuegen' }}
                </button>
            </form>

            <section class="rounded-2xl border border-border bg-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">Tageslog</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">Mahlzeiten am {{ selectedDate }}</h2>
                    </div>
                    <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-primary">{{ meals.length }} Eintraege</span>
                </div>

                <div class="mt-4 space-y-3">
                    <article v-for="meal in sortedMeals" :key="meal.id" class="rounded-2xl border border-border bg-inputBg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase text-secondary">
                                    <i :class="[mealTypeMeta(meal.meal_type).icon, 'me-1']"></i>
                                    {{ mealTypeMeta(meal.meal_type).label }}
                                </p>
                                <h3 class="mt-1 truncate text-lg font-bold text-primary">{{ meal.title }}</h3>
                                <p v-if="meal.notes" class="mt-1 line-clamp-2 text-sm text-secondary">{{ meal.notes }}</p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <button type="button" class="rounded-lg border border-border px-2.5 py-2 text-primary hover:bg-muted" title="Bearbeiten" @click="editMeal(meal)">
                                    <i class="las la-pen"></i>
                                </button>
                                <button type="button" class="rounded-lg border border-danger/30 px-2.5 py-2 text-danger hover:bg-danger/10" title="Loeschen" @click="deleteCandidate = meal">
                                    <i class="las la-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <span class="rounded-xl border border-border bg-card p-2 text-xs text-secondary"><b class="block text-base text-primary">{{ formatNumber(meal.calories) }}</b>kcal</span>
                            <span class="rounded-xl border border-border bg-card p-2 text-xs text-secondary"><b class="block text-base text-primary">{{ formatNumber(meal.protein_g, 1) }} g</b>Protein</span>
                            <span class="rounded-xl border border-border bg-card p-2 text-xs text-secondary"><b class="block text-base text-primary">{{ formatNumber(meal.carbs_g, 1) }} g</b>Carbs</span>
                            <span class="rounded-xl border border-border bg-card p-2 text-xs text-secondary"><b class="block text-base text-primary">{{ formatNumber(meal.fat_g, 1) }} g</b>Fett</span>
                        </div>
                    </article>

                    <div v-if="!meals.length" class="rounded-2xl border border-dashed border-border bg-inputBg p-8 text-center">
                        <i class="las la-apple-alt text-4xl text-air-blue"></i>
                        <p class="mt-3 text-lg font-bold text-primary">Noch keine Mahlzeit erfasst.</p>
                        <p class="mt-1 text-sm text-secondary">Starte mit einer groben Schaetzung. Perfektion kommt spaeter.</p>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-border bg-inputBg p-4">
                    <p class="text-sm font-bold text-primary">7-Tage-Verlauf</p>
                    <div class="mt-3 flex h-32 items-end gap-2">
                        <div v-for="day in weeklySummaries" :key="day.date" class="flex min-w-0 flex-1 flex-col items-center gap-2">
                            <div class="flex h-24 w-full items-end rounded-full bg-card px-1">
                                <div class="w-full rounded-full bg-gradient-to-t from-air-blue to-emerald-300" :style="{ height: `${Math.max(6, (Number(day.calories || 0) / maxWeekCalories) * 100)}%` }"></div>
                            </div>
                            <span class="text-[11px] font-bold text-secondary">{{ day.label }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-5">
                <form class="rounded-2xl border border-border bg-card p-4" @submit.prevent="saveGoal">
                    <p class="text-xs font-bold uppercase text-air-blue">Ziel</p>
                    <h2 class="mt-1 text-xl font-bold text-primary">{{ selectedGoal.label || 'Ernaehrungsziel' }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ selectedGoal.hint }}</p>

                    <div class="mt-4 grid gap-3">
                        <label class="block text-sm font-bold text-primary">Ziel
                            <select v-model="goalForm.goal_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option v-for="goalType in goalTypes" :key="goalType.key" :value="goalType.key">{{ goalType.label }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-bold text-primary">Ernaehrungsstil
                            <select v-model="goalForm.diet_style" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                                <option v-for="style in dietStyles" :key="style.key" :value="style.key">{{ style.label }}</option>
                            </select>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block text-sm font-bold text-primary">kcal
                                <input v-model="goalForm.daily_calories_target" type="number" min="800" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            </label>
                            <label class="block text-sm font-bold text-primary">Protein
                                <input v-model="goalForm.protein_target_g" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            </label>
                            <label class="block text-sm font-bold text-primary">Carbs
                                <input v-model="goalForm.carbs_target_g" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            </label>
                            <label class="block text-sm font-bold text-primary">Fett
                                <input v-model="goalForm.fat_target_g" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                            </label>
                        </div>
                        <label class="block text-sm font-bold text-primary">Wasserziel ml
                            <input v-model="goalForm.water_target_ml" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                        </label>
                        <label class="block text-sm font-bold text-primary">Notiz
                            <textarea v-model="goalForm.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Allergien, Vorlieben, Trainer-Hinweise"></textarea>
                        </label>
                    </div>
                    <button type="submit" class="mt-4 w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="goalForm.processing">
                        Ziel speichern
                    </button>
                </form>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-xs font-bold uppercase text-air-blue">Tipps</p>
                    <div class="mt-3 space-y-2">
                        <p v-for="tip in tips" :key="tip" class="rounded-xl border border-border bg-inputBg p-3 text-sm leading-6 text-secondary">
                            {{ tip }}
                        </p>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-xs font-bold uppercase text-air-blue">Training-Bezug</p>
                    <div class="mt-3 space-y-2">
                        <article v-for="suggestion in trainingSuggestions" :key="suggestion.title + suggestion.body" class="rounded-xl border border-border bg-inputBg p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-primary">{{ suggestion.title }}</p>
                                    <p class="mt-1 text-sm leading-6 text-secondary">{{ suggestion.body }}</p>
                                </div>
                                <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-primary hover:bg-muted" @click="applyTrainingSuggestion(suggestion)">
                                    Nutzen
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-air-blue">Rezepte</p>
                            <h2 class="mt-1 text-lg font-bold text-primary">Schnell uebernehmen</h2>
                        </div>
                        <i class="las la-utensils text-2xl text-air-blue"></i>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <button type="button" :class="['rounded-lg border px-2 py-2 text-xs font-bold', recipeFilter === 'all' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="recipeFilter = 'all'">
                            Alle
                        </button>
                        <button type="button" :class="['rounded-lg border px-2 py-2 text-xs font-bold', recipeFilter === 'goal' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="recipeFilter = 'goal'">
                            Ziel
                        </button>
                        <button type="button" :class="['rounded-lg border px-2 py-2 text-xs font-bold', recipeFilter === 'style' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="recipeFilter = 'style'">
                            Stil
                        </button>
                    </div>
                    <div class="mt-3 space-y-3">
                        <article v-for="recipe in filteredRecipes" :key="recipe.key" class="rounded-2xl border border-border bg-inputBg p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold uppercase text-secondary">{{ recipe.category }} - {{ recipe.prep_minutes }} min</p>
                                    <h3 class="mt-1 font-bold text-primary">{{ recipe.title }}</h3>
                                </div>
                                <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-primary hover:bg-muted" @click="applyRecipe(recipe)">
                                    Nutzen
                                </button>
                            </div>
                            <div class="mt-3 grid grid-cols-4 gap-2 text-center text-[11px] text-secondary">
                                <span class="rounded-lg bg-card p-2"><b class="block text-primary">{{ recipe.calories }}</b>kcal</span>
                                <span class="rounded-lg bg-card p-2"><b class="block text-primary">{{ recipe.protein_g }}g</b>P</span>
                                <span class="rounded-lg bg-card p-2"><b class="block text-primary">{{ recipe.carbs_g }}g</b>C</span>
                                <span class="rounded-lg bg-card p-2"><b class="block text-primary">{{ recipe.fat_g }}g</b>F</span>
                            </div>
                        </article>
                    </div>
                </section>
            </aside>
        </section>

        <div v-if="deleteCandidate" class="fixed inset-0 z-[80] flex items-end justify-center bg-black/60 p-4 sm:items-center">
            <div class="w-full max-w-md rounded-2xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-bold text-primary">Mahlzeit loeschen?</h2>
                <p class="mt-2 text-sm text-secondary">
                    "{{ deleteCandidate.title }}" wird aus deinem Tageslog entfernt.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted" @click="deleteCandidate = null">
                        Abbrechen
                    </button>
                    <button type="button" class="rounded-xl bg-danger px-4 py-2 text-sm font-bold text-white" @click="confirmDelete">
                        Loeschen
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
