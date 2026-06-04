<script setup>
defineProps({
    profile: { type: Object, required: true },
    form: { type: Object, required: true },
    trainingDayOptions: { type: Array, default: () => [] },
    todayDate: { type: String, required: true },
    sportProfileText: { type: Function, required: true },
    sportMetricLabel: { type: Function, required: true },
    metricVisibilityLabel: { type: Function, required: true },
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
})
</script>

<template>
    <div
        v-for="field in sportProfileRegularFields(profile)"
        :key="field.key"
        class="rounded-xl border border-border bg-bg p-3"
    >
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <label class="block flex-1 text-sm font-semibold text-primary">
                {{ sportMetricLabel(field) }}
                <span v-if="field.required" class="text-error">*</span>
                <span v-if="field.unit" class="text-secondary">({{ field.unit }})</span>
                <div
                    v-if="isTrainingDaysField(field)"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <button
                        v-for="day in trainingDayOptions"
                        :key="day.key"
                        type="button"
                        class="min-w-12 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                        :class="isTrainingDaySelected(form, field, day.key)
                            ? 'border-air-blue bg-air-blue/20 text-air-blue'
                            : 'border-border bg-inputBg text-secondary hover:border-air-blue/60 hover:text-primary'"
                        :disabled="isMetricUnknown(form, field)"
                        :aria-pressed="isTrainingDaySelected(form, field, day.key)"
                        :title="trainingDayLabel(day.key, 'long')"
                        @click="toggleTrainingDay(form, field, day.key)"
                    >
                        {{ trainingDayLabel(day.key, 'short') }}
                    </button>
                </div>
                <textarea
                    v-else-if="field.type === 'textarea'"
                    v-model="form.metrics[field.key]"
                    rows="2"
                    :disabled="isMetricUnknown(form, field)"
                    class="input mt-2"
                    :placeholder="isMetricUnknown(form, field) ? sportProfileText('unknown_placeholder', 'Wird vorsichtig geschätzt') : sportMetricPlaceholder(field)"
                    @input="clearMetricUnknown(form, field)"
                />
                <input
                    v-else
                    v-model="form.metrics[field.key]"
                    :type="sportMetricInputType(field)"
                    :step="field.type === 'number' && !isExperienceDateField(field) ? '0.01' : undefined"
                    :max="isExperienceDateField(field) ? todayDate : undefined"
                    :disabled="isMetricUnknown(form, field)"
                    class="input mt-2"
                    :placeholder="isMetricUnknown(form, field) ? sportProfileText('unknown_placeholder', 'Wird vorsichtig geschätzt') : sportMetricPlaceholder(field)"
                    @input="clearMetricUnknown(form, field)"
                />
                <button
                    v-if="field.required"
                    type="button"
                    class="mt-2 rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
                    :class="isMetricUnknown(form, field) ? 'border-air-blue/50 bg-air-blue/10 text-air-blue' : 'border-border text-secondary hover:text-primary'"
                    @click="toggleMetricUnknown(form, field)"
                >
                    {{ isMetricUnknown(form, field) ? sportProfileText('unknown_marked', 'Wird geschätzt') : sportProfileText('unknown_action', 'Weiß ich nicht') }}
                </button>
                <span
                    v-if="isExperienceDateField(field) && sportExperienceDuration(form.metrics[field.key])"
                    class="mt-2 block text-xs font-semibold text-air-blue"
                >
                    {{ sportExperienceDuration(form.metrics[field.key]) }}
                </span>
            </label>
            <label class="block w-full text-xs font-semibold uppercase tracking-wide text-secondary md:w-40">
                {{ sportProfileText('visible_label', 'Sichtbar') }}
                <select v-model="form.metric_visibility[field.key]" class="input mt-2 text-sm normal-case tracking-normal">
                    <option value="private">{{ metricVisibilityLabel('private') }}</option>
                    <option value="trainer">{{ metricVisibilityLabel('trainer') }}</option>
                    <option value="public">{{ metricVisibilityLabel('public') }}</option>
                </select>
            </label>
        </div>
    </div>
</template>

