<script setup>
defineProps({
    place: {
        type: Object,
        required: true,
    },
    placeTypeLabel: {
        type: Function,
        required: true,
    },
})
</script>

<template>
    <article class="rounded-lg border border-border bg-inputBg p-4">
        <img
            v-if="place.gallery_images?.length"
            :src="place.gallery_images[0]"
            :alt="place.name"
            class="mb-3 aspect-video w-full rounded-lg object-cover"
        >

        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-400 text-slate-950">
                <i class="las la-map-marker-alt"></i>
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-primary">{{ place.name }}</p>
                <p class="mt-1 text-xs text-secondary">{{ placeTypeLabel(place.type) }} - {{ place.location?.city || $t('sport_map.places.no_city') }}</p>
            </div>
        </div>

        <p v-if="place.description" class="mt-3 line-clamp-2 text-xs text-secondary">{{ place.description }}</p>

        <div v-if="place.sport_types?.length" class="mt-3 flex flex-wrap gap-2">
            <span v-for="sport in place.sport_types" :key="sport" class="rounded-full bg-card px-2 py-1 text-[11px] font-semibold text-secondary">{{ sport }}</span>
        </div>
    </article>
</template>
