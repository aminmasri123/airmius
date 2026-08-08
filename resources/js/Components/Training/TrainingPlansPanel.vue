<script setup>
defineProps({
    selectedSport: { type: Object, default: () => ({}) },
    aiTrainingPlanAvailable: { type: Boolean, default: false },
    filteredPlans: { type: Array, default: () => [] },
    visibleLogs: { type: Array, default: () => [] },
    templatePlans: { type: Array, default: () => [] },
    cadenceLabels: { type: Object, default: () => ({}) },
    phaseLabels: { type: Object, default: () => ({}) },
    levelLabels: { type: Object, default: () => ({}) },
    permissionLabels: { type: Object, default: () => ({}) },
    loadLabels: { type: Object, default: () => ({}) },
    sportAccent: { type: Function, required: true },
    sportIcon: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    itemStatusClass: { type: Function, required: true },
    itemStatusLabel: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    formatDuration: { type: Function, required: true },
})

const emit = defineEmits([
    'open-ai-training-plan-modal',
    'open-modal',
    'open-plan-item',
    'document-plan-item',
    'duplicate-plan-item',
    'duplicate-plan',
    'publish-plan',
])
</script>

<template>
    <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ selectedSport.label }}</p>
                        <h2 class="text-xl font-semibold text-primary">{{ $t('Trainingspläne') }}</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-xl border border-air-blue/50 bg-air-blue/10 px-4 py-2 text-sm font-semibold text-air-blue hover:bg-air-blue/15 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!aiTrainingPlanAvailable" @click="emit('open-ai-training-plan-modal')">
                            KI-Plan
                        </button>
                        <button type="button" class="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted" @click="emit('open-modal', 'plan')">
                            Neuer Plan
                        </button>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <article v-for="plan in filteredPlans" :key="plan.id" class="overflow-hidden rounded-2xl border border-border bg-card">
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-secondary">
                                            {{ cadenceLabels[plan.cadence] || plan.cadence }}
                                        </span>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="plan.status === 'published' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'">
                                            {{ plan.status === 'published' ? 'Freigegeben' : 'Entwurf' }}
                                        </span>
                                        <span v-if="plan.settings?.ai_generation" class="rounded-full border border-air-blue/40 bg-air-blue/10 px-2.5 py-1 text-xs font-semibold text-air-blue">
                                            KI-generiert
                                        </span>
                                        <span v-if="plan.settings?.ai_generation?.profile_estimate_mode" class="rounded-full border border-warning/40 bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">
                                            Schätzmodus
                                        </span>
                                    </div>
                                    <h3 class="mt-3 truncate text-lg font-semibold text-primary">{{ plan.title }}</h3>
                                    <p class="mt-1 hidden line-clamp-2 text-sm text-secondary md:block">{{ plan.description || 'Keine Beschreibung hinterlegt.' }}</p>
                                    <p v-if="plan.settings?.ai_generation?.convincing_explanation" class="mt-2 hidden line-clamp-2 rounded-xl border border-air-blue/25 bg-air-blue/10 px-3 py-2 text-xs text-primary md:block">
                                        Warum so: {{ plan.settings.ai_generation.convincing_explanation }}
                                    </p>
                                    <p v-if="plan.settings?.goal" class="mt-2 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-primary">
                                        Ziel: {{ plan.settings.goal }}
                                    </p>
                                </div>
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white" :class="sportAccent(plan.items?.[0]?.sport_type)">
                                    <i :class="sportIcon(plan.items?.[0]?.sport_type)" class="text-xl"></i>
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="text-base font-semibold text-primary">{{ plan.items?.length || 0 }}</p>
                                    <p class="text-[11px] text-secondary">Einheiten</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="truncate text-base font-semibold text-primary">{{ plan.settings?.weeks || '-' }}</p>
                                    <p class="text-[11px] text-secondary">Wochen</p>
                                </div>
                                <div class="rounded-xl border border-border bg-inputBg/50 p-2">
                                    <p class="text-base font-semibold text-primary">{{ plan.settings?.weekly_sessions || '-' }}</p>
                                    <p class="text-[11px] text-secondary">pro Woche</p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <span v-if="plan.settings?.phase" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ phaseLabels[plan.settings.phase] || plan.settings.phase }}
                                </span>
                                <span v-if="plan.settings?.level" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ levelLabels[plan.settings.level] || plan.settings.level }}
                                </span>
                                <span v-if="plan.settings?.macrocycle" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ plan.settings.macrocycle }}
                                </span>
                                <span v-if="plan.settings?.mesocycle" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ plan.settings.mesocycle }}
                                </span>
                                <span v-if="plan.settings?.competition_date" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    Ziel: {{ formatDate(plan.settings.competition_date) }}
                                </span>
                                <span v-if="plan.team?.name" class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ plan.team.name }}
                                </span>
                                <span class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-secondary">
                                    {{ permissionLabels[plan.share_permission] }}
                                </span>
                            </div>

                            <div class="mt-4 rounded-xl border border-border bg-inputBg/40 p-3">
                                <div class="flex items-center justify-between gap-3 text-xs font-semibold">
                                    <span class="text-secondary">Planfortschritt</span>
                                    <span class="text-primary">{{ plan.progress?.percent || 0 }}%</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-success" :style="{ width: `${plan.progress?.percent || 0}%` }"></div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-secondary">
                                    <span>{{ plan.progress?.completed || 0 }} erledigt</span>
                                    <span>{{ plan.progress?.open || 0 }} offen</span>
                                    <span>{{ plan.progress?.missed || 0 }} Ausfall</span>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div v-for="item in (plan.items || []).slice(0, 2)" :key="item.id" class="rounded-xl border border-border bg-inputBg/40 p-3">
                                    <div class="flex gap-3">
                                        <img v-if="item.image_url" :src="item.image_url" alt="" class="h-14 w-14 rounded-xl object-cover" />
                                        <span v-else class="flex h-14 w-14 items-center justify-center rounded-xl text-white" :class="sportAccent(item.sport_type)">
                                            <i :class="sportIcon(item.sport_type)" class="text-xl"></i>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="truncate text-sm font-semibold text-primary">{{ item.title }}</p>
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="itemStatusClass(item)">{{ itemStatusLabel(item) }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-secondary">{{ item.scheduled_at ? formatDate(item.scheduled_at) : 'offen' }}</p>
                                            <p class="mt-1 text-xs text-secondary">{{ sportLabel(item.sport_type) }} · {{ formatDuration(item.duration_minutes) }}</p>
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <span v-if="item.metrics?.Woche" class="rounded-full bg-air-blue/10 px-2 py-0.5 text-[11px] font-semibold text-air-blue">Woche {{ item.metrics.Woche }}</span>
                                                <span v-if="item.metrics?.Belastung" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">{{ loadLabels[item.metrics.Belastung] || item.metrics.Belastung }}</span>
                                                <span v-if="item.metrics?.Fokus" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">{{ item.metrics.Fokus }}</span>
                                            </div>
                                            <div v-if="item.metrics && Object.keys(item.metrics).length" class="mt-2 flex flex-wrap gap-1">
                                                <span v-for="(value, key) in item.metrics" v-show="!['Woche', 'Belastung', 'Fokus', '_training_type', 'training_type', 'Trainingstyp'].includes(key)" :key="key" class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-secondary">
                                                    {{ key }}: {{ value }}
                                                </span>
                                            </div>
                                            <div v-if="plan.can_write" class="mt-3 flex flex-wrap gap-2">
                                                <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-muted" @click="emit('open-plan-item', { ...item, plan })">
                                                    Details
                                                </button>
                                                <button type="button" class="rounded-lg border border-success/40 px-2.5 py-1.5 text-[11px] font-semibold text-success hover:bg-success/10" @click="emit('document-plan-item', item)">
                                                    Dokumentieren
                                                </button>
                                                <button type="button" class="rounded-lg border border-warning/40 px-2.5 py-1.5 text-[11px] font-semibold text-warning hover:bg-warning/10" @click="emit('open-modal', 'item-missed', plan, item)">
                                                    Ausfall
                                                </button>
                                                <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-muted" @click="emit('open-modal', 'item-edit', plan, item)">
                                                    Bearbeiten
                                                </button>
                                                <button type="button" class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-muted" @click="emit('duplicate-plan-item', plan, item)">
                                                    Kopie
                                                </button>
                                                <button type="button" class="rounded-lg border border-danger/40 px-2.5 py-1.5 text-[11px] font-semibold text-danger hover:bg-danger/10" @click="emit('open-modal', 'item-delete', plan, item)">
                                                    Löschen
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 border-t border-border bg-inputBg/30 p-3">
                            <button v-if="plan.can_write" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="emit('open-modal', 'item', plan)">
                                Einheit
                            </button>
                            <button v-if="plan.can_write" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="emit('open-modal', 'edit', plan)">
                                Bearbeiten
                            </button>
                            <button v-if="plan.can_write && plan.settings?.ai_generation && aiTrainingPlanAvailable" type="button" class="rounded-lg border border-air-blue/40 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-air-blue hover:bg-air-blue/15" @click="emit('open-ai-training-plan-modal', plan)">
                                Mit KI anpassen
                            </button>
                            <button v-if="plan.can_write" type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-muted" @click="emit('duplicate-plan', plan)">
                                Als Vorlage kopieren
                            </button>
                            <button v-if="plan.status !== 'published' && plan.can_write" type="button" class="rounded-lg border border-success/40 px-3 py-2 text-xs font-semibold text-success hover:bg-success/10" @click="emit('publish-plan', plan)">
                                Freigeben
                            </button>
                            <button v-if="plan.can_write" type="button" class="ml-auto rounded-lg border border-danger/40 px-3 py-2 text-xs font-semibold text-danger hover:bg-danger/10" @click="emit('open-modal', 'delete', plan)">
                                Löschen
                            </button>
                        </div>
                    </article>

                    <div v-if="!filteredPlans.length" class="rounded-2xl border border-dashed border-border bg-card p-8 text-center lg:col-span-2">
                        <p class="text-lg font-semibold text-primary">Noch kein Plan für diese Auswahl.</p>
                        <p class="mt-2 text-sm text-secondary">Erstelle den ersten Plan und gib ihn direkt an Sportler oder ein Team frei.</p>
                        <div class="mt-4 flex flex-wrap justify-center gap-2">
                            <button type="button" class="rounded-xl border border-air-blue/50 bg-air-blue/10 px-4 py-2 text-sm font-semibold text-air-blue disabled:opacity-50" :disabled="!aiTrainingPlanAvailable" @click="emit('open-ai-training-plan-modal')">
                                KI-Plan erstellen
                            </button>
                            <button type="button" class="rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('open-modal', 'plan')">
                                Plan manuell erstellen
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">Letzte Einheiten</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="log in visibleLogs.slice(0, 6)" :key="log.id" class="flex items-center gap-3 rounded-xl border border-border bg-inputBg/40 p-2">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl text-white" :class="sportAccent(log.sport_type)">
                                <i :class="sportIcon(log.sport_type)" class="text-xl"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ log.title || 'Training' }}</p>
                                <p class="text-xs text-secondary">{{ formatDate(log.performed_at) }} · {{ formatDuration(log.duration_minutes) }} · {{ log.athlete?.name || 'Ich' }}</p>
                            </div>
                        </div>
                        <p v-if="!visibleLogs.length" class="text-sm text-secondary">Noch keine Einheiten dokumentiert.</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">Sportart-Parameter</h2>
                    <p class="mt-1 text-sm text-secondary">Die Felder im Plan passen sich der gewählten Sportart an.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="metric in (selectedSport.metrics || ['Dauer', 'Intensität', 'Todo'])" :key="metric" class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">
                            {{ metric }}
                        </span>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <h2 class="text-base font-semibold text-primary">Vorlagen</h2>
                    <p class="mt-1 text-sm text-secondary">Kopierte oder vorbereitete Pläne können als Startpunkt genutzt werden.</p>
                    <div class="mt-4 space-y-2">
                        <div v-for="plan in templatePlans.slice(0, 4)" :key="plan.id" class="rounded-xl border border-border bg-inputBg/40 p-3">
                            <p class="text-sm font-semibold text-primary">{{ plan.title }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ plan.items?.length || 0 }} Einheiten · {{ phaseLabels[plan.settings?.phase] || 'Phase offen' }}</p>
                            <button type="button" class="mt-2 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-primary hover:bg-muted" @click="emit('duplicate-plan', plan)">
                                Wiederverwenden
                            </button>
                        </div>
                        <p v-if="!templatePlans.length" class="text-sm text-secondary">Noch keine Vorlagen vorhanden.</p>
                    </div>
                </section>
            </aside>
        </div>
</template>

