<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    aiPlanStep: { type: Number, default: 0 },
    aiPlanSteps: { type: Array, default: () => [] },
    canOpenAiPlanStep: { type: Function, required: true },
    aiTrainingPlanError: { type: String, default: '' },
    aiTrainingPlanMessage: { type: String, default: '' },
    aiProfileMissingFields: { type: Array, default: () => [] },
    aiProfileMissingMessage: { type: String, default: '' },
    aiProfileCompletionUrl: { type: String, default: '' },
    aiProfileEstimateAllowed: { type: Boolean, default: false },
    aiTrainingPlanGenerating: { type: Boolean, default: false },
    generateAiTrainingPlanWithProfileEstimates: { type: Function, required: true },
    aiPlanSourcePlan: { type: Object, default: null },
    aiTrainingProviderLabel: { type: String, default: '' },
    aiPlanLimitLabel: { type: String, default: '' },
    aiTrainingPlan: { type: Object, default: () => ({}) },
    aiPlanForm: { type: Object, required: true },
    aiPlanSportChoices: { type: Array, default: () => [] },
    selectAiPlanSportType: { type: Function, required: true },
    aiPlanDurationPresets: { type: Array, default: () => [] },
    aiPlanMaxWeeks: { type: Number, default: 1 },
    setAiPlanDurationPreset: { type: Function, required: true },
    aiPlanTooLarge: { type: Boolean, default: false },
    aiPlanWeeksTooLong: { type: Boolean, default: false },
    aiPlanRequestedItems: { type: Number, default: 0 },
    aiPlanMaxItems: { type: Number, default: 0 },
    aiTrainingPlanPreview: { type: Object, default: null },
    aiSafetyAccepted: { type: Boolean, default: false },
    aiSafetyCanSave: { type: Boolean, default: false },
    aiSafetyGate: { type: Object, default: null },
    qualityRiskClass: { type: Function, required: true },
    qualityStatusClass: { type: Function, required: true },
    generateAiTrainingPlan: { type: Function, required: true },
    continueAiTrainingPlan: { type: Function, required: true },
    aiPlanCannotGenerate: { type: Boolean, default: false },
    saveAiTrainingPlan: { type: Function, required: true },
    aiTrainingPlanSaving: { type: Boolean, default: false },
    sportLabel: { type: Function, required: true },
})

const emit = defineEmits(['update:aiPlanStep', 'update:aiSafetyAccepted'])
const { locale } = useI18n()
const safetyCopy = {
    de: {
        gate: 'Sicherheitsprüfung',
        title: 'KI-Vorschlag bewusst freigeben',
        description: 'Airmius hat den Plan geprüft. Speichere ihn erst, wenn Ziel, Umfang, Warnungen und Einheiten für dich passen.',
        blocked: 'Blockiert',
        ready: 'Speicherbar',
        acceptance: 'Ich habe die Warnungen und Einheiten geprüft und möchte diesen Plan als bearbeitbaren Trainingsplan speichern.',
        back: 'Zurück',
        next: 'Weiter',
        working: 'KI arbeitet...',
        generate: 'Plan generieren',
        saving: 'Speichert...',
        confirm: 'Plan bestätigen & speichern',
        block_missing_quality_check: 'Die technische Qualitätsprüfung fehlt.',
        block_score_too_low: 'Die Qualitätsbewertung ist zu niedrig.',
        block_high_risk_requires_draft: 'Hohes Risiko oder kritische Prüfung: Der Plan darf nicht veröffentlicht werden.',
    },
    en: {
        gate: 'Safety check',
        title: 'Approve the AI proposal consciously',
        description: 'Airmius has checked the plan. Save it only after the goal, volume, warnings and sessions are right for you.',
        blocked: 'Blocked',
        ready: 'Ready to save',
        acceptance: 'I have reviewed the warnings and sessions and want to save this plan as an editable training plan.',
        back: 'Back',
        next: 'Next',
        working: 'AI is working...',
        generate: 'Generate plan',
        saving: 'Saving...',
        confirm: 'Confirm and save plan',
        block_missing_quality_check: 'The technical quality check is missing.',
        block_score_too_low: 'The quality score is too low.',
        block_high_risk_requires_draft: 'High risk or a critical check prevents publishing this plan.',
    },
    fr: {
        gate: 'Contrôle de sécurité',
        title: "Valider consciemment la proposition de l'IA",
        description: "Airmius a vérifié le plan. Enregistre-le seulement après avoir contrôlé l'objectif, le volume, les avertissements et les séances.",
        blocked: 'Bloqué',
        ready: 'Prêt à enregistrer',
        acceptance: "J'ai vérifié les avertissements et les séances et je souhaite enregistrer ce plan comme plan d'entraînement modifiable.",
        back: 'Retour',
        next: 'Suivant',
        working: "L'IA travaille...",
        generate: 'Générer le plan',
        saving: 'Enregistrement...',
        confirm: 'Confirmer et enregistrer',
        block_missing_quality_check: 'Le contrôle technique de qualité est absent.',
        block_score_too_low: 'Le score de qualité est trop faible.',
        block_high_risk_requires_draft: 'Un risque élevé ou un contrôle critique empêche la publication de ce plan.',
    },
    ar: {
        gate: 'فحص السلامة',
        title: 'الموافقة الواعية على اقتراح الذكاء الاصطناعي',
        description: 'راجع Airmius الخطة. احفظها فقط بعد التأكد من الهدف والحجم والتحذيرات والوحدات.',
        blocked: 'محظور',
        ready: 'جاهز للحفظ',
        acceptance: 'راجعت التحذيرات والوحدات وأرغب في حفظ هذه الخطة كخطة تدريب قابلة للتعديل.',
        back: 'رجوع',
        next: 'التالي',
        working: 'الذكاء الاصطناعي يعمل...',
        generate: 'إنشاء الخطة',
        saving: 'جارٍ الحفظ...',
        confirm: 'تأكيد الخطة وحفظها',
        block_missing_quality_check: 'فحص الجودة التقني غير موجود.',
        block_score_too_low: 'درجة الجودة منخفضة جداً.',
        block_high_risk_requires_draft: 'تمنع المخاطر العالية أو نتيجة الفحص الحرجة نشر هذه الخطة.',
    },
}
const sc = (key) => (safetyCopy[String(locale.value || 'de').split('-')[0]] || safetyCopy.de)[key]
const safetyBlock = (block) => sc(`block_${block}`) || block

