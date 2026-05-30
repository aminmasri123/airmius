<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    selectedDate: { type: String, required: true },
    goal: { type: Object, default: () => ({}) },
    meals: { type: Array, default: () => [] },
    todaySummary: { type: Object, default: () => ({}) },
    weeklySummaries: { type: Array, default: () => [] },
    waterRecommendation: { type: Object, default: () => ({}) },
    catalog: { type: Object, default: () => ({}) },
    recipes: { type: Array, default: () => [] },
    tips: { type: Array, default: () => [] },
    trainingSuggestions: { type: Array, default: () => [] },
    recentTraining: { type: Array, default: () => [] },
    aiCapabilities: { type: Object, default: () => ({}) },
})

const { locale, messages, te, t } = useI18n({ useScope: 'global' })

const tAutoPattern = (source) => {
    const patterns = messages.value?.[locale.value]?.auto_patterns || []

    for (const pattern of patterns) {
        if (!pattern?.source || !pattern?.target) {
            continue
        }

        try {
            const regex = new RegExp(pattern.source, pattern.flags || '')

            if (regex.test(source)) {
                return source.replace(regex, pattern.target)
            }
        } catch (error) {
            // Optional translation patterns should never break the page.
        }
    }

    return null
}

const tAuto = (value) => {
    const source = String(value ?? '').trim()

    if (!source || locale.value === 'de') {
        return source
    }

    const dictionary = messages.value?.[locale.value]?.auto || {}

    if (dictionary[source]) {
        return dictionary[source]
    }

    const trailingPunctuation = source.match(/([.!?؟])$/)?.[1]
    if (trailingPunctuation) {
        const normalized = source.slice(0, -trailingPunctuation.length).trim()

        if (dictionary[normalized]) {
            return `${dictionary[normalized]}${trailingPunctuation}`
        }
    }

    const leadingPunctuation = source.match(/^([.!?؟])/)?.[1]
    if (leadingPunctuation) {
        const normalized = source.slice(leadingPunctuation.length).trim()

        if (dictionary[normalized]) {
            return dictionary[normalized]
        }
    }

    const patternTranslation = tAutoPattern(source)

    if (patternTranslation) {
        return patternTranslation
    }

    return te(source) ? t(source) : source
}

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
const aiMealImageFile = ref(null)
const aiMealImageInput = ref(null)
const aiMealConsent = ref(false)
const aiMealAnalyzing = ref(false)
const aiMealError = ref('')
const aiMealSuggestion = ref(null)
const drinkSelectOpen = ref(false)
const drinkSearchQuery = ref('Wasser')
const drinkSelectRef = ref(null)
const quickDrinkAmounts = [150, 250, 500, 750]
const popularDrinkOptions = [
    { label: 'Wasser', category: 'Wasser', amount: 250, aliases: ['still', 'leitungswasser', 'tap water'] },
    { label: 'Mineralwasser', category: 'Wasser', amount: 250, aliases: ['mineral water'] },
    { label: 'Sprudelwasser', category: 'Wasser', amount: 250, aliases: ['sparkling water', 'wasser mit kohlensaeure'] },
    { label: 'Infused Water', category: 'Wasser', amount: 250, aliases: ['zitrone', 'gurke', 'mint water'] },
    { label: 'Kokoswasser', category: 'Wasser', amount: 250, aliases: ['coconut water'] },
    { label: 'Kräutertee', category: 'Tee', amount: 250, aliases: ['tee', 'herbal tea'] },
    { label: 'Grüner Tee', category: 'Tee', amount: 250, aliases: ['green tea'] },
    { label: 'Schwarzer Tee', category: 'Tee', amount: 250, aliases: ['black tea'] },
    { label: 'Eistee', category: 'Tee', amount: 330, aliases: ['iced tea'] },
    { label: 'Kaffee', category: 'Kaffee', amount: 200, aliases: ['coffee', 'filterkaffee'] },
    { label: 'Espresso', category: 'Kaffee', amount: 40, aliases: ['espresso shot'] },
    { label: 'Cappuccino', category: 'Kaffee', amount: 180, aliases: ['kaffee milch'] },
    { label: 'Latte Macchiato', category: 'Kaffee', amount: 250, aliases: ['latte'] },
    { label: 'Milch', category: 'Milch & Protein', amount: 250, aliases: ['milk'] },
    { label: 'Haferdrink', category: 'Milch & Protein', amount: 250, aliases: ['hafermilch', 'oat milk'] },
    { label: 'Ayran', category: 'Milch & Protein', amount: 250, aliases: ['joghurt drink'] },
    { label: 'Proteinshake', category: 'Milch & Protein', amount: 300, aliases: ['shake', 'protein'] },
    { label: 'Kakao', category: 'Milch & Protein', amount: 250, aliases: ['schokomilch', 'chocolate milk'] },
    { label: 'Orangensaft', category: 'Saft & Smoothie', amount: 200, aliases: ['orange juice', 'o-saft'] },
    { label: 'Apfelsaft', category: 'Saft & Smoothie', amount: 200, aliases: ['apple juice'] },
    { label: 'Multivitaminsaft', category: 'Saft & Smoothie', amount: 200, aliases: ['multi'] },
    { label: 'Smoothie', category: 'Saft & Smoothie', amount: 250, aliases: ['frucht smoothie'] },
    { label: 'Cola', category: 'Softdrink', amount: 330, aliases: ['coke'] },
    { label: 'Cola Zero', category: 'Softdrink', amount: 330, aliases: ['coke zero', 'cola light'] },
    { label: 'Limonade', category: 'Softdrink', amount: 330, aliases: ['lemonade', 'sprite', 'fanta'] },
    { label: 'Energy Drink', category: 'Softdrink', amount: 250, aliases: ['red bull', 'monster'] },
    { label: 'Isotonisches Getränk', category: 'Sport', amount: 500, aliases: ['isodrink', 'sports drink', 'elektrolyt'] },
    { label: 'Elektrolyt-Drink', category: 'Sport', amount: 500, aliases: ['hydration', 'salze'] },
    { label: 'Pre-Workout', category: 'Sport', amount: 300, aliases: ['booster'] },
    { label: 'Alkoholfreies Bier', category: 'Sport', amount: 330, aliases: ['recovery bier'] },
]
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
        hint: 'Standardglas für zwischendurch',
        title: 'Glas Wasser',
        fill: 58,
        gradient: 'from-sky-300 to-cyan-500',
        ring: 'border-sky-300/45 hover:border-sky-200',
    },
    {
        key: 'large-cup',
        label: 'Großer Becher',
        amount: 350,
        hint: 'Guter Schritt nach dem Training',
        title: 'Großer Becher Wasser',
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
const pendingDrinkEntries = ref([])
const deletingDrinkEntries = ref([])
const drinkError = ref('')

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
    body_weight_kg: props.goal?.body_weight_kg || '',
    water_target_mode: props.goal?.water_target_mode || 'auto',
    diet_style: props.goal?.diet_style || 'balanced',
    allergies: props.goal?.allergies || [],
    notes: props.goal?.notes || '',
})

