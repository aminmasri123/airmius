<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    importLabelKey: {
        type: String,
        required: true,
    },
    labelForSport: {
        type: Function,
        required: true,
    },
    showVisibility: {
        type: Boolean,
        default: false,
    },
    sportTypes: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['select-file', 'submit'])

const visibilityOptions = ['private', 'public']
</script>

<template>
    <form class="mt-3 rounded-lg border border-dashed border-border bg-card p-3" @submit.prevent="emit('submit')">
        <div
            class="grid gap-3 sm:items-end"
            :class="showVisibility ? 'sm:grid-cols-[minmax(0,1fr),180px,160px,auto]' : 'sm:grid-cols-[minmax(0,1fr),180px,auto]'"
        >
            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t(importLabelKey) }}</span>
                <input
                    type="file"
                    accept=".gpx,application/gpx+xml,text/xml,application/xml"
                    class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm"
                    @change="emit('select-file', $event)"
                >
            </label>

            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.sport') }}</span>
                <select v-model="form.sport_type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                    <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ labelForSport(sport, sport.key) }}</option>
                </select>
            </label>

            <label v-if="showVisibility" class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.visibility') }}</span>
                <select v-model="form.visibility" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                    <option v-for="visibility in visibilityOptions" :key="visibility" :value="visibility">
                        {{ $t(`sport_map.visibility.${visibility}`) }}
                    </option>
                </select>
            </label>

            <button
                type="submit"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-buttonPrimary/40 px-3 py-2 text-xs font-bold text-buttonPrimary disabled:opacity-50"
                :disabled="form.processing || !form.gpx_file"
            >
                <i class="las la-file-import"></i>
                {{ form.processing ? $t('sport_map.gpx.importing') : $t('sport_map.gpx.import_action') }}
            </button>
        </div>

        <span v-if="form.errors.gpx || form.errors.gpx_file" class="mt-2 block text-xs font-semibold text-red-500">
            {{ form.errors.gpx || form.errors.gpx_file }}
        </span>
    </form>
</template>
