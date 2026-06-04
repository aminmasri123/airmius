<script setup>
defineProps({
    form: { type: Object, required: true },
    athleteOptions: { type: Array, default: () => [] },
    plannedLogItems: { type: Array, default: () => [] },
    sportChoices: { type: Array, default: () => [] },
    formatDate: { type: Function, required: true },
})

const emit = defineEmits([
    'add-entry',
    'apply-selected-plan-item',
    'remove-entry',
    'set-log-status',
    'submit',
])
</script>

<template>
    <form class="space-y-5 p-4" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <label v-if="athleteOptions.length > 1" class="block text-sm font-semibold text-primary">Sportler
                <select v-model="form.user_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option v-for="athlete in athleteOptions" :key="athlete.id || 'self'" :value="athlete.id">{{ athlete.name }}</option>
                </select>
            </label>

            <label class="block text-sm font-semibold text-primary">Geplante Einheit
                <select
                    v-model="form.training_plan_item_id"
                    class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary"
                    @change="emit('apply-selected-plan-item')"
                >
                    <option value="">Spontanes Training</option>
                    <option v-for="item in plannedLogItems" :key="item.id" :value="item.id">
                        {{ item.plan.title }} · {{ item.title }}{{ item.scheduled_at ? ` · ${formatDate(item.scheduled_at)}` : '' }}
                    </option>
                </select>
            </label>

            <label class="block text-sm font-semibold text-primary md:col-span-2">Titel
                <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required>
            </label>

            <label class="block text-sm font-semibold text-primary">Sportart
                <select v-model="form.sport_type" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option v-for="sport in sportChoices" :key="sport.key" :value="sport.key">{{ sport.label }}</option>
                </select>
            </label>

            <label class="block text-sm font-semibold text-primary">Status
                <select
                    v-model="form.status"
                    class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary"
                    @change="emit('set-log-status')"
                >
                    <option value="completed">Abgeschlossen</option>
                    <option value="in_progress">Läuft gerade</option>
                    <option value="planned">Geplant</option>
                </select>
            </label>

            <label class="block text-sm font-semibold text-primary">Zeitpunkt
                <input v-model="form.performed_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
            </label>

            <label class="block text-sm font-semibold text-primary">Intensität
                <select v-model="form.intensity" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="">Keine Angabe</option>
                    <option value="locker">Locker</option>
                    <option value="mittel">Mittel</option>
                    <option value="hart">Hart</option>
                    <option value="recovery">Regeneration</option>
                </select>
            </label>

            <label class="block text-sm font-semibold text-primary">Dauer in Minuten
                <input v-model="form.duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
            </label>

            <label class="block text-sm font-semibold text-primary">Distanz in km
                <input v-model="form.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
            </label>

            <label class="block text-sm font-semibold text-primary">Kalorien
                <input v-model="form.calories" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
            </label>

            <label class="block text-sm font-semibold text-primary md:col-span-2">Notizen
                <textarea v-model="form.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Gefühl, Technik, Schmerzen, Besonderheiten"></textarea>
            </label>

            <label v-if="form.user_id" class="block text-sm font-semibold text-primary md:col-span-2">Trainer-Hinweis
                <textarea v-model="form.trainer_feedback" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="Hinweise, Korrekturen oder Fokus für die nächste Einheit"></textarea>
            </label>
        </div>

        <div class="rounded-2xl border border-border bg-inputBg/40 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-secondary">Übungen / Werte</h3>
                <button
                    type="button"
                    class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary hover:bg-muted"
                    @click="emit('add-entry')"
                >
                    Zeile hinzufügen
                </button>
            </div>

            <div class="mt-4 space-y-3">
                <div v-for="(entry, index) in form.entries" :key="index" class="grid gap-3 rounded-xl border border-border p-3 lg:grid-cols-6">
                    <label class="block text-sm font-semibold text-primary lg:col-span-2">Übung / Abschnitt
                        <input v-model="entry.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" placeholder="z. B. Kniebeugen, 5-km-Lauf, Technikdrill">
                    </label>
                    <label class="block text-sm font-semibold text-primary">Sätze
                        <input v-model="entry.sets" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-semibold text-primary">Wdh.
                        <input v-model="entry.reps" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-semibold text-primary">kg
                        <input v-model="entry.weight_kg" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-semibold text-primary">Zeit min
                        <input v-model="entry.duration_minutes" type="number" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-semibold text-primary">Distanz km
                        <input v-model="entry.distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    </label>
                    <label class="block text-sm font-semibold text-primary lg:col-span-4">Kommentar
                        <input v-model="entry.notes" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    </label>
                    <button
                        type="button"
                        class="self-end rounded-xl border border-border px-3 py-2 text-sm font-semibold text-danger hover:bg-danger/10"
                        @click="emit('remove-entry', index)"
                    >
                        Entfernen
                    </button>
                </div>
            </div>
        </div>

        <button
            type="submit"
            class="w-full rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
            :disabled="form.processing"
        >
            Training speichern
        </button>
    </form>
</template>


