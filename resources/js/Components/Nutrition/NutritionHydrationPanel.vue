<script setup>
import NutritionDrinkTipsModal from '@/Components/Nutrition/NutritionDrinkTipsModal.vue'
import NutritionHydrationSidebar from '@/Components/Nutrition/NutritionHydrationSidebar.vue'
import NutritionHydrationTrackerForm from '@/Components/Nutrition/NutritionHydrationTrackerForm.vue'

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
    deletingDrinkEntries: { type: Array, default: () => [] },
    drinkEntries: { type: Array, default: () => [] },
    weeklySummaries: { type: Array, default: () => [] },
    maxWeekWater: { type: Number, required: true },
    showDrinkTips: { type: Boolean, default: false },
    drinkTips: { type: Array, default: () => [] },
    setActiveSection: { type: Function, required: true },
    setShowDrinkTips: { type: Function, required: true },
    setDrinkSelectOpen: { type: Function, required: true },
    setDrinkSelectElement: { type: Function, required: true },
    updateDrinkSearch: { type: Function, required: true },
    selectDrinkOption: { type: Function, required: true },
    useCustomDrinkName: { type: Function, required: true },
    clearDrinkSearch: { type: Function, required: true },
    submitDrink: { type: Function, required: true },
    submitDrinkVessel: { type: Function, required: true },
    deleteDrinkEntry: { type: Function, required: true },
})
</script>

<template>
    <section class="grid gap-3 sm:gap-4 xl:grid-cols-[minmax(0,0.85fr),minmax(320px,0.55fr)]">
        <NutritionHydrationTrackerForm
            :clear-drink-search="clearDrinkSearch"
            :custom-drink-name-available="customDrinkNameAvailable"
            :drink-error="drinkError"
            :drink-form="drinkForm"
            :drink-search-query="drinkSearchQuery"
            :drink-select-open="drinkSelectOpen"
            :drink-vessels="drinkVessels"
            :filtered-drink-options="filteredDrinkOptions"
            :format-water="formatWater"
            :goal-form="goalForm"
            :quick-drink-amounts="quickDrinkAmounts"
            :select-drink-option="selectDrinkOption"
            :set-active-section="setActiveSection"
            :set-drink-select-element="setDrinkSelectElement"
            :set-drink-select-open="setDrinkSelectOpen"
            :submit-drink="submitDrink"
            :submit-drink-vessel="submitDrinkVessel"
            :t-auto="tAuto"
            :update-drink-search="updateDrinkSearch"
            :use-custom-drink-name="useCustomDrinkName"
            :water-base-ml="waterBaseMl"
            :water-consumed-ml="waterConsumedMl"
            :water-left-ml="waterLeftMl"
            :water-progress="waterProgress"
            :water-target-ml="waterTargetMl"
            :water-training-extra-ml="waterTrainingExtraMl"
        />

        <NutritionHydrationSidebar
            :deleting-drink-entries="deletingDrinkEntries"
            :delete-drink-entry="deleteDrinkEntry"
            :drink-entries="drinkEntries"
            :format-water="formatWater"
            :max-week-water="maxWeekWater"
            :set-show-drink-tips="setShowDrinkTips"
            :t-auto="tAuto"
            :weekly-summaries="weeklySummaries"
        />
    </section>

    <NutritionDrinkTipsModal
        v-if="showDrinkTips"
        :drink-tips="drinkTips"
        :format-water="formatWater"
        :set-show-drink-tips="setShowDrinkTips"
        :t-auto="tAuto"
        :water-consumed-ml="waterConsumedMl"
        :water-left-ml="waterLeftMl"
        :water-progress="waterProgress"
        :water-target-ml="waterTargetMl"
    />
</template>
