<script setup>
import { computed } from 'vue'

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({}),
    },
})

defineEmits(['create'])

const summaryCards = computed(() => [
    { key: 'sports_count', label: 'Sportarten', value: props.summary.sports_count || 0 },
    { key: 'active_count', label: 'Aktiv', value: props.summary.active_count || 0 },
    { key: 'teams_count', label: 'Teams', value: props.summary.teams_count || 0 },
    { key: 'clubs_count', label: 'Vereine', value: props.summary.clubs_count || 0 },
])
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-semibold uppercase tracking-wide text-air-blue">
                    System Admin
                </p>

                <h1 class="mt-1 text-2xl font-bold text-primary">
                    Sportarten verwalten
                </h1>

                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-secondary">
                    Pflege zentrale Sportarten, Aktivstatus und Sortierung. Die Nutzungszahlen zeigen dir,
                    wie viele Teams, Vereine, Profile und Beiträge je Sportart existieren.
                </p>
            </div>

            <button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-buttonPrimary text-buttonTextPrimary shadow sm:hidden"
                aria-label="Sportart erstellen"
                @click="$emit('create')"
            >
                <i class="las la-plus text-2xl"></i>
            </button>

            <button
                type="button"
                class="hidden rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover sm:inline-flex"
                @click="$emit('create')"
            >
                + Sportart erstellen
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
            <div
                v-for="card in summaryCards"
                :key="card.key"
                class="rounded-lg border border-border bg-card p-4"
            >
                <p class="text-xs font-semibold uppercase text-secondary">
                    {{ card.label }}
                </p>
                <p class="mt-2 text-2xl font-bold text-primary">
                    {{ card.value }}
                </p>
            </div>
        </div>
    </div>
</template>

