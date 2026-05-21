<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
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
const activeSection = ref('today')
const showAdvancedMeal = ref(false)
const recipeFilter = ref('all')
const foodSearchQuery = ref('')
const foodBarcode = ref('')
const foodLookupLoading = ref(false)
const foodLookupError = ref('')
const foodSearchResults = ref([])
const quickDrinkAmounts = [150, 250, 500, 750]
const drinkVessels = [
    {
        key: 'small-cup',
        label: 'Kleine Tasse',
        amount: 150,
        hint: 'Kaffee, Tee oder kurzer Drink',
        title: 'Kleine Tasse Wasser',
        fill: 42,
        gradient: 'from-cyan-300 to-air-blue',
        ring: 'border-cyan-300/45 hover:border-cyan-200',
    },
    {
        key: 'glass',
        label: 'Glas',
        amount: 250,
        hint: 'Standardglas fuer zwischendurch',
        title: 'Glas Wasser',
        fill: 58,
        gradient: 'from-sky-300 to-cyan-500',
        ring: 'border-sky-300/45 hover:border-sky-200',
    },
    {
        key: 'large-cup',
        label: 'Grosser Becher',
        amount: 350,
        hint: 'Guter Schritt nach dem Training',
        title: 'Grosser Becher Wasser',
        fill: 72,
        gradient: 'from-emerald-300 to-cyan-500',
        ring: 'border-emerald-300/45 hover:border-emerald-200',
    },
    {
        key: 'bottle',
        label: 'Flasche',
        amount: 500,
        hint: 'Schnell viel nachtragen',
        title: 'Flasche Wasser',
        fill: 88,
        gradient: 'from-air-blue to-indigo-400',
        ring: 'border-air-blue/55 hover:border-air-blue',
    },
]
const showDrinkTips = ref(false)

const drinkTips = [
    {
        title: 'Regelmäßig statt alles auf einmal',
        body: 'Kleine Mengen über den Tag sind für die meisten alltagstauglicher als abends plötzlich sehr viel zu trinken.',
        icon: 'las la-clock',
    },
    {
        title: 'Training verändert den Bedarf',
        body: 'Bei langen, intensiven oder warmen Einheiten brauchst du meist mehr Flüssigkeit. Nach dem Training nicht nur Kalorien, sondern auch Wasser nachtragen.',
        icon: 'las la-running',
    },
    {
        title: 'Farbe und Gefühl beobachten',
        body: 'Sehr dunkler Urin, Kopfschmerzen oder starke Müdigkeit können Hinweise sein, dass du zu wenig getrunken hast.',
        icon: 'las la-eye',
    },
    {
        title: 'Nicht jedes Getränk ist gleich',
        body: 'Wasser und ungesüßter Tee sind einfache Standardoptionen. Zuckerreiche Getränke zählen zwar als Flüssigkeit, passen aber nicht immer zum Ziel.',
        icon: 'las la-mug-hot',
    },
]

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

