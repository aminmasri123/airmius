<script setup>
defineProps({
    placesCount: {
        type: Number,
        default: 0,
    },
    routesCount: {
        type: Number,
        default: 0,
    },
    tracksCount: {
        type: Number,
        default: 0,
    },
})
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">
                    {{ $t('sport_map.landing.eyebrow') }}
                </p>
                <h2 class="mt-2 text-2xl font-bold text-primary sm:text-3xl">
                    {{ $t('sport_map.title') }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-secondary">
                    {{ $t('sport_map.landing.subtitle') }}
                </p>
            </div>

            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="rounded-lg border border-border bg-inputBg px-3 py-2">
                    <p class="text-lg font-bold text-primary">{{ routesCount }}</p>
                    <p class="text-xs text-secondary">{{ $t('sport_map.stats.routes') }}</p>
                </div>
                <div class="rounded-lg border border-border bg-inputBg px-3 py-2">
                    <p class="text-lg font-bold text-primary">{{ tracksCount }}</p>
                    <p class="text-xs text-secondary">{{ $t('sport_map.stats.tracks') }}</p>
                </div>
                <div class="rounded-lg border border-border bg-inputBg px-3 py-2">
                    <p class="text-lg font-bold text-primary">{{ placesCount }}</p>
                    <p class="text-xs text-secondary">{{ $t('sport_map.stats.places') }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
