<script setup>
defineProps({
    activeDraftLog: { type: Object, required: true },
    formatDate: { type: Function, required: true },
    formatTime: { type: Function, required: true },
    sportAccent: { type: Function, required: true },
    sportIcon: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
})

const emit = defineEmits(['open-draft-delete', 'open-log-page'])
</script>

<template>
    <section class="rounded-2xl border border-air-blue/40 bg-air-blue/10 p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 gap-3">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white" :class="sportAccent(activeDraftLog.sport_type)">
                    <i :class="sportIcon(activeDraftLog.sport_type)" class="text-2xl"></i>
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Training fortsetzen</p>
                        <span class="rounded-full border border-success/40 bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success">
                            Entwurf automatisch gespeichert
                        </span>
                    </div>
                    <h2 class="mt-1 truncate text-lg font-semibold text-primary">{{ activeDraftLog.title || 'Training-Entwurf' }}</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ sportLabel(activeDraftLog.sport_type) }} &middot; zuletzt gespeichert {{ formatDate(activeDraftLog.updated_at) }} {{ formatTime(activeDraftLog.updated_at) }} &middot; {{ activeDraftLog.entries?.length || 0 }} Einträge
                    </p>
                </div>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('open-log-page')">
                    Weiter trainieren
                </button>
                <button type="button" class="rounded-xl border border-danger/40 px-4 py-2 text-sm font-semibold text-danger hover:bg-danger/10" @click="emit('open-draft-delete')">
                    Entwurf verwerfen
                </button>
            </div>
        </div>
    </section>
</template>