const aiPlanStepModel = computed({
    get: () => props.aiPlanStep,
    set: (value) => emit('update:aiPlanStep', value),
})

const aiSafetyAcceptedModel = computed({
    get: () => props.aiSafetyAccepted,
    set: (value) => emit('update:aiSafetyAccepted', value),
})
</script>

<template>
    <form class="space-y-4 p-4" @submit.prevent="aiPlanStepModel < 2 ? generateAiTrainingPlan(false) : saveAiTrainingPlan()">
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="(step, index) in aiPlanSteps"
                            :key="step.label"
                            type="button"
                            class="rounded-2xl border p-3 text-left transition"
                            :class="[
                                aiPlanStepModel === index ? 'border-air-blue bg-air-blue text-white shadow-lg shadow-air-blue/20' : 'border-border bg-card text-primary hover:bg-muted',
                                !canOpenAiPlanStep(index) ? 'cursor-not-allowed opacity-50' : '',
                            ]"
                            :disabled="!canOpenAiPlanStep(index)"
                            @click="aiPlanStepModel = index"
                        >
                            <span class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide" :class="aiPlanStepModel === index ? 'text-white/80' : 'text-secondary'">
                                <i :class="step.icon"></i>
                                Schritt {{ index + 1 }}
                            </span>
                            <span class="mt-2 block text-sm font-semibold">{{ step.label }}</span>
                            <span class="hidden text-xs opacity-80 sm:block">{{ step.hint }}</span>
                        </button>
                    </div>

                    <div v-if="aiTrainingPlanError || aiTrainingPlanMessage" class="rounded-xl border px-4 py-3 text-sm font-semibold" :class="aiTrainingPlanError ? 'border-danger/40 bg-danger/10 text-danger' : 'border-success/40 bg-success/10 text-success'">
                        {{ aiTrainingPlanError || aiTrainingPlanMessage }}
                    </div>

                    <div v-if="aiProfileMissingFields.length" class="rounded-2xl border border-warning/40 bg-warning/10 p-4 text-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-warning">Sportprofil ergänzen</p>
                                <h3 class="mt-1 text-base font-semibold text-primary">Möchtest du die Daten vor dem Generieren nachtragen?</h3>
                                <p class="mt-2 max-w-3xl text-sm text-primary">{{ aiProfileMissingMessage }}</p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <div v-for="field in aiProfileMissingFields" :key="field.key" class="rounded-xl border border-warning/30 bg-bg/70 px-3 py-2">
                                        <p class="text-xs font-semibold text-warning">{{ field.label }}</p>
                                        <p v-if="field.help" class="mt-1 text-xs text-secondary">{{ field.help }}</p>
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-secondary">
                                    Wenn du die Werte nicht kennst, kann Airmius trotzdem starten, aber nur vorsichtig und allgemeiner. Präziser wird es erst mit nachgetragenen Leistungsdaten.
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col gap-2 sm:flex-row lg:flex-col">
                                <Link
                                    :href="aiProfileCompletionUrl"
                                    class="inline-flex items-center justify-center rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                                >
                                    Daten jetzt nachtragen
                                </Link>
                                <button
                                    v-if="aiProfileEstimateAllowed"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-xl border border-warning/50 px-4 py-2 text-sm font-semibold text-warning hover:bg-warning/10 disabled:opacity-60"
                                    :disabled="aiTrainingPlanGenerating"
                                    @click="generateAiTrainingPlanWithProfileEstimates"
                                >
                                    Ich kenne sie nicht - vorsichtig generieren
                                </button>
                            </div>
                        </div>
                    </div>

                    <section v-if="aiPlanStepModel === 0" class="rounded-2xl border border-border bg-card p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">KI-Planung</p>
                                <h3 class="mt-1 text-lg font-semibold text-primary">{{ aiPlanSourcePlan ? 'Plan mit KI anpassen' : 'Ziel festlegen' }}</h3>
                            </div>
                            <span class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-secondary">{{ aiTrainingProviderLabel }}</span>
                        </div>

                        <div class="mt-3 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-sm text-primary">
                            <span class="font-semibold">{{ aiPlanLimitLabel }}</span>
                            <span v-if="aiTrainingPlan.access_reason" class="mt-1 block text-danger">{{ aiTrainingPlan.access_reason }}</span>
                        </div>

                        <p v-if="aiPlanSourcePlan" class="mt-3 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-sm text-primary">
                            Ausgangsplan: {{ aiPlanSourcePlan.title }}. Die KI erstellt eine neue bestätigbare Version, damit dein alter Plan nachvollziehbar bleibt.
                        </p>

                        <div class="mt-4 grid gap-4">
                            <label class="block text-sm font-semibold text-primary">Planname
                                <input v-model="aiPlanForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. 5-km Comeback, Hyrox Aufbau, Oberkörper Kraft" />
                            </label>

                            <label class="block text-sm font-semibold text-primary">Trainingsziel
                                <textarea v-model="aiPlanForm.goal" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="Was soll der Plan erreichen? Beispiel: 6 Wochen, 5 km schneller laufen, 3 Einheiten pro Woche, Knie schonen." required />
                            </label>

                            <div>
                                <p class="text-sm font-semibold text-primary">Sportart</p>
                                <p class="mt-1 text-xs text-secondary">Die KI kombiniert die passenden Schwerpunkte wie Grundlage, Tempo, Technik, Kraft und Regeneration automatisch.</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <button
                                        v-for="sport in aiPlanSportChoices"
                                        :key="sport.key"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-left text-sm font-semibold transition"
                                        :class="aiPlanForm.sport_type === sport.key ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-inputBg text-primary hover:bg-muted'"
                                        @click="selectAiPlanSportType(sport.key)"
                                    >
                                        <span :class="['mb-2 block h-1.5 w-8 rounded-full', sport.accent]"></span>
                                        <i :class="sport.icon" class="mr-2"></i>{{ sport.label }}
                                    </button>
                                </div>
                                <div class="mt-3 rounded-xl border border-air-blue/30 bg-air-blue/10 px-3 py-2 text-xs font-semibold text-primary">
                                    Ausgewogene Planung aktiv: Die KI ordnet jede Einheit automatisch einem passenden Typ zu, z. B. Long Run, Intervalle, Technik oder Regeneration.
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="aiPlanStepModel === 1" class="rounded-2xl border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Rahmen</p>
                        <h3 class="mt-1 text-lg font-semibold text-primary">Damit der Plan wirklich passt</h3>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="block text-sm font-semibold text-primary">Niveau
                                <select v-model="aiPlanForm.level" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="beginner">Einsteiger</option>
                                    <option value="intermediate">Fortgeschritten</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="elite">Leistung</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-primary">Phase
                                <select v-model="aiPlanForm.phase" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                                    <option value="base">Grundlage</option>
                                    <option value="build">Aufbau</option>
                                    <option value="peak">Peak</option>
                                    <option value="recovery">Regeneration</option>
                                    <option value="rehab">Reha / Wiedereinstieg</option>
                                </select>
                            </label>
                            <div class="md:col-span-2">
                                <p class="text-sm font-semibold text-primary">Planlänge</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <button
                                        v-for="preset in aiPlanDurationPresets"
                                        :key="preset.weeks"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-left transition"
                                        :class="[
                                            Number(aiPlanForm.weeks) === Math.min(preset.weeks, aiPlanMaxWeeks) ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-inputBg text-primary hover:bg-muted',
                                            preset.weeks > aiPlanMaxWeeks ? 'opacity-50' : '',
                                        ]"
                                        @click="setAiPlanDurationPreset(preset.weeks)"
                                    >
                                        <span class="block text-sm font-semibold">{{ preset.label }}</span>
                                        <span class="mt-1 block text-xs text-secondary">{{ preset.hint }}</span>
                                        <span v-if="preset.weeks > aiPlanMaxWeeks" class="mt-1 block text-[11px] text-danger">ab nächster Stufe</span>
                                    </button>
                                </div>
                            </div>
                            <label class="block text-sm font-semibold text-primary">Wochen
                                <input v-model="aiPlanForm.weeks" type="number" min="1" :max="aiPlanMaxWeeks" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Einheiten pro Woche
                                <input v-model="aiPlanForm.sessions_per_week" type="number" min="1" max="6" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <div class="md:col-span-2 rounded-xl border px-3 py-2 text-sm" :class="aiPlanTooLarge || aiPlanWeeksTooLong ? 'border-danger/40 bg-danger/10 text-danger' : 'border-border bg-inputBg/40 text-secondary'">
                                {{ aiPlanRequestedItems }} geplante Einheiten · maximal {{ aiPlanMaxItems }} pro KI-Plan · maximal {{ aiPlanMaxWeeks }} Wochen in deiner Stufe.
                            </div>
                            <label class="block text-sm font-semibold text-primary">Dauer je Einheit
                                <input v-model="aiPlanForm.duration_minutes" type="number" min="10" max="240" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary">Startdatum
                                <input v-model="aiPlanForm.starts_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                            </label>
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Equipment / Ort
                                <input v-model="aiPlanForm.equipment" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Kurzhanteln, Laufbahn, Gym, kein Gerät" />
                            </label>
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Einschränkungen
                                <textarea v-model="aiPlanForm.constraints" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="Verletzungen, Zeitfenster, Pausentage, Dinge die vermieden werden sollen" />
                            </label>
                            <label class="block text-sm font-semibold text-primary md:col-span-2">Vorlieben
                                <textarea v-model="aiPlanForm.preferences" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="Lieblingsübungen, bevorzugte Tage, Fokus, Stil" />
                            </label>
                            <label v-if="aiPlanSourcePlan || aiTrainingPlanPreview" class="block text-sm font-semibold text-primary md:col-span-2">Was soll die KI ändern?
                                <textarea v-model="aiPlanForm.revision_instruction" rows="3" class="mt-2 w-full rounded-xl border border-air-blue/40 bg-air-blue/10 px-3 py-3 text-primary" placeholder="z. B. weniger Umfang, mehr Kraft, Dienstag frei lassen, Intervalle kürzer machen" />
                            </label>
                        </div>
                    </section>

                    <section v-if="aiPlanStepModel === 2" class="space-y-4">
                        <div v-if="aiTrainingPlanPreview" class="rounded-2xl border border-air-blue/30 bg-air-blue/10 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Vorschlag prüfen</p>
                                    <h3 class="mt-1 text-xl font-semibold text-primary">{{ aiTrainingPlanPreview.title }}</h3>
                                    <p class="mt-2 max-w-3xl text-sm text-secondary">{{ aiTrainingPlanPreview.summary }}</p>
                                </div>
                                <span class="rounded-full border border-air-blue/40 px-3 py-1 text-xs font-semibold text-air-blue">
                                    {{ aiTrainingPlanPreview.items?.length || 0 }} Einheiten
                                </span>
                                <span v-if="aiTrainingPlanPreview.profile_estimate_mode" class="rounded-full border border-warning/40 bg-warning/10 px-3 py-1 text-xs font-semibold text-warning">
                                    Schätzmodus
                                </span>
                            </div>
                            <div class="mt-4 rounded-xl border border-air-blue/25 bg-bg/50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Warum genau so?</p>
                                <p class="mt-1 text-sm text-primary">{{ aiTrainingPlanPreview.convincing_explanation }}</p>
                            </div>
                            <div v-if="aiTrainingPlanPreview.profile_estimate_mode" class="mt-3 rounded-xl border border-warning/30 bg-warning/10 p-3 text-sm text-primary">
                                <p class="font-semibold text-warning">Mit Schätzungen erstellt</p>
                                <p class="mt-1 text-xs text-secondary">Einige Leistungsdaten fehlen oder wurden als unbekannt markiert. Der Vorschlag ist deshalb bewusst vorsichtig und sollte vor dem Speichern genauer geprüft werden.</p>
                            </div>
                        </div>

                        <div v-if="aiTrainingPlanPreview?.quality_check" class="rounded-2xl border border-border bg-card p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Airmius-Regelwerk</p>
                                    <h4 class="mt-1 text-lg font-semibold text-primary">Technische Prüfung</h4>
                                    <p class="mt-1 text-sm text-secondary">
                                        Airmius prüft Umfang, Steigerung, Belastung, Regeneration und sportartspezifische Risiken.
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="qualityRiskClass(aiTrainingPlanPreview.quality_check.risk)">
                                        Risiko: {{ aiTrainingPlanPreview.quality_check.risk }}
                                    </span>
                                    <span class="rounded-full border border-air-blue/40 bg-air-blue/10 px-3 py-1 text-xs font-semibold text-air-blue">
                                        {{ aiTrainingPlanPreview.quality_check.score }} / 100
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-2 md:grid-cols-2">
                                <div
                                    v-for="check in aiTrainingPlanPreview.quality_check.checks || []"
                                    :key="check.key"
                                    class="rounded-xl border px-3 py-2"
                                    :class="qualityStatusClass(check.status)"
                                >
                                    <p class="text-sm font-semibold">{{ check.label }}</p>
                                    <p class="mt-1 text-xs opacity-90">{{ check.message }}</p>
                                </div>
                            </div>

                            <div v-if="(aiTrainingPlanPreview.quality_check.warnings || []).length || (aiTrainingPlanPreview.quality_check.suggestions || []).length" class="mt-4 grid gap-3 lg:grid-cols-2">
                                <section v-if="(aiTrainingPlanPreview.quality_check.warnings || []).length" class="rounded-xl border border-warning/30 bg-warning/10 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-warning">Warnungen</p>
                                    <ul class="mt-2 space-y-1 text-xs text-primary">
                                        <li v-for="warning in aiTrainingPlanPreview.quality_check.warnings" :key="warning">- {{ warning }}</li>
                                    </ul>
                                </section>
                                <section v-if="(aiTrainingPlanPreview.quality_check.suggestions || []).length" class="rounded-xl border border-success/30 bg-success/10 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-success">Verbesserungen</p>
                                    <ul class="mt-2 space-y-1 text-xs text-primary">
                                        <li v-for="suggestion in aiTrainingPlanPreview.quality_check.suggestions" :key="suggestion">- {{ suggestion }}</li>
                                    </ul>
                                </section>
                            </div>
                        </div>

                        <div v-if="aiTrainingPlanPreview" class="rounded-2xl border p-4" :class="aiSafetyGate?.status === 'blocked' ? 'border-danger/40 bg-danger/10' : 'border-success/30 bg-success/10'">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide" :class="aiSafetyGate?.status === 'blocked' ? 'text-danger' : 'text-success'">{{ sc('gate') }}</p>
                                    <h4 class="mt-1 text-base font-semibold text-primary">{{ sc('title') }}</h4>
                                    <p class="mt-1 text-sm text-secondary">
                                        {{ sc('description') }}
                                    </p>
                                </div>
                                <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="aiSafetyGate?.status === 'blocked' ? 'border-danger/40 text-danger' : 'border-success/40 text-success'">
                                    {{ aiSafetyGate?.status === 'blocked' ? sc('blocked') : sc('ready') }}
                                </span>
                            </div>
                            <ul v-if="(aiSafetyGate?.blocks || []).length" class="mt-3 space-y-1 text-xs font-semibold text-danger">
                                <li v-for="block in aiSafetyGate.blocks" :key="block">- {{ safetyBlock(block) }}</li>
                            </ul>
                            <label class="mt-4 flex items-start gap-3 rounded-xl border border-border bg-bg/60 p-3 text-sm font-semibold text-primary">
                                <input v-model="aiSafetyAcceptedModel" type="checkbox" class="mt-1 rounded border-border text-air-blue focus:ring-air-blue" :disabled="aiSafetyGate?.status === 'blocked'" />
                                <span>{{ sc('acceptance') }}</span>
                            </label>
                        </div>

                        <div v-if="aiTrainingPlanPreview" class="grid gap-4 lg:grid-cols-2">
                            <section class="rounded-2xl border border-border bg-card p-4">
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-secondary">Planlogik</h4>
                                <ul class="mt-3 space-y-2 text-sm text-primary">
                                    <li v-for="reason in aiTrainingPlanPreview.progression_logic || []" :key="reason" class="rounded-xl border border-border bg-inputBg/40 px-3 py-2">{{ reason }}</li>
                                </ul>
                            </section>
                            <section class="rounded-2xl border border-border bg-card p-4">
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-secondary">Analyse-Tipps</h4>
                                <ul class="mt-3 space-y-2 text-sm text-primary">
                                    <li v-for="tip in aiTrainingPlanPreview.analysis_tips || []" :key="tip" class="rounded-xl border border-border bg-inputBg/40 px-3 py-2">{{ tip }}</li>
                                    <li v-if="!(aiTrainingPlanPreview.analysis_tips || []).length" class="text-secondary">Keine zusätzlichen Tipps.</li>
                                </ul>
                            </section>
                        </div>

                        <div v-if="aiTrainingPlanPreview" class="rounded-2xl border border-border bg-card p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Trainingseinheiten</p>
                                    <h4 class="text-lg font-semibold text-primary">Nach dem Speichern normal bearbeitbar</h4>
                                </div>
                                <button type="button" class="rounded-xl border border-air-blue/40 px-3 py-2 text-sm font-semibold text-air-blue hover:bg-air-blue/10" :disabled="aiTrainingPlanGenerating" @click="generateAiTrainingPlan(true)">
                                    KI überarbeiten
                                </button>
                            </div>

                            <label class="mt-4 block text-sm font-semibold text-primary">Änderungswunsch an die KI
                                <textarea v-model="aiPlanForm.revision_instruction" rows="2" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" placeholder="z. B. Einheit 3 leichter machen, mehr Gym-Sätze, Laufumfang reduzieren" />
                            </label>

                            <div class="mt-4 space-y-2">
                                <article v-for="(item, index) in aiTrainingPlanPreview.items || []" :key="`${item.title}-${index}`" class="rounded-xl border border-border bg-inputBg/40 p-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Woche {{ item.week || '-' }} · {{ item.day || 'Termin offen' }}</p>
                                            <h5 class="mt-1 text-sm font-semibold text-primary">{{ item.title }}</h5>
                                            <p class="mt-1 text-xs text-secondary">{{ item.description }}</p>
                                        </div>
                                        <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-secondary">{{ sportLabel(item.sport_type) }}</span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] text-secondary">
                                        <span v-if="item.duration_minutes" class="rounded-full bg-bg px-2 py-1">{{ item.duration_minutes }} min</span>
                                        <span v-if="item.distance_km" class="rounded-full bg-bg px-2 py-1">{{ item.distance_km }} km</span>
                                        <span v-if="item.intensity" class="rounded-full bg-bg px-2 py-1">{{ item.intensity }}</span>
                                        <span v-for="(value, key) in item.metrics || {}" :key="key" class="rounded-full bg-bg px-2 py-1">{{ key }}: {{ value }}</span>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </section>

                    <div class="sticky bottom-0 -mx-4 -mb-4 flex items-center justify-between gap-3 border-t border-border bg-bg/95 p-4 backdrop-blur">
                        <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary disabled:opacity-40" :disabled="aiPlanStepModel === 0 || aiTrainingPlanGenerating || aiTrainingPlanSaving" @click="aiPlanStepModel = Math.max(0, aiPlanStepModel - 1)">
                            {{ sc('back') }}
                        </button>
                        <button v-if="aiPlanStepModel < 2" type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="aiTrainingPlanGenerating || !aiPlanForm.goal?.trim() || (aiPlanStepModel === 1 && aiPlanCannotGenerate)" @click="continueAiTrainingPlan">
                            {{ aiPlanStepModel === 0 ? sc('next') : aiTrainingPlanGenerating ? sc('working') : sc('generate') }}
                        </button>
                        <button v-else type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="aiTrainingPlanSaving || !aiTrainingPlanPreview || !aiSafetyCanSave" @click="saveAiTrainingPlan">
                            {{ aiTrainingPlanSaving ? sc('saving') : sc('confirm') }}
                        </button>
                    </div>
                </form>
</template>






