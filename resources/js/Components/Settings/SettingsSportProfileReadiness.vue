<script setup>
defineProps({
    profile: { type: Object, required: true },
    savingSportProfileId: { type: [Number, String], default: null },
    sportProfileText: { type: Function, required: true },
    sportProfileGroupLabel: { type: Function, required: true },
    sportMetricLabel: { type: Function, required: true },
    openSportProfileRemoveModal: { type: Function, required: true },
})
</script>

<template>
    <div class="border-b border-border p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ sportProfileGroupLabel(profile.group) }}</p>
                <h3 class="mt-1 text-lg font-semibold text-primary">{{ profile.sport.name }}</h3>
                <p class="mt-1 text-sm text-secondary">
                    {{ profile.readiness.ready ? sportProfileText('ready', 'Bereit für KI-Trainingspläne.') : sportProfileText('not_ready', 'Noch nicht vollständig für zuverlässige KI-Pläne.') }}
                </p>
            </div>
            <span
                class="inline-flex items-center justify-center rounded-full border px-3 py-1 text-xs font-semibold"
                :class="profile.readiness.ready ? 'border-success/30 bg-success/10 text-success' : 'border-warning/30 bg-warning/10 text-warning'"
            >
                {{ profile.readiness.score }}%
            </span>
        </div>

        <button
            type="button"
            class="mt-4 rounded-xl border border-error/30 px-3 py-2 text-xs font-semibold text-error hover:bg-error/10"
            :disabled="savingSportProfileId === profile.sport.id"
            @click="openSportProfileRemoveModal(profile)"
        >
            {{ sportProfileText('remove_sport', 'Sportart entfernen') }}
        </button>

        <div v-if="profile.readiness.missing?.length" class="mt-4 rounded-xl border border-warning/30 bg-warning/10 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-warning">{{ sportProfileText('missing_title', 'Fehlt noch') }}</p>
            <div class="mt-2 flex flex-wrap gap-2 text-xs font-semibold text-warning">
                <span v-for="field in profile.readiness.missing" :key="field.key" class="rounded-full bg-bg px-2.5 py-1">
                    {{ sportMetricLabel(field) }}
                </span>
            </div>
        </div>
        <div v-if="profile.readiness.unknown?.length" class="mt-4 rounded-xl border border-air-blue/30 bg-air-blue/10 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ sportProfileText('unknown_title', 'Wird geschätzt') }}</p>
            <div class="mt-2 flex flex-wrap gap-2 text-xs font-semibold text-air-blue">
                <span v-for="field in profile.readiness.unknown" :key="field.key" class="rounded-full bg-bg px-2.5 py-1">
                    {{ sportMetricLabel(field) }}
                </span>
            </div>
        </div>
    </div>
</template>

