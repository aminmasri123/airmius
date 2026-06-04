<script setup>
defineProps({
    activeTab: {
        type: String,
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
    routes: {
        type: Array,
        default: () => [],
    },
    selectedRouteId: {
        type: [Number, String],
        default: null,
    },
    sportLabel: {
        type: Function,
        required: true,
    },
    tabLabel: {
        type: Function,
        required: true,
    },
    tabs: {
        type: Array,
        default: () => [],
    },
})

defineEmits(['select-tab', 'update:selectedRouteId'])
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="flex min-h-11 items-center justify-center gap-2 rounded-lg border px-2 text-sm font-semibold"
                :class="activeTab === tab.key ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border bg-inputBg text-secondary hover:text-primary'"
                :aria-label="tabLabel(tab)"
                @click="$emit('select-tab', tab.key)"
            >
                <i :class="tab.icon"></i>
                <span class="hidden sm:inline">{{ tabLabel(tab) }}</span>
            </button>
        </div>

        <div class="mt-4 space-y-3">
            <button
                v-for="routeItem in routes.slice(0, 5)"
                :key="routeItem.id"
                type="button"
                class="w-full rounded-lg border border-border bg-inputBg p-3 text-left hover:bg-muted"
                :class="{ 'border-buttonPrimary': selectedRouteId === routeItem.id }"
                @click="$emit('update:selectedRouteId', routeItem.id)"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="truncate text-sm font-semibold text-primary">{{ routeItem.title }}</p>
                    <span class="shrink-0 text-xs font-semibold text-secondary">{{ formatDistance(routeItem.distance_meters) }}</span>
                </div>
                <p class="mt-1 text-xs text-secondary">
                    {{ sportLabel(routeItem.sport_type) }} - {{ formatDuration(routeItem.estimated_duration_seconds) }}
                </p>
            </button>
        </div>
    </div>
</template>
