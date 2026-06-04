<script setup>
import SportMapGpxImportForm from '@/Components/SportMap/SportMapGpxImportForm.vue'
import SportMapSavedTrackCard from '@/Components/SportMap/SportMapSavedTrackCard.vue'

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
    gpxImportForm: {
        type: Object,
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
    tracks: {
        type: Array,
        default: () => [],
    },
    trackStatusLabel: {
        type: Function,
        required: true,
    },
})

defineEmits([
    'cancel-edit',
    'edit',
    'import-gpx',
    'select-gpx-file',
    'update',
])
</script>

<template>
    <div class="rounded-2xl border border-border bg-inputBg p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ $t('sport_map.tracks.history') }}</p>
                <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.tracks.saved_title') }}</h3>
            </div>
            <span class="rounded-full bg-card px-3 py-1 text-xs font-semibold text-secondary">{{ $t('sport_map.tracks.count_label', { count: tracks.length }) }}</span>
        </div>

        <SportMapGpxImportForm
            :form="gpxImportForm"
            import-label-key="sport_map.gpx.track_import"
            :label-for-sport="catalogLabel"
            :sport-types="sportTypes"
            @select-file="$emit('select-gpx-file', $event)"
            @submit="$emit('import-gpx')"
        />

        <div class="mt-3 grid gap-3">
            <SportMapSavedTrackCard
                v-for="track in tracks"
                :key="track.id"
                :catalog-label="catalogLabel"
                :edit-form="editForm"
                :editing-track-id="editingTrackId"
                :format-distance="formatDistance"
                :format-duration="formatDuration"
                :sport-label="sportLabel"
                :sport-types="sportTypes"
                :track="track"
                :track-average-pace-label="trackAveragePaceLabel"
                :track-average-speed-label="trackAverageSpeedLabel"
                :track-calories-label="trackCaloriesLabel"
                :track-status-label="trackStatusLabel"
                @cancel-edit="$emit('cancel-edit')"
                @edit="$emit('edit', $event)"
                @update="$emit('update', $event)"
            />
            <div v-if="!tracks.length" class="rounded-xl border border-dashed border-border bg-card/60 p-5 text-center">
                <p class="text-sm font-bold text-primary">{{ $t('sport_map.tracks.empty_title') }}</p>
                <p class="mt-1 text-sm text-secondary">{{ $t('sport_map.tracks.empty_hint') }}</p>
            </div>
        </div>
    </div>
</template>
