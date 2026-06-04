<script setup>
import SportMapPlaceImagesField from '@/Components/SportMap/SportMapPlaceImagesField.vue'
import SportMapSavedPlaceCard from '@/Components/SportMap/SportMapSavedPlaceCard.vue'
import { computed } from 'vue'

const props = defineProps({
    amenitiesText: {
        type: String,
        default: '',
    },
    catalogLabel: {
        type: Function,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    imageUrlsText: {
        type: String,
        default: '',
    },
    locationError: {
        type: String,
        default: '',
    },
    locationStatus: {
        type: String,
        default: '',
    },
    placeTypeLabel: {
        type: Function,
        required: true,
    },
    placeTypes: {
        type: Array,
        default: () => [],
    },
    places: {
        type: Array,
        default: () => [],
    },
    previewImages: {
        type: Array,
        default: () => [],
    },
    sportTypesText: {
        type: String,
        default: '',
    },
    surfacesText: {
        type: String,
        default: '',
    },
})

const emit = defineEmits([
    'save',
    'update:amenitiesText',
    'update:imageUrlsText',
    'update:sportTypesText',
    'update:surfacesText',
    'upload-images',
    'use-current-location',
])

const amenitiesTextModel = computed({
    get: () => props.amenitiesText,
    set: (value) => emit('update:amenitiesText', value),
})

const imageUrlsTextModel = computed({
    get: () => props.imageUrlsText,
    set: (value) => emit('update:imageUrlsText', value),
})

const sportTypesTextModel = computed({
    get: () => props.sportTypesText,
    set: (value) => emit('update:sportTypesText', value),
})

const surfacesTextModel = computed({
    get: () => props.surfacesText,
    set: (value) => emit('update:surfacesText', value),
})
</script>

<template>
    <div class="grid gap-5 p-4 lg:grid-cols-[minmax(0,0.9fr),minmax(0,1.1fr)]">
        <form class="space-y-4" @submit.prevent="$emit('save')">
            <div>
                <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.places.create_title') }}</h3>
                <p class="mt-1 text-sm text-secondary">{{ $t('sport_map.places.create_hint') }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="space-y-1">
                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.name') }}</span>
                    <input v-model="form.name" required class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.places.type') }}</span>
                    <select v-model="form.type" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                        <option v-for="type in placeTypes" :key="type.key" :value="type.key">{{ catalogLabel(type, type.key) }}</option>
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.latitude') }}</span>
                    <input v-model="form.latitude" required type="number" step="0.0000001" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.longitude') }}</span>
                    <input v-model="form.longitude" required type="number" step="0.0000001" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm">
                </label>
            </div>

            <div>
                <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold hover:bg-muted" @click="$emit('use-current-location')">
                    <i class="las la-crosshairs"></i>
                    {{ $t('sport_map.places.use_location') }}
                </button>
                <p v-if="locationStatus" class="mt-2 rounded-lg bg-air-blue/10 px-3 py-2 text-sm font-semibold text-air-blue">
                    {{ locationStatus }}
                </p>
                <p v-if="locationError" class="mt-2 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600">
                    {{ locationError }}
                </p>
            </div>

            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.description') }}</span>
                <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm"></textarea>
            </label>

            <div class="grid gap-3 sm:grid-cols-2">
                <input v-model="form.city" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.city')">
                <input v-model="form.address" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.address')">
                <input v-model="sportTypesTextModel" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.sports_placeholder')">
                <input v-model="amenitiesTextModel" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.amenities_placeholder')">
                <input v-model="surfacesTextModel" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.surfaces_placeholder')">
                <input v-model="form.opening_hours" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm" :placeholder="$t('sport_map.places.opening_hours')">
            </div>

            <SportMapPlaceImagesField
                v-model="imageUrlsTextModel"
                :preview-images="previewImages"
                @upload="$emit('upload-images', $event)"
            />

            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="form.processing">
                <i class="las la-map-pin"></i>
                {{ $t('sport_map.places.save') }}
            </button>
        </form>

        <div>
            <h3 class="text-lg font-bold text-primary">{{ $t('sport_map.places.saved_title') }}</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <SportMapSavedPlaceCard
                    v-for="place in places"
                    :key="place.id"
                    :place="place"
                    :place-type-label="placeTypeLabel"
                />
            </div>
        </div>
    </div>
</template>
