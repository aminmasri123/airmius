<script setup>
defineProps({
    activeMapLayer: {
        type: String,
        required: true,
    },
    layerOptions: {
        type: Array,
        default: () => [],
    },
    statusText: {
        type: String,
        default: '',
    },
})

defineEmits(['update:activeMapLayer'])
</script>

<template>
    <div class="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-primary">{{ $t('sport_map.map_preview') }}</p>
            <p class="text-xs text-secondary">{{ statusText }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex overflow-hidden rounded-lg border border-border bg-inputBg p-1">
                <button
                    v-for="layer in layerOptions"
                    :key="layer.key"
                    type="button"
                    class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md px-3 text-xs font-bold transition"
                    :class="activeMapLayer === layer.key ? 'bg-buttonPrimary text-buttonTextPrimary shadow-sm' : 'text-secondary hover:bg-card hover:text-primary'"
                    :aria-label="$t('sport_map.map_status.show_layer', { layer: layer.label })"
                    @click="$emit('update:activeMapLayer', layer.key)"
                >
                    <i :class="layer.icon"></i>
                    <span>{{ layer.label }}</span>
                </button>
            </div>
            <span class="rounded-full bg-inputBg px-3 py-1 text-xs font-semibold text-secondary">
                {{ $t('sport_map.map_status.interactive_map') }}
            </span>
        </div>
    </div>
</template>
