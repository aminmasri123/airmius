<script setup>
defineProps({
    catalogLabel: {
        type: Function,
        required: true,
    },
    destinationPoint: {
        type: Object,
        default: null,
    },
    form: {
        type: Object,
        required: true,
    },
    mapTarget: {
        type: String,
        default: 'start',
    },
    routeTypeLabel: {
        type: String,
        default: '-',
    },
    routeTypes: {
        type: Array,
        default: () => [],
    },
    sportTypes: {
        type: Array,
        default: () => [],
    },
    startModes: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits([
    'clear-destination',
    'set-destination-from-map-center',
    'set-map-target',
    'set-route-type',
    'set-start-mode',
    'use-current-location',
])
</script>

<template>
    <div class="grid gap-5 lg:grid-cols-[minmax(0,0.9fr),minmax(0,1.1fr)]">
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.generator.base_title') }}</h3>
                <p class="mt-1 text-sm leading-6 text-secondary">{{ $t('sport_map.generator.base_description') }}</p>
            </div>

            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.route_name_optional') }}</span>
                <input v-model="form.title" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.generator.route_name_placeholder')">
            </label>

            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.sport') }}</span>
                <select v-model="form.sport_type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                    <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                </select>
            </label>

            <div class="grid gap-2 sm:grid-cols-3">
                <button
                    v-for="mode in startModes"
                    :key="mode.key"
                    type="button"
                    class="rounded-xl border px-3 py-3 text-left text-sm font-semibold"
                    :class="form.start_mode === mode.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                    @click="emit('set-start-mode', mode.key)"
                >
                    <i :class="mode.icon"></i>
                    <span class="ml-2">{{ mode.labelKey ? $t(mode.labelKey) : mode.label }}</span>
                </button>
            </div>

            <div v-if="form.start_mode === 'manual'" class="grid gap-3 sm:grid-cols-2">
                <input v-model="form.start_latitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.form.latitude')">
                <input v-model="form.start_longitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.form.longitude')">
            </div>

            <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted" @click="emit('use-current-location')">
                <i class="las la-location-arrow"></i>
                {{ $t('sport_map.generator.use_current_location_start') }}
            </button>
        </div>

        <div class="space-y-4">
            <div class="grid gap-2 sm:grid-cols-2">
                <button
                    v-for="type in routeTypes"
                    :key="type.key"
                    type="button"
                    class="rounded-xl border px-4 py-4 text-left"
                    :class="form.route_type === type.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                    @click="emit('set-route-type', type.key)"
                >
                    <i :class="type.icon"></i>
                    <span class="ml-2 text-sm font-bold">{{ type.labelKey ? $t(type.labelKey) : type.label }}</span>
                    <span class="mt-2 block text-xs text-secondary">{{ type.descriptionKey ? $t(type.descriptionKey) : type.description }}</span>
                </button>
            </div>

            <div v-if="form.route_type === 'point_to_point'" class="rounded-xl border border-border bg-inputBg p-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-bold text-primary">{{ $t('sport_map.generator.destination_point') }}</p>
                        <p class="text-xs text-secondary">{{ $t('sport_map.generator.destination_hint') }}</p>
                    </div>
                    <div class="flex overflow-hidden rounded-lg border border-border">
                        <button
                            type="button"
                            class="px-3 py-2 text-xs font-bold"
                            :class="mapTarget === 'start' ? 'bg-air-blue text-white' : 'bg-card text-secondary hover:text-primary'"
                            @click="emit('set-map-target', 'start')"
                        >
                            {{ $t('sport_map.generator.set_start') }}
                        </button>
                        <button
                            type="button"
                            class="border-l border-border px-3 py-2 text-xs font-bold"
                            :class="mapTarget === 'destination' ? 'bg-air-blue text-white' : 'bg-card text-secondary hover:text-primary'"
                            @click="emit('set-map-target', 'destination')"
                        >
                            {{ $t('sport_map.generator.set_destination') }}
                        </button>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <input v-model="form.destination_latitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" :placeholder="$t('sport_map.generator.destination_latitude')">
                    <input v-model="form.destination_longitude" type="number" step="0.0000001" class="rounded-lg border border-border bg-card px-3 py-2 text-sm" :placeholder="$t('sport_map.generator.destination_longitude')">
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-muted" @click="emit('set-destination-from-map-center')">
                        {{ $t('sport_map.generator.destination_from_map_center') }}
                    </button>
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-muted" @click="emit('clear-destination')">
                        {{ $t('sport_map.generator.clear_destination') }}
                    </button>
                    <span v-if="destinationPoint" class="rounded-lg bg-emerald-500/10 px-3 py-2 text-xs font-bold text-emerald-500">
                        {{ $t('sport_map.generator.destination_set', { latitude: Number(destinationPoint.latitude).toFixed(5), longitude: Number(destinationPoint.longitude).toFixed(5) }) }}
                    </span>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-inputBg p-3">
                <div class="grid gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold"
                        :class="form.target_mode === 'distance' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-card text-secondary'"
                        @click="form.target_mode = 'distance'"
                    >
                        {{ $t('sport_map.generator.distance') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold"
                        :class="form.target_mode === 'duration' ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-card text-secondary'"
                        @click="form.target_mode = 'duration'"
                    >
                        {{ $t('sport_map.generator.duration') }}
                    </button>
                </div>

                <div class="mt-3 grid gap-3" :class="form.target_mode === 'duration' ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
                    <label v-if="form.target_mode === 'distance'" class="space-y-1">
                        <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.distance_km') }}</span>
                        <input v-model="form.distance_km" type="number" min="1" max="80" step="0.5" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                    </label>
                    <label v-if="form.target_mode === 'duration'" class="space-y-1">
                        <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.duration_minutes') }}</span>
                        <input v-model="form.duration_minutes" type="number" min="10" max="360" step="5" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                    </label>
                    <label v-if="form.target_mode === 'duration'" class="space-y-1">
                        <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.pace') }}</span>
                        <select v-model="form.pace_mode" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                            <option value="pace">{{ $t('sport_map.generator.pace_min_per_km') }}</option>
                            <option value="speed">km/h</option>
                        </select>
                    </label>
                    <label v-if="form.target_mode === 'duration' && form.pace_mode === 'pace'" class="space-y-1">
                        <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.pace_min_per_km') }}</span>
                        <input v-model="form.pace_min_per_km" type="number" min="2.5" max="30" step="0.1" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                    </label>
                    <label v-if="form.target_mode === 'duration' && form.pace_mode === 'speed'" class="space-y-1">
                        <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.generator.speed_kmh') }}</span>
                        <input v-model="form.speed_kmh" type="number" min="1" max="80" step="0.5" class="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm">
                    </label>
                </div>
            </div>

            <div class="rounded-xl border border-air-blue/30 bg-air-blue/10 p-4">
                <p class="text-sm font-bold text-primary">{{ $t('sport_map.generator.current_plan') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-secondary sm:grid-cols-4">
                    <span>{{ summary.distance }}</span>
                    <span>{{ summary.duration }}</span>
                    <span>{{ summary.pace }}</span>
                    <span>{{ routeTypeLabel }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
