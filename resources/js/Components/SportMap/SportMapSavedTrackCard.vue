<script setup>
defineProps({
    catalogLabel: {
        type: Function,
        required: true,
    },
    editForm: {
        type: Object,
        required: true,
    },
    editingTrackId: {
        type: [Number, String, null],
        default: null,
    },
    formatDistance: {
        type: Function,
        required: true,
    },
    formatDuration: {
        type: Function,
        required: true,
    },
    sportLabel: {
        type: Function,
        required: true,
    },
    sportTypes: {
        type: Array,
        default: () => [],
    },
    track: {
        type: Object,
        required: true,
    },
    trackAveragePaceLabel: {
        type: Function,
        required: true,
    },
    trackAverageSpeedLabel: {
        type: Function,
        required: true,
    },
    trackCaloriesLabel: {
        type: Function,
        required: true,
    },
    trackStatusLabel: {
        type: Function,
        required: true,
    },
})

const emit = defineEmits(['cancel-edit', 'edit', 'update'])
</script>

<template>
    <article class="rounded-xl border border-border bg-card p-4">
        <div v-if="editingTrackId === track.id" class="grid gap-3">
            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.title') }}</span>
                <input
                    v-model="editForm.title"
                    class="w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-sm font-semibold text-primary"
                    :placeholder="$t('sport_map.tracks.edit_title_placeholder')"
                >
                <span v-if="editForm.errors.title" class="text-xs font-semibold text-red-500">{{ editForm.errors.title }}</span>
            </label>

            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.sport') }}</span>
                <select v-model="editForm.sport_type" class="w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-sm font-semibold text-primary">
                    <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                </select>
                <span v-if="editForm.errors.sport_type" class="text-xs font-semibold text-red-500">{{ editForm.errors.sport_type }}</span>
            </label>

            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-50"
                    :disabled="editForm.processing || !editForm.title"
                    @click="emit('update', track)"
                >
                    {{ $t('sport_map.tracks.save_short') }}
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-border px-4 py-2 text-sm font-bold text-primary"
                    :disabled="editForm.processing"
                    @click="emit('cancel-edit')"
                >
                    {{ $t('sport_map.tracks.cancel') }}
                </button>
            </div>
        </div>

        <template v-else>
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-primary">{{ track.title }}</p>
                    <p class="mt-1 text-xs text-secondary">{{ sportLabel(track.sport_type) }} - {{ trackStatusLabel(track.status) }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-primary">{{ formatDistance(track.distance_meters) }}</span>
                    <button
                        v-if="track.can_edit"
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-inputBg text-primary hover:bg-muted"
                        :aria-label="$t('sport_map.tracks.edit')"
                        @click="emit('edit', track)"
                    >
                        <i class="las la-pen"></i>
                    </button>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-secondary sm:grid-cols-4">
                <span class="rounded-lg bg-inputBg px-3 py-2">{{ $t('sport_map.tracks.metrics.duration') }}: {{ formatDuration(track.duration_seconds) }}</span>
                <span class="rounded-lg bg-inputBg px-3 py-2">{{ $t('sport_map.tracks.metrics.pace') }}: {{ trackAveragePaceLabel(track) }}</span>
                <span class="rounded-lg bg-inputBg px-3 py-2">{{ $t('sport_map.tracks.metrics.speed') }}: {{ trackAverageSpeedLabel(track) }}</span>
                <span class="rounded-lg bg-inputBg px-3 py-2">{{ $t('sport_map.tracks.metrics.calories') }}: {{ trackCaloriesLabel(track) }}</span>
            </div>

            <a
                v-if="track.gpx?.web_export_url || track.gpx?.export_url"
                :href="track.gpx?.web_export_url || track.gpx?.export_url"
                class="mt-3 inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-xs font-bold text-primary hover:bg-muted"
            >
                <i class="las la-file-export"></i>
                {{ $t('sport_map.gpx.export') }}
            </a>
        </template>
    </article>
</template>