const mealTypes = computed(() => props.catalog?.meal_types || [])
const foodMealTypes = computed(() => mealTypes.value.filter((type) => type.key !== 'drink'))
const goalTypes = computed(() => props.catalog?.goal_types || [])
const dietStyles = computed(() => props.catalog?.diet_styles || [])
const deletingDrinkIds = computed(() => new Set(deletingDrinkEntries.value.map((entry) => entry.id)))
const persistedDrinkEntries = computed(() => (props.meals || []).filter((meal) => meal.meal_type === 'drink' && !deletingDrinkIds.value.has(meal.id)))
const pendingWaterMl = computed(() => pendingDrinkEntries.value.reduce((total, entry) => total + Number(entry.water_ml || 0), 0))
const deletingWaterMl = computed(() => deletingDrinkEntries.value.reduce((total, entry) => total + Number(entry.water_ml || 0), 0))
const drinkEntries = computed(() => [...pendingDrinkEntries.value, ...persistedDrinkEntries.value].sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '')))
const sortedMeals = computed(() => (props.meals || []).filter((meal) => meal.meal_type !== 'drink').sort((a, b) => (a.meal_type || '').localeCompare(b.meal_type || '')))
const selectedGoal = computed(() => goalTypes.value.find((goal) => goal.key === goalForm.goal_type) || goalTypes.value[0] || {})
const maxWeekCalories = computed(() => Math.max(1, ...props.weeklySummaries.map((day) => Number(day.calories || 0))))
const caloriesLeft = computed(() => Math.max(0, Number(goalForm.daily_calories_target || 0) - Number(props.todaySummary.calories || 0)))
const waterRecommendation = computed(() => props.waterRecommendation || {})
const waterBaseMl = computed(() => {
    const weight = Number(goalForm.body_weight_kg || 0)

    if (weight > 0) {
        return roundToWaterStep(weight * 33)
    }

    return Number(waterRecommendation.value.base_ml || 2500)
})
const waterTrainingExtraMl = computed(() => Number(waterRecommendation.value.training_extra_ml || 0))
const suggestedWaterTargetMl = computed(() => clampWaterTarget(waterBaseMl.value + waterTrainingExtraMl.value))
const manualWaterTargetMl = computed(() => clampWaterTarget(Number(goalForm.water_target_ml || waterRecommendation.value.manual_target_ml || 2500)))
const waterTargetMl = computed(() => goalForm.water_target_mode === 'auto' ? suggestedWaterTargetMl.value : manualWaterTargetMl.value)
const maxWeekWater = computed(() => Math.max(1, Number(waterTargetMl.value || 0), ...props.weeklySummaries.map((day) => Number(day.water_ml || 0))))
const waterConsumedMl = computed(() => Math.max(0, Number(props.todaySummary.water_ml || 0) + pendingWaterMl.value - deletingWaterMl.value))
const waterLeftMl = computed(() => Math.max(0, waterTargetMl.value - waterConsumedMl.value))
const waterProgress = computed(() => progressValue(waterConsumedMl.value, waterTargetMl.value))
const aiNutritionImage = computed(() => props.aiCapabilities?.nutrition_image_analysis || {})
const aiMealImageAvailable = computed(() => Boolean(aiNutritionImage.value.available))
const aiMealImageAccessReason = computed(() => aiNutritionImage.value.access_reason || tAuto('KI-Bildanalyse ist in Sportler Pro, Trainer Pro oder einem passenden Vereinsplan enthalten.'))
const aiMealProviderLabel = computed(() => {
    const provider = aiNutritionImage.value.primary_provider || props.aiCapabilities?.primary_provider || 'google'
    const match = (props.aiCapabilities?.available_providers || []).find((item) => item.key === provider)

    return match?.label || provider
})
const filteredDrinkOptions = computed(() => {
    const search = normalizeDrinkSearch(drinkSearchQuery.value)

    return popularDrinkOptions
        .filter((drink) => {
            if (!search) return true

            return [
                drink.label,
                drink.category,
                ...(drink.aliases || []),
            ].some((value) => normalizeDrinkSearch(value).includes(search))
        })
        .slice(0, 18)
})
const customDrinkNameAvailable = computed(() => {
    const search = normalizeDrinkSearch(drinkSearchQuery.value)

    return Boolean(search) && !popularDrinkOptions.some((drink) => normalizeDrinkSearch(drink.label) === search)
})
const filteredRecipes = computed(() => {
    if (recipeFilter.value === 'goal') {
        return props.recipes.filter((recipe) => recipe.goal_type === goalForm.goal_type)
    }

    if (recipeFilter.value === 'style') {
        return props.recipes.filter((recipe) => (recipe.diet_styles || []).includes(goalForm.diet_style))
    }

    return props.recipes
})

