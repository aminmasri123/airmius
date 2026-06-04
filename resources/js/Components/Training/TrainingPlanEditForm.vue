<script setup>
defineProps({
    form: { type: Object, required: true },
    people: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    togglePlanUser: { type: Function, required: true },
})

const emit = defineEmits(['submit'])
</script>

<template>
    <form class="space-y-4 p-4" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-semibold text-primary md:col-span-2">Planname
                <input v-model="form.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" required />
            </label>
            <label class="block text-sm font-semibold text-primary">Rhythmus
                <select v-model="form.cadence" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="single">Einmalig</option>
                    <option value="daily">Täglich</option>
                    <option value="weekly">Wöchentlich</option>
                    <option value="monthly">Monatlich</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">Status
                <select v-model="form.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="published">Freigegeben</option>
                    <option value="draft">Entwurf</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">Team
                <select v-model="form.team_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="">Kein komplettes Team</option>
                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">Berechtigung
                <select v-model="form.share_permission" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="read">Nur lesen</option>
                    <option value="write">Mit schreiben / verbessern</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">Start
                <input v-model="form.starts_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">Ende
                <input v-model="form.ends_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary md:col-span-2">Ziel des Plans
                <input v-model="form.goal" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">Trainingsphase
                <select v-model="form.phase" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="base">Grundlage</option>
                    <option value="build">Aufbau</option>
                    <option value="peak">Peak / Wettkampfnähe</option>
                    <option value="recovery">Regeneration</option>
                    <option value="rehab">Reha / Wiedereinstieg</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">Niveau
                <select v-model="form.level" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                    <option value="beginner">Einsteiger</option>
                    <option value="intermediate">Fortgeschritten</option>
                    <option value="advanced">Advanced</option>
                    <option value="elite">Leistung</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-primary">Wochen
                <input v-model="form.weeks" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">Einheiten pro Woche
                <input v-model="form.weekly_sessions" type="number" min="1" max="21" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">Makrozyklus
                <input v-model="form.macrocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">Mesozyklus
                <input v-model="form.mesocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">Deload-Woche
                <input v-model="form.deload_week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary">Wettkampf / Zieltermin
                <input v-model="form.competition_date" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
            <label class="block text-sm font-semibold text-primary md:col-span-2">Beschreibung
                <textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" />
            </label>
        </div>
        <div class="rounded-2xl border border-border p-3">
            <p class="text-sm font-semibold text-primary">Einzelne Sportler</p>
            <div class="mt-3 grid max-h-44 gap-2 overflow-y-auto sm:grid-cols-2">
                <label v-for="person in people" :key="person.id" class="flex items-center gap-2 rounded-xl border border-border px-3 py-2 text-sm text-primary">
                    <input type="checkbox" class="rounded border-border bg-inputBg" :checked="form.user_ids.map(Number).includes(Number(person.id))" @change="togglePlanUser(person.id, form)" />
                    <span class="truncate">{{ person.name }}</span>
                </label>
            </div>
        </div>
        <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
            Änderungen speichern
        </button>
    </form>
</template>

