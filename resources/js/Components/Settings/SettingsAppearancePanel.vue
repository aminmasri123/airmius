<script setup>
defineProps({
    settingsText: { type: Function, required: true },
    themeDescription: { type: Function, required: true },
    themeOptions: { type: Array, default: () => [] },
    updateTheme: { type: Function, required: true },
})
</script>

<template>
    <div class="surface-card p-5">
        <h2 class="mb-3 text-sm font-semibold text-secondary">{{ settingsText('design.title', 'Design') }}</h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <button
                v-for="themeOption in themeOptions"
                :key="themeOption.key"
                type="button"
                class="rounded-lg border border-border bg-bg p-4 text-left transition hover:border-borderHover hover:bg-muted"
                @click="updateTheme(themeOption.key)"
            >
                <span class="flex items-center gap-2">
                    <span
                        v-for="color in themeOption.colors"
                        :key="color"
                        class="h-5 w-5 rounded-full border border-border"
                        :style="{ backgroundColor: color }"
                    ></span>
                </span>
                <span class="mt-3 block font-semibold text-primary">{{ themeOption.label }}</span>
                <span class="mt-1 block text-xs text-secondary">{{ themeDescription(themeOption) }}</span>
            </button>
        </div>
    </div>
</template>
