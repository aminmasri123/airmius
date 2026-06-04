<script setup>
import { computed } from 'vue'

const props = defineProps({
    activeSport: { type: String, required: true },
    sports: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:activeSport'])

const selectedSport = computed({
    get: () => props.activeSport,
    set: (value) => emit('update:activeSport', value),
})
</script>

<template>
    <section class="rounded-2xl border border-border bg-card p-3">
        <div class="flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="sport in sports"
                :key="sport.key"
                type="button"
                class="flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                :class="selectedSport === sport.key ? 'border-air-blue bg-air-blue/10 text-primary' : 'border-border text-secondary hover:bg-muted'"
                @click="selectedSport = sport.key"
            >
                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" :class="sport.accent">
                    <i :class="sport.icon"></i>
                </span>
                {{ sport.label }}
            </button>
        </div>
    </section>
</template>
