<script setup>
import { computed } from 'vue'
import SettingsSportProfileMetricSections from '@/Components/Settings/SettingsSportProfileMetricSections.vue'
import SettingsSportProfilePicker from '@/Components/Settings/SettingsSportProfilePicker.vue'
import SettingsSportProfileReadiness from '@/Components/Settings/SettingsSportProfileReadiness.vue'
import SettingsSportProfileRegularFields from '@/Components/Settings/SettingsSportProfileRegularFields.vue'
import SettingsSportProfileTabs from '@/Components/Settings/SettingsSportProfileTabs.vue'

const props = defineProps({
    sportProfileNotice: { type: Object, default: null },
    savingSportProfileId: { type: [Number, String], default: null },
    selectedSportProfileId: { type: [Number, String], default: '' },
    sportProfileSearch: { type: String, default: '' },
    sportProfilePickerOpen: { type: Boolean, default: false },
    activeSportProfileId: { type: [Number, String], default: '' },
    selectedSportProfiles: { type: Array, default: () => [] },
    availableSportProfiles: { type: Array, default: () => [] },
    filteredAvailableSportProfiles: { type: Array, default: () => [] },
    selectedSportProfile: { type: Object, default: null },
    sportProfileForms: { type: Object, required: true },
    trainingDayOptions: { type: Array, default: () => [] },
    todayDate: { type: String, required: true },
    sportProfileText: { type: Function, required: true },
    clearSportProfileChoice: { type: Function, required: true },
    chooseSportProfile: { type: Function, required: true },
    addSelectedSportProfile: { type: Function, required: true },
    sportProfileGroupLabel: { type: Function, required: true },
    openSportProfileRemoveModal: { type: Function, required: true },
    sportMetricLabel: { type: Function, required: true },
    sportStatusLabel: { type: Function, required: true },
    sportExperienceLabel: { type: Function, required: true },
    metricVisibilityLabel: { type: Function, required: true },
    performanceSectionsForSportProfile: { type: Function, required: true },
    sportMetricInputType: { type: Function, required: true },
    isMetricUnknown: { type: Function, required: true },
    sportMetricPlaceholder: { type: Function, required: true },
    clearMetricUnknown: { type: Function, required: true },
    toggleMetricUnknown: { type: Function, required: true },
    sportProfileRegularFields: { type: Function, required: true },
    isTrainingDaysField: { type: Function, required: true },
    isTrainingDaySelected: { type: Function, required: true },
    trainingDayLabel: { type: Function, required: true },
    toggleTrainingDay: { type: Function, required: true },
    isExperienceDateField: { type: Function, required: true },
    sportExperienceDuration: { type: Function, required: true },
    saveSportProfile: { type: Function, required: true },
})

const emit = defineEmits([
    'update:selectedSportProfileId',
    'update:sportProfileSearch',
    'update:sportProfilePickerOpen',
    'update:activeSportProfileId',
])

const selectedSportProfileIdModel = computed({
    get: () => props.selectedSportProfileId,
    set: (value) => emit('update:selectedSportProfileId', value),
})
const sportProfileSearchModel = computed({
    get: () => props.sportProfileSearch,
    set: (value) => emit('update:sportProfileSearch', value),
})
const sportProfilePickerOpenModel = computed({
    get: () => props.sportProfilePickerOpen,
    set: (value) => emit('update:sportProfilePickerOpen', value),
})
const activeSportProfileIdModel = computed({
    get: () => props.activeSportProfileId,
    set: (value) => emit('update:activeSportProfileId', value),
})
</script>

