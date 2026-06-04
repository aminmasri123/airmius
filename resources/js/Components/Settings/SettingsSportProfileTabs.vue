<script setup>
import { computed } from 'vue'

const props = defineProps({
    activeSportProfileId: { type: [Number, String], default: '' },
    selectedSportProfiles: { type: Array, default: () => [] },
    sportProfileGroupLabel: { type: Function, required: true },
})

const emit = defineEmits(['update:activeSportProfileId'])

const activeSportProfileIdModel = computed({
    get: () => props.activeSportProfileId,
    set: (value) => emit('update:activeSportProfileId', value),
})
</script>

<template>
    <div class="surface-card p-3">
        <div class="flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="profile in selectedSportProfiles"
                :key="profile.sport.id"
                type="button"
                class="min-w-[220px] rounded-xl border p-3 text-left transition"
                :class="Number(activeSportProfileIdModel) === Number(profile.sport.id)
                    ? 'border-air-blue bg-air-blue/15 text-primary'
                    : 'border-border bg-bg text-secondary hover:border-air-blue/60 hover:text-primary'"
                @click="activeSportProfileIdModel = Number(profile.sport.id)"
            >
                <span class="block text-xs font-semibold uppercase tracking-wide">
                    {{ sportProfileGroupLabel(profile.group) }}
                </span>
                <span class="mt-1 flex items-center justify-between gap-3">
                    <span class="truncate text-sm font-semibold">{{ profile.sport.name }}</span>
                    <span
                        class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-semibold"
                        :class="profile.readiness.ready ? 'border-success/30 bg-success/10 text-success' : 'border-warning/30 bg-warning/10 text-warning'"
                    >
                        {{ profile.readiness.score }}%
                    </span>
                </span>
            </button>
        </div>
    </div>
</template>
