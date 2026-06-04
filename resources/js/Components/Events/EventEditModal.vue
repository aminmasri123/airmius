<script setup>
defineProps({
    show: { type: Boolean, default: false },
    editForm: { type: Object, required: true },
    eventTypes: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    filteredTeams: { type: Array, default: () => [] },
    typeLabels: { type: Object, required: true },
    visibilityLabels: { type: Object, required: true },
    recurrenceOptions: { type: Array, default: () => [] },
    weekdayOptions: { type: Array, default: () => [] },
    toggleWeekday: { type: Function, required: true },
    updateEvent: { type: Function, required: true },
})

defineEmits(['close'])
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4" @click.self="$emit('close')">
            <form class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-border bg-card p-5 shadow-xl" @submit.prevent="updateEvent">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-primary">Event bearbeiten</h2>
                        <p class="mt-1 text-sm text-secondary">Änderungen gelten für dieses Event.</p>
                    </div>
                    <button type="button" class="rounded-lg border border-border px-3 py-1 text-secondary hover:text-primary" @click="$emit('close')">
                        Schließen
                    </button>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-primary" for="edit-title">Titel</label>
                        <input id="edit-title" v-model="editForm.title" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                        <p v-if="editForm.errors.title" class="mt-1 text-sm text-error">{{ editForm.errors.title }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary" for="edit-type">Typ</label>
                        <select id="edit-type" v-model="editForm.type" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option v-for="type in eventTypes" :key="type" :value="type">{{ typeLabels[type] || type }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary" for="edit-visibility">Sichtbarkeit</label>
                        <select id="edit-visibility" v-model="editForm.visibility" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option v-for="visibility in visibilities" :key="visibility" :value="visibility">{{ visibilityLabels[visibility] || visibility }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary" for="edit-club">Verein</label>
                        <select id="edit-club" v-model="editForm.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="">Kein Verein</option>
                            <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary" for="edit-team">Team</label>
                        <select id="edit-team" v-model="editForm.team_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option value="">Kein Team</option>
                            <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                        <p v-if="editForm.errors.team_id" class="mt-1 text-sm text-error">{{ editForm.errors.team_id }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary" for="edit-start">Start</label>
                        <input id="edit-start" v-model="editForm.start_time" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                        <p v-if="editForm.errors.start_time" class="mt-1 text-sm text-error">{{ editForm.errors.start_time }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary" for="edit-end">Ende</label>
                        <input id="edit-end" v-model="editForm.end_time" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                        <p v-if="editForm.errors.end_time" class="mt-1 text-sm text-error">{{ editForm.errors.end_time }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary" for="edit-reminder">Erinnerung</label>
                        <input id="edit-reminder" v-model="editForm.reminder_at" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                        <p v-if="editForm.errors.reminder_at" class="mt-1 text-sm text-error">{{ editForm.errors.reminder_at }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary" for="edit-recurring">Wiederholung</label>
                        <select id="edit-recurring" v-model="editForm.recurring" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary">
                            <option v-for="option in recurrenceOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary" for="edit-recurrence-end">Wiederholung bis</label>
                        <input id="edit-recurrence-end" v-model="editForm.recurrence_ends_at" type="date" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                        <p v-if="editForm.errors.recurrence_ends_at" class="mt-1 text-sm text-error">{{ editForm.errors.recurrence_ends_at }}</p>
                    </div>

                    <div v-if="['weekly', 'biweekly'].includes(editForm.recurring)" class="md:col-span-2">
                        <p class="text-sm font-semibold text-primary">Wochentage</p>
                        <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-7">
                            <button
                                v-for="day in weekdayOptions"
                                :key="day.value"
                                type="button"
                                class="rounded-lg border px-2 py-3 text-xs font-semibold transition"
                                :class="editForm.recurrence_days.map(Number).includes(day.value)
                                    ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary'
                                    : 'border-border bg-inputBg text-primary hover:bg-muted'"
                                @click="toggleWeekday(day.value)"
                            >
                                {{ day.label }}
                            </button>
                        </div>
                        <p v-if="editForm.errors.recurrence_days" class="mt-1 text-sm text-error">{{ editForm.errors.recurrence_days }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <p class="text-sm font-semibold text-primary">Adresse</p>
                        <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-name">Ort / Treffpunkt</label>
                                <input id="edit-location-name" v-model="editForm.location_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="z. B. Waldhaus, Sporthalle, Vereinsheim" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-street">Straße</label>
                                <input id="edit-location-street" v-model="editForm.location_street" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-house-number">Nr.</label>
                                <input id="edit-location-house-number" v-model="editForm.location_house_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-postal-code">PLZ</label>
                                <input id="edit-location-postal-code" v-model="editForm.location_postal_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-city">Stadt</label>
                                <input id="edit-location-city" v-model="editForm.location_city" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-secondary" for="edit-location-country">Land</label>
                                <input id="edit-location-country" v-model="editForm.location_country" maxlength="2" class="mt-1 w-full rounded-lg border-border bg-inputBg uppercase text-primary" placeholder="DE" />
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-primary" for="edit-max-participants">Maximale Teilnehmerzahl</label>
                        <input id="edit-max-participants" v-model="editForm.max_participants" type="number" min="1" max="100000" inputmode="numeric" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Leer lassen = unbegrenzt" />
                        <p class="mt-1 text-xs text-secondary">Nur Zusagen zaehlen gegen diese Grenze. Vielleicht und Absagen bleiben möglich.</p>
                        <p v-if="editForm.errors.max_participants" class="mt-1 text-sm text-error">{{ editForm.errors.max_participants }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-primary" for="edit-notes">Notizen</label>
                        <textarea id="edit-notes" v-model="editForm.notes" rows="5" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" />
                    </div>
                </div>

                <div v-if="editForm.errors.authorization" class="mt-4 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                    {{ editForm.errors.authorization }}
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-primary hover:bg-muted" @click="$emit('close')">
                        Abbrechen
                    </button>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover" :disabled="editForm.processing">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>


