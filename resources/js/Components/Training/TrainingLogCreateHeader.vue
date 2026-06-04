<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    draftLog: { type: Object, default: null },
    selectedType: { type: Object, required: true },
    detailSummary: { type: String, default: '' },
    documentationScore: { type: Number, default: 0 },
    documentationScoreClass: { type: String, default: '' },
    autosaveStatus: { type: String, default: '' },
    autosaveSavedAt: { type: [String, Date], default: null },
    autosaveError: { type: String, default: '' },
    formatSaveTime: { type: Function, required: true },
})
</script>

<template>
    <section class="hidden overflow-hidden rounded-2xl border border-border bg-card sm:block">
        <div class="border-b border-border bg-inputBg/30 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Training</p>
                    <h1 class="mt-1 text-2xl font-semibold text-primary sm:text-3xl">Dokumentieren</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">
                        Schnell erfassen, Sätze abhaken, bei Bedarf später Details ergänzen.
                    </p>
                </div>
                <Link :href="route('auth.training.index')" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted">
                    Zurück
                </Link>
            </div>
        </div>
        <div class="grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Vorlage</p>
                <p class="mt-1 truncate text-sm font-semibold text-primary">{{ selectedType.label }}</p>
            </div>
            <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Status</p>
                <p class="mt-1 truncate text-sm font-semibold text-primary">{{ detailSummary }}</p>
            </div>
            <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">Qualität</p>
                <div class="mt-2 flex items-center gap-2">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-air-blue" :style="{ width: `${documentationScore}%` }"></div>
                    </div>
                    <span class="text-sm font-semibold" :class="documentationScoreClass">{{ documentationScore }}%</span>
                </div>
            </div>
            <div class="rounded-xl border border-border bg-inputBg/40 p-3">
                <p
                    v-if="draftLog"
                    class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="autosaveStatus === 'error' ? 'border-danger/40 bg-danger/10 text-danger' : autosaveStatus === 'saving' || autosaveStatus === 'dirty' ? 'border-air-blue/40 bg-air-blue/10 text-air-blue' : 'border-success/40 bg-success/10 text-success'"
                >
                    <span v-if="autosaveStatus === 'saving'">Entwurf wird gespeichert...</span>
                    <span v-else-if="autosaveStatus === 'dirty'">Änderungen werden gleich gespeichert</span>
                    <span v-else-if="autosaveStatus === 'error'">{{ autosaveError }}</span>
                    <span v-else>Entwurf gespeichert{{ autosaveSavedAt ? ` um ${formatSaveTime(autosaveSavedAt)}` : '' }}</span>
                </p>
                <p v-else class="text-sm font-semibold text-secondary">Noch kein Entwurf</p>
            </div>
        </div>
    </section>
</template>



