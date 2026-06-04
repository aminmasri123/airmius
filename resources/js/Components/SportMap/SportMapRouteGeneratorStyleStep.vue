<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    surfaceOptions: {
        type: Array,
        default: () => [],
    },
    environmentOptions: {
        type: Array,
        default: () => [],
    },
    elevationOptions: {
        type: Array,
        default: () => [],
    },
    difficultyOptions: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <div class="grid gap-5 lg:grid-cols-2">
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.generator.style_title') }}</h3>
                <p class="mt-1 text-sm leading-6 text-secondary">{{ $t('sport_map.generator.style_description') }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase text-secondary">{{ $t('sport_map.generator.surface') }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="surface in surfaceOptions"
                        :key="surface.key"
                        type="button"
                        class="rounded-full border px-3 py-2 text-sm font-semibold"
                        :class="form.surface === surface.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                        @click="form.surface = surface.key"
                    >
                        {{ surface.labelKey ? $t(surface.labelKey) : surface.label }}
                    </button>
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase text-secondary">{{ $t('sport_map.generator.environment') }}</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="environment in environmentOptions"
                        :key="environment.key"
                        type="button"
                        class="rounded-xl border px-3 py-3 text-left text-sm font-semibold"
                        :class="form.environment === environment.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                        @click="form.environment = environment.key"
                    >
                        {{ environment.labelKey ? $t(environment.labelKey) : environment.label }}
                    </button>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase text-secondary">{{ $t('sport_map.generator.elevation') }}</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                    <button
                        v-for="elevation in elevationOptions"
                        :key="elevation.key"
                        type="button"
                        class="rounded-xl border px-3 py-3 text-sm font-semibold"
                        :class="form.elevation === elevation.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                        @click="form.elevation = elevation.key"
                    >
                        {{ elevation.labelKey ? $t(elevation.labelKey) : elevation.label }}
                    </button>
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase text-secondary">{{ $t('sport_map.generator.difficulty') }}</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                    <button
                        v-for="difficulty in difficultyOptions"
                        :key="difficulty.key"
                        type="button"
                        class="rounded-xl border px-3 py-3 text-sm font-semibold"
                        :class="form.difficulty === difficulty.key ? 'border-air-blue bg-air-blue/15 text-primary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                        @click="form.difficulty = difficulty.key"
                    >
                        {{ difficulty.labelKey ? $t(difficulty.labelKey) : difficulty.label }}
                    </button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="flex items-center gap-3 rounded-xl border border-border bg-inputBg p-3 text-sm font-semibold text-primary">
                    <input v-model="form.low_traffic" type="checkbox" class="rounded border-border">
                    {{ $t('sport_map.generator.low_traffic') }}
                </label>
                <label class="flex items-center gap-3 rounded-xl border border-border bg-inputBg p-3 text-sm font-semibold text-primary">
                    <input v-model="form.lit" type="checkbox" class="rounded border-border">
                    {{ $t('sport_map.generator.lit') }}
                </label>
                <label class="flex items-center gap-3 rounded-xl border border-border bg-inputBg p-3 text-sm font-semibold text-primary">
                    <input v-model="form.water_breaks" type="checkbox" class="rounded border-border">
                    {{ $t('sport_map.generator.water_breaks') }}
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <input v-model="form.include_places" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.generator.include_places_placeholder')">
                <input v-model="form.avoid_places" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.generator.avoid_places_placeholder')">
            </div>
        </div>
    </div>
</template>
