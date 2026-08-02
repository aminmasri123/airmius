import { router, useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useNutritionWorkspace(props) {
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

        const trailingPunctuation = source.match(/([.!?])$/)?.[1]
        if (trailingPunctuation) {
            const normalized = source.slice(0, -trailingPunctuation.length).trim()

            if (dictionary[normalized]) {
                return `${dictionary[normalized]}${trailingPunctuation}`
            }
        }

        const leadingPunctuation = source.match(/^([.!?])/)?.[1]
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
        { label: 'Sprudelwasser', category: 'Wasser', amount: 250, aliases: ['sparkling water', 'wasser mit Kohlensäure'] },
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
            gradient: 'from-air-blue to-indigo-400',
            ring: 'border-air-blue/45 hover:border-air-blue',
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
        aiMealError.value = tAuto('Bitte zuerst ein Essensbild auswählen.')
            return
        }

        if (!aiMealConsent.value) {
        aiMealError.value = tAuto('Bitte bestätige zuerst die KI-Analyse.')
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
        aiMealError.value = error.response?.data?.message || tAuto('Bildanalyse konnte nicht abgeschlossen werden.')
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
        drinkError.value = tAuto('Bitte Menge zwischen 1 und 5000 ml eingeben.')
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
            drinkError.value = tAuto('Trinken konnte nicht gespeichert werden. Bitte versuche es erneut.')
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

    const restoreDrinkEntry = (message = tAuto('Getränk konnte nicht gelöscht werden. Bitte versuche es erneut.')) => {
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
        onCancel: () => restoreDrinkEntry(tAuto('Löschen wurde abgebrochen. Der Eintrag ist wieder sichtbar.')),
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
        foodLookupError.value = tAuto('Bitte mindestens 2 Zeichen eingeben.')
            return
        }

        foodLookupLoading.value = true

        try {
            const response = await window.axios.get(route('auth.nutrition.foods.search'), {
                params: { q: foodSearchQuery.value.trim() },
            })
            foodSearchResults.value = response.data?.data || []
            if (!foodSearchResults.value.length) {
            foodLookupError.value = tAuto('Keine passenden Lebensmittel gefunden.')
            }
        } catch (error) {
        foodLookupError.value = error.response?.data?.message || tAuto('Lebensmittel-Suche ist gerade nicht verfügbar.')
        } finally {
            foodLookupLoading.value = false
        }
    }

    const lookupBarcode = async () => {
        foodLookupError.value = ''
        foodSearchResults.value = []

        if (foodBarcode.value.trim().length < 6) {
        foodLookupError.value = tAuto('Bitte einen gültigen Barcode eingeben.')
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
        foodLookupError.value = error.response?.data?.message || tAuto('Barcode konnte nicht gefunden werden.')
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

    return {
        locale,
        messages,
        te,
        t,
        tAutoPattern,
        tAuto,
        selectedDateValue,
        editingMealId,
        deleteCandidate,
        activeSection,
        showAdvancedMeal,
        recipeFilter,
        foodSearchQuery,
        foodBarcode,
        foodLookupLoading,
        foodLookupError,
        foodSearchResults,
        aiMealImageFile,
        aiMealImageInput,
        aiMealConsent,
        aiMealAnalyzing,
        aiMealError,
        aiMealSuggestion,
        drinkSelectOpen,
        drinkSearchQuery,
        drinkSelectRef,
        quickDrinkAmounts,
        popularDrinkOptions,
        drinkVessels,
        showDrinkTips,
        pendingDrinkEntries,
        deletingDrinkEntries,
        drinkError,
        drinkTips,
        emptyItems,
        mealForm,
        drinkForm,
        goalForm,
        mealTypes,
        foodMealTypes,
        goalTypes,
        dietStyles,
        deletingDrinkIds,
        persistedDrinkEntries,
        pendingWaterMl,
        deletingWaterMl,
        drinkEntries,
        sortedMeals,
        selectedGoal,
        maxWeekCalories,
        caloriesLeft,
        waterRecommendation,
        waterBaseMl,
        waterTrainingExtraMl,
        suggestedWaterTargetMl,
        manualWaterTargetMl,
        waterTargetMl,
        maxWeekWater,
        waterConsumedMl,
        waterLeftMl,
        waterProgress,
        aiNutritionImage,
        aiMealImageAvailable,
        aiMealImageAccessReason,
        aiMealProviderLabel,
        filteredDrinkOptions,
        customDrinkNameAvailable,
        filteredRecipes,
        nutritionSections,
        macroCards,
        progressValue,
        numberLocale,
        formatNumber,
        formatWater,
        roundToWaterStep,
        clampWaterTarget,
        mealTypeMeta,
        normalizeDrinkSearch,
        changeDate,
        addItemRow,
        removeItemRow,
        resetMealForm,
        submitMeal,
        editMeal,
        onAiMealImageSelected,
        analyzeMealImage,
        applyAiMealSuggestion,
        saveGoal,
        removePendingDrinkEntry,
        removeDeletingDrinkEntry,
        updateDrinkSearch,
        selectDrinkOption,
        useCustomDrinkName,
        clearDrinkSearch,
        submitDrink,
        submitDrinkVessel,
        deleteDrinkEntry,
        applyRecipe,
        applyTrainingSuggestion,
        applyFoodResult,
        searchFoods,
        lookupBarcode,
        confirmDelete,
        closeDrinkSelectOnOutsideClick,
    }
}
