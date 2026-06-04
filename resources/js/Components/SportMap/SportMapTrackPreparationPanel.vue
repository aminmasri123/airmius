<script setup>
defineProps({
    catalogLabel: {
        type: Function,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    routes: {
        type: Array,
        default: () => [],
    },
    sportTypes: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <details class="rounded-2xl border border-border bg-inputBg p-4">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <span>
                <span class="block text-xs font-bold uppercase tracking-wide text-secondary">{{ $t('sport_map.tracks.preparation') }}</span>
                <span class="block text-base font-bold text-primary">{{ $t('sport_map.tracks.details_title') }}</span>
            </span>
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-card text-secondary">
                <i class="las la-sliders-h"></i>
            </span>
        </summary>

        <div class="mt-4 grid gap-3 border-t border-border pt-4">
            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.title') }}</span>
                <input v-model="form.title" class="w-full rounded-xl border border-border bg-card px-3 py-3 text-sm" :placeholder="$t('sport_map.tracks.title_placeholder')">
            </label>
            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.form.sport') }}</span>
                <select v-model="form.sport_type" class="w-full rounded-xl border border-border bg-card px-3 py-3 text-sm">
                    <option v-for="sport in sportTypes" :key="sport.key" :value="sport.key">{{ catalogLabel(sport, sport.key) }}</option>
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-xs font-semibold text-secondary">{{ $t('sport_map.tracks.route_optional') }}</span>
                <select v-model="form.sport_route_id" class="w-full rounded-xl border border-border bg-card px-3 py-3 text-sm">
                    <option :value="null">{{ $t('sport_map.tracks.free_track') }}</option>
                    <option v-for="routeItem in routes" :key="routeItem.id" :value="routeItem.id">{{ routeItem.title }}</option>
                </select>
            </label>
        </div>
    </details>
</template>