const nutritionSections = computed(() => [
    { key: 'today', label: tAuto('Heute'), hint: tAuto('Überblick'), icon: 'las la-chart-pie' },
    { key: 'add', label: tAuto('Erfassen'), hint: tAuto('Mahlzeit'), icon: 'las la-plus-circle' },
    { key: 'drink', label: tAuto('Trinken'), hint: tAuto('Wasser'), icon: 'las la-tint' },
    { key: 'goals', label: tAuto('Ziele'), hint: tAuto('Plan'), icon: 'las la-bullseye' },
    { key: 'ideas', label: tAuto('Ideen'), hint: tAuto('Rezepte'), icon: 'las la-lightbulb' },
])

const macroCards = computed(() => [
    {
        key: 'calories',
        label: tAuto('Kalorien'),
        value: Number(props.todaySummary.calories || 0),
        target: Number(goalForm.daily_calories_target || 0),
        unit: 'kcal',
        icon: 'las la-fire',
        color: 'from-rose-500 to-orange-400',
    },
    {
        key: 'protein_g',
        label: tAuto('Protein'),
        value: Number(props.todaySummary.protein_g || 0),
        target: Number(goalForm.protein_target_g || 0),
        unit: 'g',
        icon: 'las la-dumbbell',
        color: 'from-sky-500 to-cyan-400',
    },
    {
        key: 'carbs_g',
        label: tAuto('Kohlenhydrate'),
        value: Number(props.todaySummary.carbs_g || 0),
        target: Number(goalForm.carbs_target_g || 0),
        unit: 'g',
        icon: 'las la-bolt',
        color: 'from-amber-400 to-yellow-300',
    },
    {
        key: 'fat_g',
        label: tAuto('Fett'),
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

const numberLocale = computed(() => ({
    ar: 'ar',
    en: 'en-US',
    fr: 'fr-FR',
    de: 'de-DE',
}[locale.value] || 'de-DE'))

const formatNumber = (value, digits = 0) => {
    const number = Number(value || 0)

    return number.toLocaleString(numberLocale.value, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    })
}

const formatWater = (value) => {
    const ml = Number(value || 0)

    if (ml >= 1000) {
        return `${(ml / 1000).toLocaleString(numberLocale.value, { maximumFractionDigits: 1 })} l`
    }

    return `${formatNumber(ml)} ml`
}

const roundToWaterStep = (value, step = 50) => Math.round(Number(value || 0) / step) * step

const clampWaterTarget = (value) => Math.max(1500, Math.min(6000, roundToWaterStep(value || 2500)))

const mealTypeMeta = (key) => mealTypes.value.find((type) => type.key === key) || { label: key || 'Mahlzeit', icon: 'las la-utensils' }

const normalizeDrinkSearch = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()

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

const onAiMealImageSelected = (event) => {
    aiMealImageFile.value = event.target.files?.[0] || null
    aiMealError.value = ''
    aiMealSuggestion.value = null
}

const analyzeMealImage = async () => {
    if (!aiMealImageAvailable.value) {
        aiMealError.value = aiMealImageAccessReason.value
        return
    }

    if (!aiMealImageFile.value) {
        aiMealError.value = 'Bitte zuerst ein Essensbild auswählen.'
        return
    }

    if (!aiMealConsent.value) {
        aiMealError.value = 'Bitte bestätige zuerst die KI-Analyse.'
        return
    }

    aiMealAnalyzing.value = true
    aiMealError.value = ''
    aiMealSuggestion.value = null

    const payload = new FormData()
    payload.append('image', aiMealImageFile.value)
    payload.append('ai_consent', '1')
    payload.append('meal_type', mealForm.meal_type || '')
    payload.append('diet_style', goalForm.diet_style || '')

    try {
        const response = await window.axios.post(route('auth.nutrition.ai.meal-image'), payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        aiMealSuggestion.value = response.data?.data || null
        if (aiMealSuggestion.value) {
            applyAiMealSuggestion()
        }
    } catch (error) {
        aiMealError.value = error.response?.data?.message || 'Bildanalyse konnte nicht abgeschlossen werden.'
    } finally {
        aiMealAnalyzing.value = false
    }
}

const applyAiMealSuggestion = () => {
    const suggestion = aiMealSuggestion.value
    if (!suggestion) return

    mealForm.title = suggestion.title || mealForm.title
    mealForm.calories = suggestion.calories ?? mealForm.calories
    mealForm.protein_g = suggestion.protein_g ?? mealForm.protein_g
    mealForm.carbs_g = suggestion.carbs_g ?? mealForm.carbs_g
    mealForm.fat_g = suggestion.fat_g ?? mealForm.fat_g
    mealForm.fiber_g = suggestion.fiber_g ?? mealForm.fiber_g
    mealForm.sugar_g = suggestion.sugar_g ?? mealForm.sugar_g
    mealForm.water_ml = suggestion.water_ml ?? mealForm.water_ml
    mealForm.source = 'photo_estimate'
    mealForm.items = suggestion.items?.length
        ? suggestion.items.map((item) => ({ name: item.name || '', amount: item.amount || '' }))
        : mealForm.items
    mealForm.notes = [
        suggestion.notes,
        ...(suggestion.warnings || []),
    ].filter(Boolean).join(' ')
    showAdvancedMeal.value = true
}

const saveGoal = () => {
    goalForm.patch(route('auth.nutrition.goal.update'), { preserveScroll: true })
}

const removePendingDrinkEntry = (id) => {
    pendingDrinkEntries.value = pendingDrinkEntries.value.filter((entry) => entry.id !== id)
}

const removeDeletingDrinkEntry = (id) => {
    deletingDrinkEntries.value = deletingDrinkEntries.value.filter((entry) => entry.id !== id)
}

const updateDrinkSearch = (value) => {
    drinkSearchQuery.value = value
    drinkForm.title = value
    drinkSelectOpen.value = true
}

const selectDrinkOption = (drink) => {
    drinkSearchQuery.value = drink.label
    drinkForm.title = drink.label
    drinkForm.amount_ml = drink.amount || drinkForm.amount_ml || 250

    drinkSelectOpen.value = false
}

const useCustomDrinkName = () => {
    const customName = drinkSearchQuery.value.trim()

    if (!customName) return

    drinkForm.title = customName
    drinkSelectOpen.value = false
}

const clearDrinkSearch = () => {
    drinkSearchQuery.value = ''
    drinkForm.title = ''
    drinkSelectOpen.value = true
}

const submitDrink = (amount = null, title = null) => {
    const selectedAmount = Number(amount || drinkForm.amount_ml || 0)
    const selectedTitle = String(title || drinkForm.title || 'Wasser').trim() || 'Wasser'
    const selectedDate = selectedDateValue.value || props.selectedDate

    if (!selectedAmount || selectedAmount < 1 || selectedAmount > 5000) {
        drinkError.value = 'Bitte Menge zwischen 1 und 5000 ml eingeben.'
        return
    }

    drinkError.value = ''
    activeSection.value = 'drink'
    drinkForm.eaten_on = selectedDate
    drinkForm.amount_ml = selectedAmount
    drinkForm.title = selectedTitle
    drinkSearchQuery.value = selectedTitle

    const pendingId = `pending-drink-${Date.now()}-${Math.random().toString(36).slice(2)}`
    pendingDrinkEntries.value.unshift({
        id: pendingId,
        title: selectedTitle,
        meal_type: 'drink',
        water_ml: selectedAmount,
        created_at: new Date().toISOString(),
        is_pending: true,
    })

    router.post(route('auth.nutrition.water.store'), {
        eaten_on: selectedDate,
        amount_ml: selectedAmount,
        title: selectedTitle,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            removePendingDrinkEntry(pendingId)
            drinkForm.amount_ml = amount || selectedAmount || 250
            drinkForm.title = 'Wasser'
            drinkSearchQuery.value = 'Wasser'
            activeSection.value = 'drink'
        },
        onError: () => {
            removePendingDrinkEntry(pendingId)
            drinkError.value = 'Trinken konnte nicht gespeichert werden. Bitte versuche es erneut.'
        },
    })
}

const submitDrinkVessel = (vessel) => {
    submitDrink(vessel.amount, vessel.title)
}

const deleteDrinkEntry = (entry) => {
    if (!entry?.id || entry.is_pending || deletingDrinkIds.value.has(entry.id)) return

    drinkError.value = ''
    activeSection.value = 'drink'
    deletingDrinkEntries.value.unshift({ ...entry, is_deleting: true })

    let handled = false

    const restoreDrinkEntry = (message = 'Getränk konnte nicht gelöscht werden. Bitte versuche es erneut.') => {
        handled = true
        removeDeletingDrinkEntry(entry.id)
        drinkError.value = message
    }

    router.delete(route('auth.nutrition.meals.destroy', entry.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            handled = true
            removeDeletingDrinkEntry(entry.id)
            activeSection.value = 'drink'
        },
        onError: () => restoreDrinkEntry(),
        onCancel: () => restoreDrinkEntry('Löschen wurde abgebrochen. Der Eintrag ist wieder sichtbar.'),
        onFinish: () => {
            if (!handled) {
                restoreDrinkEntry()
            }
        },
    })
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
    mealForm.notes = `Quelle: ${food.attribution || 'Open Food Facts'}. Werte bitte prüfen, da offene Daten unvollständig sein können.`
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
        foodLookupError.value = 'Bitte einen gültigen Barcode eingeben.'
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

const closeDrinkSelectOnOutsideClick = (event) => {
    if (!drinkSelectRef.value?.contains(event.target)) {
        drinkSelectOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', closeDrinkSelectOnOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeDrinkSelectOnOutsideClick)
})
</script>

<template>
    <Head :title="tAuto('Ernährung')" />

    <div class="space-y-4">
        <section class="rounded-2xl border border-border bg-card p-3 sm:p-4 lg:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Airmius Fuel') }}</p>
                    <h1 class="mt-1 text-xl font-bold leading-tight text-primary sm:text-2xl">{{ tAuto('Ernährung') }}</h1>
                    <p class="mt-1 hidden max-w-2xl text-sm leading-6 text-secondary sm:block">
                        {{ tAuto('Heute sehen, schnell erfassen, Ziele ruhig anpassen. Keine überladene Arbeitsfläche mehr.') }}
                    </p>
                </div>
                <div class="flex w-full gap-2 sm:w-auto">
                    <input v-model="selectedDateValue" type="date" class="min-w-0 flex-1 rounded-xl border border-border bg-inputBg px-3 py-2 text-sm text-primary sm:w-44" @change="changeDate">
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" @click="changeDate">
                        {{ tAuto('Laden') }}
                    </button>
                </div>
            </div>
        </section>

        <nav class="custom-scrollbar -mx-1 flex gap-2 overflow-x-auto px-1 pb-1 sm:mx-0 sm:grid sm:grid-cols-5 sm:overflow-visible sm:px-0 sm:pb-0">
            <button
                v-for="section in nutritionSections"
                :key="section.key"
                type="button"
                :class="[
                    'flex min-w-[88px] flex-col items-center justify-center gap-1 rounded-2xl border px-3 py-3 text-center transition sm:min-w-0 sm:flex-row sm:justify-start sm:gap-3 sm:rounded-xl sm:text-start',
                    activeSection === section.key
                        ? 'border-air-blue bg-air-blue/15 text-primary shadow-lg shadow-air-blue/10'
                        : 'border-transparent text-secondary hover:border-border hover:bg-inputBg'
                ]"
                @click="activeSection = section.key"
            >
                <i :class="[section.icon, 'text-xl sm:text-xl']"></i>
                <span class="min-w-0">
                    <span class="block text-xs font-bold sm:text-sm">{{ section.label }}</span>
                    <span class="hidden truncate text-xs opacity-80 sm:block">{{ section.hint }}</span>
                </span>
            </button>
        </nav>

        <section v-if="activeSection === 'today'" class="grid gap-4 xl:grid-cols-[minmax(0,0.95fr),minmax(360px,0.75fr)]">
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
                            <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary" @click="activeSection = 'add'">
                                {{ tAuto('Mahlzeit erfassen') }}
                            </button>
                            <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted" @click="activeSection = 'ideas'">
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

                    <div class="mt-4 rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase text-cyan-200">{{ tAuto('Trinken') }}</p>
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
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <button type="button" class="rounded-lg border border-border px-2.5 py-2 text-primary hover:bg-muted" :title="tAuto('Bearbeiten')" @click="editMeal(meal)">
                                    <i class="las la-pen"></i>
                                </button>
                                <button type="button" class="rounded-lg border border-danger/30 px-2.5 py-2 text-danger hover:bg-danger/10" :title="tAuto('Löschen')" @click="deleteCandidate = meal">
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
                </div>
            </section>
        </section>

        <section v-if="activeSection === 'add'" class="grid gap-4 xl:grid-cols-[minmax(0,0.85fr),minmax(340px,0.65fr)]">
            <form class="rounded-2xl border border-border bg-card p-4 lg:p-5" @submit.prevent="submitMeal">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Mahlzeit') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ editingMealId ? tAuto('Mahlzeit bearbeiten') : tAuto('Schnell erfassen') }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ tAuto('Nur Name und Kalorien sind Pflicht. Details bleiben optional.') }}</p>
                    </div>
                    <button v-if="editingMealId" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted" @click="resetMealForm">
                        {{ tAuto('Neu') }}
                    </button>
                </div>

                <section class="mt-4 rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase text-cyan-200">{{ tAuto('KI-Fotoanalyse') }}</p>
                            <h3 class="mt-1 text-base font-black text-primary">{{ tAuto('Kalorien aus Bild schützen') }}</h3>
                            <p class="mt-1 text-sm leading-6 text-secondary">
                                {{ tAuto('Bild wird verkleinert, EXIF wird entfernt. Ergebnis bleibt ein Vorschlag und muss von dir bestätigt werden.') }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full bg-card px-3 py-1 text-xs font-black text-cyan-100">
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
                                ref="aiMealImageInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-sm text-primary file:mr-3 file:rounded-lg file:border-0 file:bg-buttonPrimary file:px-3 file:py-2 file:text-sm file:font-bold file:text-buttonTextPrimary"
                                :disabled="aiMealAnalyzing"
                                @change="onAiMealImageSelected"
                            >
                        </label>
                        <button
                            type="button"
                            class="self-end rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60"
                            :disabled="aiMealAnalyzing || !aiMealImageAvailable"
                            @click="analyzeMealImage"
                        >
                            <span v-if="aiMealAnalyzing" class="inline-flex items-center gap-2">
                                <i class="las la-sync-alt animate-spin"></i>
                                {{ tAuto('Analysiere') }}
                            </span>
                            <span v-else>{{ tAuto('Bild analysieren') }}</span>
                        </button>
                    </div>

                    <label class="mt-3 flex items-start gap-3 rounded-xl border border-border bg-card/70 p-3 text-sm text-secondary">
                        <input v-model="aiMealConsent" type="checkbox" class="mt-1 rounded border-border bg-inputBg text-air-blue">
                        <span>{{ tAuto('Ich möchte dieses Bild zur KI-Analyse senden. Es wird nur für den Vorschlag genutzt und nicht automatisch als Mahlzeit gespeichert.') }}</span>
                    </label>

                    <p v-if="aiMealError" class="mt-3 rounded-xl border border-danger/30 bg-danger/10 p-3 text-sm font-semibold text-danger">
                        {{ aiMealError }}
                    </p>

                    <div v-if="aiMealSuggestion" class="mt-3 rounded-xl border border-cyan-300/30 bg-card p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-black text-primary">{{ aiMealSuggestion.title }}</p>
                                <p class="mt-1 text-xs leading-5 text-secondary">
                                    {{ tAuto(`${formatNumber(aiMealSuggestion.calories || 0)} kcal · ${formatNumber(aiMealSuggestion.protein_g || 0, 1)} g Protein · Sicherheit ${Math.round((aiMealSuggestion.confidence || 0) * 100)}%`) }}
                                </p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted" @click="applyAiMealSuggestion">
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

                <button type="button" class="mt-4 flex w-full items-center justify-between rounded-xl border border-border bg-inputBg px-4 py-3 text-sm font-bold text-primary hover:bg-muted" @click="showAdvancedMeal = !showAdvancedMeal">
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
                            <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-primary hover:bg-muted" @click="addItemRow">
                                {{ tAuto('Zeile') }}
                            </button>
                        </div>
                        <div class="mt-3 space-y-2">
                            <div v-for="(item, index) in mealForm.items" :key="index" class="grid gap-2 sm:grid-cols-[minmax(0,1fr),130px,auto]">
                                <input v-model="item.name" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="tAuto('Lebensmittel')">
                                <input v-model="item.amount" class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="tAuto('Menge')">
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-danger hover:bg-danger/10" @click="removeItemRow(index)">
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
                            <input v-model="foodSearchQuery" class="rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="tAuto('z. B. Skyr, Banane')">
                            <button type="button" class="rounded-xl border border-air-blue/40 px-4 py-2 text-sm font-bold text-primary hover:bg-air-blue/15 disabled:opacity-60" :disabled="foodLookupLoading" @click="searchFoods">
                                {{ tAuto('Suchen') }}
                            </button>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr),auto]">
                            <input v-model="foodBarcode" class="rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary" :placeholder="tAuto('Barcode')">
                            <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted disabled:opacity-60" :disabled="foodLookupLoading" @click="lookupBarcode">
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
                            <button type="button" class="shrink-0 rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-bold text-buttonTextPrimary" @click="applyFoodResult(food)">
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

        <section v-if="activeSection === 'drink'" class="grid gap-3 sm:gap-4 xl:grid-cols-[minmax(0,0.85fr),minmax(320px,0.55fr)]">
            <form class="rounded-2xl border border-border bg-card p-3 sm:p-4 lg:p-5" @submit.prevent="submitDrink()">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-cyan-200">{{ tAuto('Trinken') }}</p>
                        <div class="mt-1 flex items-center gap-2">
                            <h2 class="text-xl font-black text-primary sm:text-2xl">{{ tAuto(`${formatWater(waterConsumedMl)} heute`) }}</h2>
                            <button
                                type="button"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border bg-inputBg text-secondary hover:border-cyan-300 hover:text-primary sm:h-9 sm:w-9"
                                :title="tAuto('Wasserziel einstellen')"
                                :aria-label="tAuto('Wasserziel einstellen')"
                                @click="activeSection = 'goals'"
                            >
                                <i class="las la-cog text-xl"></i>
                            </button>
                        </div>
                        <p class="mt-1 text-sm leading-5 text-secondary sm:leading-6">
                            {{ tAuto(`Ziel: ${formatWater(waterTargetMl)}. Noch ${formatWater(waterLeftMl)} offen.`) }}
                        </p>
                    </div>
                </div>

                <div class="mt-3 rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-3 sm:mt-5 sm:p-4">
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
                            class="rounded-xl border border-border bg-inputBg px-2 py-3 text-sm font-black text-primary hover:border-cyan-300 hover:bg-cyan-400/10 disabled:opacity-60"
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
                        <span class="hidden text-xs font-bold uppercase text-cyan-200 sm:inline">{{ tAuto('Airmius Quick Drink') }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
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
                                    <p class="truncate text-sm font-black text-primary">{{ tAuto(vessel.label) }}</p>
                                    <p class="mt-1 text-xs text-secondary">{{ tAuto(vessel.hint) }}</p>
                                </div>
                                <span class="rounded-full bg-card px-2.5 py-1 text-xs font-black text-cyan-100">{{ vessel.amount }} ml</span>
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
                            class="rounded-xl border border-border bg-inputBg px-4 py-4 text-base font-black text-primary hover:border-cyan-300 hover:bg-cyan-400/10 disabled:opacity-60"
                            :disabled="drinkForm.processing"
                            @click="submitDrink(amount)"
                        >
                            +{{ amount }} ml
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1.2fr),minmax(0,0.8fr),auto]">
                    <div ref="drinkSelectRef" class="relative block text-sm font-bold text-primary">
                        <span>{{ tAuto('Getränk') }}</span>
                        <div class="mt-2 flex rounded-xl border border-border bg-inputBg focus-within:border-cyan-300">
                            <input
                                :value="drinkSearchQuery"
                                class="min-w-0 flex-1 rounded-l-xl border-0 bg-transparent px-3 py-3 text-primary placeholder-secondary focus:ring-0"
                                :placeholder="tAuto('Getränk suchen oder eigenes schreiben')"
                                autocomplete="off"
                                @focus="drinkSelectOpen = true"
                                @input="updateDrinkSearch($event.target.value)"
                                @keydown.escape="drinkSelectOpen = false"
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
                                @click="drinkSelectOpen = !drinkSelectOpen"
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
                                class="mb-2 flex w-full items-center gap-3 rounded-xl border border-cyan-300/30 bg-cyan-400/10 px-3 py-3 text-left hover:bg-cyan-400/15"
                                @mousedown.prevent="useCustomDrinkName"
                            >
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-cyan-400/20 text-cyan-100">
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
                                <span class="shrink-0 rounded-full bg-inputBg px-2.5 py-1 text-xs font-black text-cyan-100">{{ drink.amount }} ml</span>
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
                            @click="showDrinkTips = true"
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
        </section>

        <section v-if="activeSection === 'goals'" class="grid gap-4 xl:grid-cols-[minmax(0,0.8fr),minmax(320px,0.6fr)]">
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
                <div class="mt-3 rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4">
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

        <section v-if="activeSection === 'ideas'" class="grid gap-4 xl:grid-cols-[minmax(0,0.85fr),minmax(320px,0.55fr)]">
            <section class="rounded-2xl border border-border bg-card p-4 lg:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Rezepte') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-primary">{{ tAuto('Schnell übernehmen') }}</h2>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" :class="['rounded-lg border px-3 py-2 text-xs font-bold', recipeFilter === 'all' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="recipeFilter = 'all'">
                            {{ tAuto('Alle') }}
                        </button>
                        <button type="button" :class="['rounded-lg border px-3 py-2 text-xs font-bold', recipeFilter === 'goal' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="recipeFilter = 'goal'">
                            {{ tAuto('Ziel') }}
                        </button>
                        <button type="button" :class="['rounded-lg border px-3 py-2 text-xs font-bold', recipeFilter === 'style' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border text-secondary hover:bg-muted']" @click="recipeFilter = 'style'">
                            {{ tAuto('Stil') }}
                        </button>
                    </div>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <article v-for="recipe in filteredRecipes" :key="recipe.key" class="rounded-xl border border-border bg-inputBg p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase text-secondary">{{ tAuto(recipe.category) }} - {{ recipe.prep_minutes }} {{ tAuto('min') }}</p>
                                <h3 class="mt-1 font-bold text-primary">{{ tAuto(recipe.title) }}</h3>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-primary hover:bg-muted" @click="applyRecipe(recipe)">
                                {{ tAuto('Nutzen') }}
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
                <p class="text-xs font-bold uppercase text-air-blue">{{ tAuto('Training-Bezug') }}</p>
                <div class="mt-3 space-y-2">
                    <article v-for="suggestion in trainingSuggestions" :key="suggestion.title + suggestion.body" class="rounded-xl border border-border bg-inputBg p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-primary">{{ tAuto(suggestion.title) }}</p>
                                <p class="mt-1 text-sm leading-6 text-secondary">{{ tAuto(suggestion.body) }}</p>
                            </div>
                            <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-primary hover:bg-muted" @click="applyTrainingSuggestion(suggestion)">
                                {{ tAuto('Nutzen') }}
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </section>

        <div v-if="deleteCandidate" class="fixed inset-0 z-[80] flex items-end justify-center bg-black/60 p-4 sm:items-center">
            <div class="w-full max-w-md rounded-2xl border border-border bg-card p-5 shadow-2xl">
                <h2 class="text-lg font-bold text-primary">{{ tAuto('Eintrag löschen?') }}</h2>
                <p class="mt-2 text-sm text-secondary">
                    {{ tAuto(`"${deleteCandidate.title}" wird aus deinem Tageslog entfernt.`) }}
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary hover:bg-muted" @click="deleteCandidate = null">
                        {{ tAuto('Abbrechen') }}
                    </button>
                    <button type="button" class="rounded-xl bg-danger px-4 py-2 text-sm font-bold text-white" @click="confirmDelete">
                        {{ tAuto('Löschen') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showDrinkTips" class="fixed inset-0 z-[90] flex items-end justify-center bg-black/65 p-0 sm:items-center sm:p-4" @click.self="showDrinkTips = false">
            <section class="flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-3xl border border-border bg-card shadow-2xl sm:rounded-2xl">
                <header class="flex items-start justify-between gap-3 border-b border-border p-4 sm:p-5">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase text-cyan-200">{{ tAuto('Trinken') }}</p>
                        <h2 class="mt-1 text-xl font-black text-primary sm:text-2xl">{{ tAuto('Tipps rund ums Trinken') }}</h2>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            {{ tAuto('Einfache Orientierung für Alltag, Training und Regeneration.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border text-primary hover:bg-muted"
                        :aria-label="tAuto('Schließen')"
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
                                    <h3 class="font-bold text-primary">{{ tAuto(tip.title) }}</h3>
                                    <p class="mt-1 text-sm leading-6 text-secondary">{{ tAuto(tip.body) }}</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="mt-4 rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4">
                        <p class="text-sm font-bold text-primary">{{ tAuto('Dein aktueller Stand') }}</p>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            {{ tAuto(`Heute: ${formatWater(waterConsumedMl)} von ${formatWater(waterTargetMl)}. Noch ${formatWater(waterLeftMl)} offen.`) }}
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
                        {{ tAuto('Mehr im Blog lesen') }}
                    </Link>
                    <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary" @click="showDrinkTips = false">
                        {{ tAuto('Verstanden') }}
                    </button>
                </footer>
            </section>
        </div>
    </div>
</template>
