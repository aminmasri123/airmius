<script setup>
import { computed } from 'vue'

const props = defineProps({
    activeSection: { type: String, required: true },
    sections: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:activeSection'])

const selectedSection = computed({
    get: () => props.activeSection,
    set: (value) => emit('update:activeSection', value),
})
</script>

<template>
    <nav class="flex gap-1.5 overflow-x-auto rounded-2xl border border-border bg-card p-1.5 md:grid md:grid-cols-5 md:gap-2 md:overflow-visible md:p-2">
        <button
            v-for="section in sections"
            :key="section.key"
            type="button"
            :class="[
                'flex min-w-[76px] shrink-0 items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-center transition md:min-w-0 md:justify-start md:gap-3 md:px-3 md:py-3 md:text-start',
                selectedSection === section.key
                    ? 'border-air-blue bg-air-blue/15 text-primary shadow-lg shadow-air-blue/10'
                    : 'border-transparent text-secondary hover:border-border hover:bg-inputBg'
            ]"
            @click="selectedSection = section.key"
        >
            <i :class="[section.icon, 'text-lg md:text-xl']"></i>
            <span class="min-w-0">
                <span class="block text-xs font-semibold md:text-sm">{{ section.label }}</span>
                <span class="hidden truncate text-xs opacity-80 sm:block">{{ section.hint }}</span>
            </span>
        </button>
    </nav>
</template>