<template>
    <div class="space-y-5">
        <SettingsSportProfilePicker
            v-model:selected-sport-profile-id="selectedSportProfileIdModel"
            v-model:sport-profile-search="sportProfileSearchModel"
            v-model:sport-profile-picker-open="sportProfilePickerOpenModel"
            :sport-profile-notice="sportProfileNotice"
            :saving-sport-profile-id="savingSportProfileId"
            :available-sport-profiles="availableSportProfiles"
            :filtered-available-sport-profiles="filteredAvailableSportProfiles"
            :selected-sport-profile="selectedSportProfile"
            :sport-profile-text="sportProfileText"
            :clear-sport-profile-choice="clearSportProfileChoice"
            :choose-sport-profile="chooseSportProfile"
            :add-selected-sport-profile="addSelectedSportProfile"
        />

        <div v-if="selectedSportProfiles.length" class="space-y-4">
            <SettingsSportProfileTabs
                v-model:active-sport-profile-id="activeSportProfileIdModel"
                :selected-sport-profiles="selectedSportProfiles"
                :sport-profile-group-label="sportProfileGroupLabel"
            />

            <article
                v-for="profile in selectedSportProfiles"
                :key="profile.sport.id"
                v-show="Number(activeSportProfileIdModel) === Number(profile.sport.id)"
                class="surface-card overflow-hidden"
            >
                <SettingsSportProfileReadiness
                    :profile="profile"
                    :saving-sport-profile-id="savingSportProfileId"
                    :sport-profile-text="sportProfileText"
                    :sport-profile-group-label="sportProfileGroupLabel"
                    :sport-metric-label="sportMetricLabel"
                    :open-sport-profile-remove-modal="openSportProfileRemoveModal"
                />

                <form
                    v-if="sportProfileForms[profile.sport.id]"
                    class="space-y-4 p-5"
                    @submit.prevent="saveSportProfile(profile)"
                >
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="block text-sm font-semibold text-primary">{{ sportProfileText('status_label', 'Status') }}
                            <select v-model="sportProfileForms[profile.sport.id].status" class="input mt-2">
                                <option value="active">{{ sportStatusLabel('active') }}</option>
                                <option value="wants_to_learn">{{ sportStatusLabel('wants_to_learn') }}</option>
                                <option value="coach">{{ sportStatusLabel('coach') }}</option>
                                <option value="interested">{{ sportStatusLabel('interested') }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">{{ sportProfileText('level_label', 'Niveau') }}
                            <select v-model="sportProfileForms[profile.sport.id].experience_level" class="input mt-2">
                                <option value="beginner">{{ sportExperienceLabel('beginner') }}</option>
                                <option value="intermediate">{{ sportExperienceLabel('intermediate') }}</option>
                                <option value="advanced">{{ sportExperienceLabel('advanced') }}</option>
                                <option value="expert">{{ sportExperienceLabel('expert') }}</option>
                                <option value="elite">{{ sportExperienceLabel('elite') }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-primary">{{ sportProfileText('profile_visibility_label', 'Profil-Sichtbarkeit') }}
                            <select v-model="sportProfileForms[profile.sport.id].visibility" class="input mt-2">
                                <option value="private">{{ metricVisibilityLabel('private') }}</option>
                                <option value="trainer">{{ metricVisibilityLabel('trainer') }}</option>
                                <option value="public">{{ metricVisibilityLabel('public') }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-3">
                        <SettingsSportProfileMetricSections
                            :profile="profile"
                            :form="sportProfileForms[profile.sport.id]"
                            :sport-profile-text="sportProfileText"
                            :sport-metric-label="sportMetricLabel"
                            :metric-visibility-label="metricVisibilityLabel"
                            :performance-sections-for-sport-profile="performanceSectionsForSportProfile"
                            :sport-metric-input-type="sportMetricInputType"
                            :is-metric-unknown="isMetricUnknown"
                            :sport-metric-placeholder="sportMetricPlaceholder"
                            :clear-metric-unknown="clearMetricUnknown"
                            :toggle-metric-unknown="toggleMetricUnknown"
                        />

                        <SettingsSportProfileRegularFields
                            :profile="profile"
                            :form="sportProfileForms[profile.sport.id]"
                            :training-day-options="trainingDayOptions"
                            :today-date="todayDate"
                            :sport-profile-text="sportProfileText"
                            :sport-metric-label="sportMetricLabel"
                            :metric-visibility-label="metricVisibilityLabel"
                            :sport-metric-input-type="sportMetricInputType"
                            :is-metric-unknown="isMetricUnknown"
                            :sport-metric-placeholder="sportMetricPlaceholder"
                            :clear-metric-unknown="clearMetricUnknown"
                            :toggle-metric-unknown="toggleMetricUnknown"
                            :sport-profile-regular-fields="sportProfileRegularFields"
                            :is-training-days-field="isTrainingDaysField"
                            :is-training-day-selected="isTrainingDaySelected"
                            :training-day-label="trainingDayLabel"
                            :toggle-training-day="toggleTrainingDay"
                            :is-experience-date-field="isExperienceDateField"
                            :sport-experience-duration="sportExperienceDuration"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                        :disabled="savingSportProfileId === profile.sport.id"
                    >
                        {{ savingSportProfileId === profile.sport.id ? sportProfileText('saving', 'Speichert...') : sportProfileText('save_profile', 'Sportprofil speichern') }}
                    </button>
                </form>
            </article>
        </div>

        <div v-else class="surface-card p-6 text-sm text-secondary">
            {{ sportProfileText('empty_selected', 'Noch keine Sportart ausgewählt. Füge oben zuerst die Sportart hinzu, die du wirklich trainierst.') }}
        </div>
    </div>
</template>

