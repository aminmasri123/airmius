<script setup>
defineProps({
    profile: { type: Object, required: true },
    form: { type: Object, required: true },
    sportProfileText: { type: Function, required: true },
    sportMetricLabel: { type: Function, required: true },
    metricVisibilityLabel: { type: Function, required: true },
    performanceSectionsForSportProfile: { type: Function, required: true },
    sportMetricInputType: { type: Function, required: true },
    isMetricUnknown: { type: Function, required: true },
    sportMetricPlaceholder: { type: Function, required: true },
    clearMetricUnknown: { type: Function, required: true },
    toggleMetricUnknown: { type: Function, required: true },
})
</script>

<template>
    <section
        v-for="section in performanceSectionsForSportProfile(profile)"
        :key="section.key"
        class="rounded-xl border p-3"
        :class="section.classes"
    >
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">
                    {{ sportProfileText(section.titleKey, section.title) }}
                </p>
                <p class="text-xs text-secondary">
                    {{ sportProfileText(section.hintKey, section.hint) }}
                </p>
            </div>
            <span class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                {{ sportProfileText('optional', 'Optional') }}
            </span>
        </div>

        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="field in section.fields"
                :key="field.key"
                class="rounded-xl border border-border bg-bg p-3"
            >
                <label class="block text-xs font-semibold uppercase tracking-wide text-secondary">
                    {{ sportMetricLabel(field) }}
                    <span v-if="field.unit" class="normal-case text-secondary">({{ field.unit }})</span>
                    <input
                        v-model="form.metrics[field.key]"
                        :type="sportMetricInputType(field)"
                        :step="field.type === 'number' ? '0.01' : undefined"
                        :min="field.type === 'number' ? 0 : undefined"
                        :disabled="isMetricUnknown(form, field)"
                        class="input mt-2 text-sm normal-case tracking-normal"
                        :placeholder="isMetricUnknown(form, field) ? sportProfileText('unknown_placeholder', 'Wird vorsichtig geschätzt') : sportMetricPlaceholder(field)"
                        @input="clearMetricUnknown(form, field)"
                    />
                </label>
                <button
                    v-if="field.required"
                    type="button"
                    class="mt-2 rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
                    :class="isMetricUnknown(form, field) ? 'border-air-blue/50 bg-air-blue/10 text-air-blue' : 'border-border text-secondary hover:text-primary'"
                    @click="toggleMetricUnknown(form, field)"
                >
                    {{ isMetricUnknown(form, field) ? sportProfileText('unknown_marked', 'Wird geschätzt') : sportProfileText('unknown_action', 'Weiß ich nicht') }}
                </button>
                <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-secondary">
                    {{ sportProfileText('visible_label', 'Sichtbar') }}
                    <select v-model="form.metric_visibility[field.key]" class="input mt-1 text-sm normal-case tracking-normal">
                        <option value="private">{{ metricVisibilityLabel('private') }}</option>
                        <option value="trainer">{{ metricVisibilityLabel('trainer') }}</option>
                        <option value="public">{{ metricVisibilityLabel('public') }}</option>
                    </select>
                </label>
            </div>
        </div>
    </section>
</template>