const drinkForm = useForm({
    eaten_on: props.selectedDate,
    amount_ml: 250,
    title: 'Wasser',
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
const foodMealTypes = computed(() => mealTypes.value.filter((type) => type.key !== 'drink'))
const goalTypes = computed(() => props.catalog?.goal_types || [])
const dietStyles = computed(() => props.catalog?.diet_styles || [])
const drinkEntries = computed(() => (props.meals || []).filter((meal) => meal.meal_type === 'drink').sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '')))
const sortedMeals = computed(() => (props.meals || []).filter((meal) => meal.meal_type !== 'drink').sort((a, b) => (a.meal_type || '').localeCompare(b.meal_type || '')))
const selectedGoal = computed(() => goalTypes.value.find((goal) => goal.key === goalForm.goal_type) || goalTypes.value[0] || {})
const maxWeekCalories = computed(() => Math.max(1, ...props.weeklySummaries.map((day) => Number(day.calories || 0))))
const maxWeekWater = computed(() => Math.max(1, Number(goalForm.water_target_ml || 0), ...props.weeklySummaries.map((day) => Number(day.water_ml || 0))))
const caloriesLeft = computed(() => Math.max(0, Number(goalForm.daily_calories_target || 0) - Number(props.todaySummary.calories || 0)))
const waterTargetMl = computed(() => Number(goalForm.water_target_ml || 0))
const waterConsumedMl = computed(() => Number(props.todaySummary.water_ml || 0))
const waterLeftMl = computed(() => Math.max(0, waterTargetMl.value - waterConsumedMl.value))
const waterProgress = computed(() => progressValue(waterConsumedMl.value, waterTargetMl.value))
const filteredRecipes = computed(() => {
    if (recipeFilter.value === 'goal') {
        return props.recipes.filter((recipe) => recipe.goal_type === goalForm.goal_type)
    }

    if (recipeFilter.value === 'style') {
        return props.recipes.filter((recipe) => (recipe.diet_styles || []).includes(goalForm.diet_style))
    }

    return props.recipes
})

const nutritionSections = [
    { key: 'today', label: 'Heute', hint: 'Überblick', icon: 'las la-chart-pie' },
    { key: 'add', label: 'Erfassen', hint: 'Mahlzeit', icon: 'las la-plus-circle' },
    { key: 'drink', label: 'Trinken', hint: 'Wasser', icon: 'las la-tint' },
    { key: 'goals', label: 'Ziele', hint: 'Plan', icon: 'las la-bullseye' },
    { key: 'ideas', label: 'Ideen', hint: 'Rezepte', icon: 'las la-lightbulb' },
]

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

const formatWater = (value) => {
    const ml = Number(value || 0)

    if (ml >= 1000) {
        return `${(ml / 1000).toLocaleString('de-DE', { maximumFractionDigits: 1 })} l`
    }

    return `${formatNumber(ml)} ml`
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
    showAdvancedMeal.value = false
    mealForm.reset()
    mealForm.eaten_on = props.selectedDate
    mealForm.meal_type = 'breakfast'
    mealForm.source = 'manual'
    mealForm.items = emptyItems()
}

const submitMeal = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetMealForm()
            activeSection.value = 'today'
        },
    }

    if (editingMealId.value) {
        mealForm.patch(route('auth.nutrition.meals.update', editingMealId.value), options)
        return
    }

    mealForm.post(route('auth.nutrition.meals.store'), options)
}

