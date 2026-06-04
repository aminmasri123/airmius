<script setup>
import SportMapGpxImportForm from '@/Components/SportMap/SportMapGpxImportForm.vue'
import SportMapSavedRouteCard from '@/Components/SportMap/SportMapSavedRouteCard.vue'

defineProps({
    catalogLabel: {
        type: Function,
        required: true,
    },
    form: {
        type: Object,
        required: true,
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
    routes: {
        type: Array,
        default: () => [],
    },
    sportLabel: {
        type: Function,
        required: true,
    },
    sportTypes: {
        type: Array,
        default: () => [],
    },
    teams: {
        type: Array,
        default: () => [],
    },
    validWaypointCount: {
        type: Number,
        default: 0,
    },
    visibilityLabel: {
        type: Function,
        required: true,
    },
    waypointRows: {
        type: Array,
        default: () => [],
    },
})

defineEmits([
    'add-waypoint',
    'import-gpx',
    'remove-waypoint',
    'save',
    'select-gpx-file',
])
</script>

<template>
    <div class="grid gap-5 p-4 lg:grid-cols-[minmax(0,0.9fr),minmax(0,1.1fr)]">
        <form class="space-y-4" @submit.prevent="$emit('save')">
            <div>
                <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.routes.create_title') }}</h3>
                <p class="mt-1 text-sm text-secondary">{{ $t('sport_map.routes.create_hint') }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="space-y-1">
                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.title') }}</span>
                    <input v-model="form.title" required class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.sport') }}</span>
                    <select v-model="form.sport_type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                        <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.visibility') }}</span>
                    <select v-model="form.visibility" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                        <option value="private">{{ $t('sport_map.visibility.private') }}</option>
                        <option value="public">{{ $t('sport_map.visibility.public') }}</option>
                        <option value="team">{{ $t('sport_map.visibility.team') }}</option>
                    </select>
                </label>
                <label v-if="form.visibility === 'team'" class="space-y-1">
                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.team') }}</span>
                    <select v-model="form.team_id" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                        <option :value="null">-</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </label>
            </div>

            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.description') }}</span>
                <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm"></textarea>
            </label>

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-primary">{{ $t('sport_map.routes.waypoints') }}</p>
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-muted" @click="$emit('add-waypoint')">
                        <i class="las la-plus"></i>
                        {{ $t('sport_map.routes.add_waypoint') }}
                    </button>
                </div>

                <div v-for="(point, index) in waypointRows" :key="index" class="grid gap-2 rounded-lg border border-border bg-inputBg p-3 sm:grid-cols-[1fr,1fr,1fr,auto]">
                    <input v-model="point.name" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" :placeholder="$t('sport_map.form.name')">
                    <input v-model="point.latitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" :placeholder="$t('sport_map.form.latitude')">
                    <input v-model="point.longitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" :placeholder="$t('sport_map.form.longitude')">
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm hover:bg-muted" @click="$emit('remove-waypoint', index)">
                        <i class="las la-trash"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="form.processing || validWaypointCount < 2">
                <i class="las la-save"></i>
                {{ $t('sport_map.routes.save') }}
            </button>
        </form>

        <div>
            <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.routes.saved_title') }}</h3>
            <SportMapGpxImportForm
                :form="gpxImportForm"
                import-label-key="sport_map.gpx.route_import"
                :label-for-sport="catalogLabel"
                show-visibility
                :sport-types="sportTypes"
                @select-file="$emit('select-gpx-file', $event)"
                @submit="$emit('import-gpx')"
            />
            <div class="mt-3 grid gap-3">
                <SportMapSavedRouteCard
                    v-for="routeItem in routes"
                    :key="routeItem.id"
                    :format-distance="formatDistance"
                    :format-duration="formatDuration"
                    :route-item="routeItem"
                    :sport-label="sportLabel"
                    :visibility-label="visibilityLabel"
                />
            </div>
        </div>
    </div>
</template>