const editMeal = (meal) => {
    activeSection.value = 'add'
    showAdvancedMeal.value = true
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

const submitDrink = (amount = null, title = null) => {
    if (amount) {
        drinkForm.amount_ml = amount
    }

    if (title) {
        drinkForm.title = title
    }

    drinkForm.eaten_on = selectedDateValue.value || props.selectedDate
    drinkForm.title = drinkForm.title || 'Wasser'

    drinkForm.post(route('auth.nutrition.water.store'), {
        preserveScroll: true,
        onSuccess: () => {
            drinkForm.amount_ml = amount || 250
            drinkForm.title = 'Wasser'
            activeSection.value = 'drink'
        },
    })
}

const submitDrinkVessel = (vessel) => {
    submitDrink(vessel.amount, vessel.title)
}

const applyRecipe = (recipe) => {
    activeSection.value = 'add'
    showAdvancedMeal.value = true
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
    activeSection.value = 'add'
    showAdvancedMeal.value = true
    mealForm.meal_type = suggestion.meal_type || 'snack'
    mealForm.training_context = suggestion.training_context || ''
    mealForm.notes = suggestion.body || ''
}

const applyFoodResult = (food) => {
    activeSection.value = 'add'
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
    mealForm.notes = `Quelle: ${food.attribution || 'Open Food Facts'}. Werte bitte prüfen, da offene Daten unvollstaendig sein koennen.`
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
        foodLookupError.value = error.response?.data?.message || 'Lebensmittel-Suche ist gerade nicht verfügbar.'
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

    <div class="space-y-4">
        <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase text-air-blue">Airmius Fuel</p>
                    <h1 class="mt-1 text-2xl font-bold leading-tight text-primary">Ernaehrung</h1>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-secondary">
                        Heute sehen, schnell erfassen, Ziele ruhig anpassen. Keine überladene Arbeitsflaeche mehr.
                    </p>
                </div>
                <div class="flex w-full gap-2 sm:w-auto">
                    <input v-model="selectedDateValue" type="date" class="min-w-0 flex-1 rounded-xl border border-border bg-inputBg px-3 py-2 text-sm text-primary sm:w-44" @change="changeDate">
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" @click="changeDate">
                        Laden
                    </button>
                </div>
            </div>
        </section>

        <nav class="grid gap-2 rounded-2xl border border-border bg-card p-2 sm:grid-cols-5">
            <button
                v-for="section in nutritionSections"
                :key="section.key"
                type="button"
                :class="[
                    'flex items-center gap-3 rounded-xl border px-3 py-3 text-start transition',
                    activeSection === section.key
                        ? 'border-air-blue bg-air-blue/15 text-primary shadow-lg shadow-air-blue/10'
                        : 'border-transparent text-secondary hover:border-border hover:bg-inputBg'
                ]"
                @click="activeSection = section.key"
            >
                <i :class="[section.icon, 'text-xl']"></i>
                <span class="min-w-0">
                    <span class="block text-sm font-bold">{{ section.label }}</span>
                    <span class="block truncate text-xs opacity-80">{{ section.hint }}</span>
                </span>
            </button>
        </nav>

        <section v-if="activeSection === 'today'" class="grid gap-4 xl:grid-cols-[minmax(0,0.95fr),minmax(360px,0.75fr)]">
            <div class="space-y-4">
                <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase text-air-blue">Heute</p>
                            <h2 class="mt-1 text-xl font-bold text-primary">{{ formatNumber(todaySummary.calories || 0) }} kcal gegessen</h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ formatNumber(caloriesLeft) }} kcal bis zu deinem Tagesziel.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" @click="activeSection = 'add'">
                                Mahlzeit erfassen
                            </button>
                            <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted" @click="activeSection = 'ideas'">
                                Ideen
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article v-for="macro in macroCards" :key="macro.key" class="rounded-xl border border-border bg-inputBg p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-bold uppercase text-secondary">{{ macro.label }}</p>
                                    <p class="mt-1 text-lg font-bold text-primary">
                                        {{ formatNumber(macro.value, macro.key === 'calories' ? 0 : 1) }}
                                        <span class="text-xs text-secondary">{{ macro.unit }}</span>
                                    </p>
                                </div>
                                <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white', macro.color]">
                                    <i :class="[macro.icon, 'text-lg']"></i>
                                </span>
                            </div>
                            <div class="mt-3 h-1.5 rounded-full bg-card">
                                <div :class="['h-1.5 rounded-full bg-gradient-to-r', macro.color]" :style="{ width: `${progressValue(macro.value, macro.target)}%` }"></div>
                            </div>
                        </article>
                    </div>

                    <div class="mt-4 rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase text-cyan-200">Trinken</p>
                                <h3 class="mt-1 text-xl font-black text-primary">{{ formatWater(waterConsumedMl) }} getrunken</h3>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ formatWater(waterLeftMl) }} bis zu deinem Tagesziel von {{ formatWater(waterTargetMl) }}.
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <button
                                    v-for="amount in quickDrinkAmounts"
                                    :key="amount"
                                    type="button"
                                    class="rounded-xl border border-cyan-300/40 bg-card px-3 py-2 text-sm font-bold text-primary hover:bg-cyan-400/15 disabled:opacity-60"
                                    :disabled="drinkForm.processing"
                                    @click="submitDrink(amount)"
                                >
                                    +{{ amount }} ml
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 h-3 overflow-hidden rounded-full bg-card">
                            <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-air-blue" :style="{ width: `${waterProgress}%` }"></div>
                        </div>
                        <button type="button" class="mt-3 text-sm font-bold text-cyan-200 hover:text-primary" @click="activeSection = 'drink'">
                            Trinken genau verwalten
                        </button>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-air-blue">Verlauf</p>
                            <h2 class="mt-1 text-lg font-bold text-primary">Letzte 7 Tage</h2>
                        </div>
                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-secondary">{{ formatNumber(todaySummary.water_ml || 0) }} ml Wasser</span>
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
                        <p class="text-xs font-bold uppercase text-air-blue">Tageslog</p>
                        <h2 class="mt-1 text-lg font-bold text-primary">Mahlzeiten</h2>
                    </div>
                    <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-primary">{{ sortedMeals.length }} Eintraege</span>
                </div>

                <div class="mt-4 space-y-3">
                    <article v-for="meal in sortedMeals" :key="meal.id" class="rounded-xl border border-border bg-inputBg p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase text-secondary">
                                    <i :class="[mealTypeMeta(meal.meal_type).icon, 'me-1']"></i>
                                    {{ mealTypeMeta(meal.meal_type).label }}
                                </p>
                                <h3 class="mt-1 truncate text-base font-bold text-primary">{{ meal.title }}</h3>
                                <p class="mt-1 text-sm text-secondary">{{ formatNumber(meal.calories) }} kcal - {{ formatNumber(meal.protein_g, 1) }} g Protein</p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <button type="button" class="rounded-lg border border-border px-2.5 py-2 text-primary hover:bg-muted" title="Bearbeiten" @click="editMeal(meal)">
                                    <i class="las la-pen"></i>
                                </button>
                                <button type="button" class="rounded-lg border border-danger/30 px-2.5 py-2 text-danger hover:bg-danger/10" title="Löschen" @click="deleteCandidate = meal">
                                    <i class="las la-trash"></i>
                                </button>
                            </div>
                        </div>
                    </article>

                    <div v-if="!sortedMeals.length" class="rounded-xl border border-dashed border-border bg-inputBg p-6 text-center">
                        <i class="las la-apple-alt text-4xl text-air-blue"></i>
                        <p class="mt-3 text-base font-bold text-primary">Noch nichts erfasst.</p>
                        <p class="mt-1 text-sm text-secondary">Eine grobe Mahlzeit reicht für den Anfang.</p>
                    </div>
                </div>
            </section>
        </section>

        <section v-if="activeSection === 'add'" class="grid gap-4 xl:grid-cols-[minmax(0,0.85fr),minmax(340px,0.65fr)]">
            <form class="rounded-2xl border border-border bg-card p-4 lg:p-5" @submit.prevent="submitMeal">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">Mahlzeit</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ editingMealId ? 'Mahlzeit bearbeiten' : 'Schnell erfassen' }}</h2>
                        <p class="mt-1 text-sm text-secondary">Nur Name und Kalorien sind Pflicht. Details bleiben optional.</p>
                    </div>
                    <button v-if="editingMealId" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted" @click="resetMealForm">
                        Neu
                    </button>
                </div>

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
                        <span class="mt-1 block text-xs font-bold">{{ type.label }}</span>
                    </button>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm font-bold text-primary sm:col-span-2">Was hast du gegessen?
                        <input v-model="mealForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Bowl, Banane, Proteinshake" required>
                    </label>
                    <label class="block text-sm font-bold text-primary">Kalorien
                        <input v-model="mealForm.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. 520" required>
                    </label>
                    <label class="block text-sm font-bold text-primary">Protein optional
                        <input v-model="mealForm.protein_g" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. 32">
                    </label>
                </div>

                <button type="button" class="mt-4 flex w-full items-center justify-between rounded-xl border border-border bg-inputBg px-4 py-3 text-sm font-bold text-primary hover:bg-muted" @click="showAdvancedMeal = !showAdvancedMeal">
                    <span>Mehr Details</span>
                    <i :class="[showAdvancedMeal ? 'las la-angle-up' : 'las la-angle-down', 'text-lg']"></i>
                </button>

                <div v-if="showAdvancedMeal" class="mt-4 space-y-4 rounded-2xl border border-border bg-inputBg p-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm font-bold text-primary">Datum
                            <input v-model="mealForm.eaten_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                        </label>
                        <label class="block text-sm font-bold text-primary">Training-Bezug
                            <select v-model="mealForm.training_context" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                                <option value="">Ohne Bezug</option>
                                <option value="pre_workout">Vor dem Training</option>
                                <option value="post_workout">Nach dem Training</option>
                                <option v-for="log in recentTraining" :key="log.id" :value="`training_log:${log.id}`">{{ log.title }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-bold text-primary">Kohlenhydrate g
                            <input v-model="mealForm.carbs_g" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                        </label>
                        <label class="block text-sm font-bold text-primary">Fett g
                            <input v-model="mealForm.fat_g" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                        </label>
                        <label class="block text-sm font-bold text-primary">Wasser ml
                            <input v-model="mealForm.water_ml" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary">
                        </label>
                    </div>

                    <div>
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

                    <label class="block text-sm font-bold text-primary">Notiz
                        <textarea v-model="mealForm.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-card px-3 py-2 text-primary" placeholder="Gefuehl, Hunger, Timing, Besonderheiten"></textarea>
                    </label>
                </div>

                <div v-if="Object.keys(mealForm.errors).length" class="mt-4 rounded-xl border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                    Bitte prüfe die Eingaben.
                </div>

                <button type="submit" class="mt-4 w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="mealForm.processing">
                    {{ editingMealId ? 'Speichern' : 'Hinzufügen' }}
                </button>
            </form>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-air-blue/25 bg-air-blue/10 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-air-blue">Suche</p>
                            <h2 class="mt-1 text-lg font-bold text-primary">Lebensmittel finden</h2>
                            <p class="mt-1 text-sm text-secondary">Name oder Barcode suchen, dann übernehmen.</p>
                        </div>
                        <span class="rounded-full bg-card px-3 py-1 text-[11px] font-bold text-primary">kostenlos</span>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr),auto]">
                            <input v-model="foodSearchQuery" class="rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="z. B. Skyr, Banane">
                            <button type="button" class="rounded-xl border border-air-blue/40 px-4 py-2 text-sm font-bold text-primary hover:bg-air-blue/15 disabled:opacity-60" :disabled="foodLookupLoading" @click="searchFoods">
                                Suchen
                            </button>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr),auto]">
                            <input v-model="foodBarcode" class="rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary" placeholder="Barcode">
                            <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted disabled:opacity-60" :disabled="foodLookupLoading" @click="lookupBarcode">
                                Barcode
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
                                <p class="truncate text-xs text-secondary">{{ food.brand || 'Open Food Facts' }} - {{ food.quantity_label }}</p>
                                <p class="mt-1 text-xs text-secondary">{{ food.calories }} kcal - {{ food.protein_g }} g Protein</p>
                            </div>
                            <button type="button" class="shrink-0 rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-bold text-buttonTextPrimary" @click="applyFoodResult(food)">
                                Übernehmen
                            </button>
                        </article>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-xs font-bold uppercase text-air-blue">Heute bisher</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <span class="rounded-xl border border-border bg-inputBg p-3 text-xs text-secondary"><b class="block text-lg text-primary">{{ formatNumber(todaySummary.calories || 0) }}</b>kcal</span>
                        <span class="rounded-xl border border-border bg-inputBg p-3 text-xs text-secondary"><b class="block text-lg text-primary">{{ formatNumber(todaySummary.protein_g || 0, 1) }} g</b>Protein</span>
                    </div>
                </section>
            </aside>
        </section>

        <section v-if="activeSection === 'drink'" class="grid gap-4 xl:grid-cols-[minmax(0,0.85fr),minmax(320px,0.55fr)]">
            <form class="rounded-2xl border border-border bg-card p-4 lg:p-5" @submit.prevent="submitDrink()">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase text-cyan-200">Trinken</p>
                        <h2 class="mt-1 text-2xl font-black text-primary">{{ formatWater(waterConsumedMl) }} heute</h2>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            Ziel: {{ formatWater(waterTargetMl) }}. Noch {{ formatWater(waterLeftMl) }} offen.
                        </p>
                    </div>
                    <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted" @click="activeSection = 'goals'">
                        Wasserziel ändern
                    </button>
                </div>

                <div class="mt-5 rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-bold text-primary">{{ waterProgress }}%</span>
                        <span class="text-sm font-semibold text-secondary">{{ formatWater(waterConsumedMl) }} / {{ formatWater(waterTargetMl) }}</span>
                    </div>
                    <div class="mt-3 h-4 overflow-hidden rounded-full bg-card">
                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-air-blue" :style="{ width: `${waterProgress}%` }"></div>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-sm font-bold text-primary">Nach Tasse oder Glas eintragen</p>
                            <p class="text-xs leading-5 text-secondary">Waehle die Groesse, die am besten passt. Die Menge wird direkt gespeichert.</p>
                        </div>
                        <span class="text-xs font-bold uppercase text-cyan-200">Airmius Quick Drink</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <button
                            v-for="vessel in drinkVessels"
                            :key="vessel.key"
                            type="button"
                            class="group rounded-2xl border bg-inputBg p-3 text-left transition hover:-translate-y-0.5 hover:bg-cyan-400/10 disabled:opacity-60"
                            :class="vessel.ring"
                            :disabled="drinkForm.processing"
                            @click="submitDrinkVessel(vessel)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-primary">{{ vessel.label }}</p>
                                    <p class="mt-1 text-xs text-secondary">{{ vessel.hint }}</p>
                                </div>
                                <span class="rounded-full bg-card px-2.5 py-1 text-xs font-black text-cyan-100">{{ vessel.amount }} ml</span>
                            </div>
                            <div class="mt-4 flex items-end justify-center">
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

                <div class="mt-5">
                    <p class="text-sm font-bold text-primary">Oder schnelle Menge eintragen</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <button
                            v-for="amount in quickDrinkAmounts"
                            :key="amount"
                            type="button"
                            class="rounded-xl border border-border bg-inputBg px-4 py-4 text-base font-black text-primary hover:border-cyan-300 hover:bg-cyan-400/10 disabled:opacity-60"
                            :disabled="drinkForm.processing"
                            @click="submitDrink(amount)"
                        >
                            +{{ amount }} ml
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr),minmax(0,1fr),auto]">
                    <label class="block text-sm font-bold text-primary">Getränk
                        <input v-model="drinkForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Wasser, Tee">
                    </label>
                    <label class="block text-sm font-bold text-primary">Menge in ml
                        <input v-model="drinkForm.amount_ml" type="number" min="1" max="5000" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="250">
                    </label>
                    <button type="submit" class="self-end rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="drinkForm.processing">
                        Eintragen
                    </button>
                </div>
                <input v-model="drinkForm.eaten_on" type="hidden">

                <p v-if="drinkForm.errors.amount_ml || drinkForm.errors.title" class="mt-3 rounded-xl border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                    Bitte Menge zwischen 1 und 5000 ml eingeben.
                </p>
            </form>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4 lg:p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-cyan-400/20 text-cyan-100">
                            <i class="las la-lightbulb text-2xl"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase text-cyan-200">Trink-Tipps</p>
                            <h3 class="mt-1 text-lg font-black text-primary">Kurz wissen, besser tracken</h3>
                            <p class="mt-1 text-sm leading-6 text-secondary">
                                Orientierung zu Wasserziel, Training und Alltag.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <button
                            type="button"
                            class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary"
                            @click="showDrinkTips = true"
                        >
                            Tipps öffnen
                        </button>
                        <Link
                            :href="route('guest.blog.index', { search: 'Trinken' })"
                            class="rounded-xl border border-border bg-card px-4 py-3 text-center text-sm font-bold text-primary hover:border-cyan-300 hover:bg-cyan-400/10"
                        >
                            Blog zu Trinken
                        </Link>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-air-blue">Heute</p>
                            <h2 class="mt-1 text-lg font-bold text-primary">Getränke</h2>
                        </div>
                        <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-bold text-primary">{{ drinkEntries.length }}</span>
                    </div>

                    <div v-if="drinkEntries.length" class="mt-4 space-y-2">
                        <article v-for="entry in drinkEntries" :key="entry.id" class="flex items-center justify-between gap-3 rounded-xl border border-border bg-inputBg p-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-primary">{{ entry.title }}</p>
                                <p class="text-xs text-secondary">{{ formatWater(entry.water_ml) }}</p>
                            </div>
                            <button type="button" class="rounded-lg border border-danger/30 px-2.5 py-2 text-danger hover:bg-danger/10" title="Löschen" @click="deleteCandidate = entry">
                                <i class="las la-trash"></i>
                            </button>
                        </article>
                    </div>
                    <div v-else class="mt-4 rounded-xl border border-dashed border-border bg-inputBg p-6 text-center">
                        <i class="las la-tint text-4xl text-cyan-200"></i>
                        <p class="mt-3 text-base font-bold text-primary">Noch nichts getrunken eingetragen.</p>
                        <p class="mt-1 text-sm text-secondary">Ein Tippen auf +250 ml reicht während des Tages.</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                    <p class="text-xs font-bold uppercase text-air-blue">7 Tage Wasser</p>
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
        </section>

        <section v-if="activeSection === 'goals'" class="grid gap-4 xl:grid-cols-[minmax(0,0.8fr),minmax(320px,0.6fr)]">
            <form class="rounded-2xl border border-border bg-card p-4 lg:p-5" @submit.prevent="saveGoal">
                <p class="text-xs font-bold uppercase text-air-blue">Ziel</p>
                <h2 class="mt-1 text-xl font-bold text-primary">{{ selectedGoal.label || 'Ernaehrungsziel' }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ selectedGoal.hint }}</p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm font-bold text-primary">Ziel
                        <select v-model="goalForm.goal_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                            <option v-for="goalType in goalTypes" :key="goalType.key" :value="goalType.key">{{ goalType.label }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-bold text-primary">Ernaehrungsstil
                        <select v-model="goalForm.diet_style" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                            <option v-for="style in dietStyles" :key="style.key" :value="style.key">{{ style.label }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-bold text-primary">Kalorienziel
                        <input v-model="goalForm.daily_calories_target" type="number" min="800" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary">Proteinziel
                        <input v-model="goalForm.protein_target_g" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary">Kohlenhydrate
                        <input v-model="goalForm.carbs_target_g" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary">Fett
                        <input v-model="goalForm.fat_target_g" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary">Wasserziel ml
                        <input v-model="goalForm.water_target_ml" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                    </label>
                    <label class="block text-sm font-bold text-primary sm:col-span-2">Notiz
                        <textarea v-model="goalForm.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="Allergien, Vorlieben, Trainer-Hinweise"></textarea>
                    </label>
                </div>
                <button type="submit" class="mt-4 w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60" :disabled="goalForm.processing">
                    Ziel speichern
                </button>
            </form>

            <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                <p class="text-xs font-bold uppercase text-air-blue">Tipps</p>
                <div class="mt-3 space-y-2">
                    <p v-for="tip in tips" :key="tip" class="rounded-xl border border-border bg-inputBg p-3 text-sm leading-6 text-secondary">
                        {{ tip }}
                    </p>
                </div>
            </section>
        </section>

        <section v-if="activeSection === 'ideas'" class="grid gap-4 xl:grid-cols-[minmax(0,0.85fr),minmax(320px,0.55fr)]">
            <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">Rezepte</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">Schnell übernehmen</h2>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" :class="['rounded-lg border px-3 py-2 text-xs font-bold', recipeFilter === 'all' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="recipeFilter = 'all'">
                            Alle
                        </button>
                        <button type="button" :class="['rounded-lg border px-3 py-2 text-xs font-bold', recipeFilter === 'goal' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="recipeFilter = 'goal'">
                            Ziel
                        </button>
                        <button type="button" :class="['rounded-lg border px-3 py-2 text-xs font-bold', recipeFilter === 'style' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="recipeFilter = 'style'">
                            Stil
                        </button>
                    </div>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <article v-for="recipe in filteredRecipes" :key="recipe.key" class="rounded-xl border border-border bg-inputBg p-4">
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

            <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
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
        </section>

        <div v-if="deleteCandidate" class="fixed inset-0 z-[80] flex items-end justify-center bg-black/60 p-4 sm:items-center">
            <div class="w-full max-w-md rounded-2xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-bold text-primary">Eintrag löschen?</h2>
                <p class="mt-2 text-sm text-secondary">
                    "{{ deleteCandidate.title }}" wird aus deinem Tageslog entfernt.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted" @click="deleteCandidate = null">
                        Abbrechen
                    </button>
                    <button type="button" class="rounded-xl bg-danger px-4 py-2 text-sm font-bold text-white" @click="confirmDelete">
                        Löschen
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showDrinkTips" class="fixed inset-0 z-[90] flex items-end justify-center bg-black/65 p-0 sm:items-center sm:p-4" @click.self="showDrinkTips = false">
            <section class="flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-3xl border border-border bg-card shadow-2xl sm:rounded-2xl">
                <header class="flex items-start justify-between gap-3 border-b border-border p-4 sm:p-5">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase text-cyan-200">Trinken</p>
                        <h2 class="mt-1 text-xl font-black text-primary sm:text-2xl">Tipps rund ums Trinken</h2>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            Einfache Orientierung für Alltag, Training und Regeneration.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-primary hover:bg-muted"
                        aria-label="Schließen"
                        @click="showDrinkTips = false"
                    >
                        <i class="las la-times text-xl"></i>
                    </button>
                </header>

                <div class="min-h-0 overflow-y-auto p-4 sm:p-5">
                    <div class="grid gap-3">
                        <article v-for="tip in drinkTips" :key="tip.title" class="rounded-2xl border border-border bg-inputBg p-4">
                            <div class="flex gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-400/15 text-cyan-200">
                                    <i :class="[tip.icon, 'text-xl']"></i>
                                </span>
                                <div>
                                    <h3 class="font-bold text-primary">{{ tip.title }}</h3>
                                    <p class="mt-1 text-sm leading-6 text-secondary">{{ tip.body }}</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="mt-4 rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4">
                        <p class="text-sm font-bold text-primary">Dein aktueller Stand</p>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            Heute: {{ formatWater(waterConsumedMl) }} von {{ formatWater(waterTargetMl) }}. Noch {{ formatWater(waterLeftMl) }} offen.
                        </p>
                        <div class="mt-3 h-3 overflow-hidden rounded-full bg-card">
                            <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-air-blue" :style="{ width: `${waterProgress}%` }"></div>
                        </div>
                    </div>
                </div>

                <footer class="grid gap-2 border-t border-border p-4 sm:grid-cols-[1fr_auto] sm:p-5">
                    <Link
                        :href="route('guest.blog.index', { search: 'Trinken' })"
                        class="rounded-xl border border-border px-4 py-3 text-center text-sm font-bold text-primary hover:border-cyan-300 hover:bg-cyan-400/10"
                        @click="showDrinkTips = false"
                    >
                        Mehr im Blog lesen
                    </Link>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary" @click="showDrinkTips = false">
                        Verstanden
                    </button>
                </footer>
            </section>
        </div>
    </div>
</template>
