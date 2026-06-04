<script setup>
import Modal from '@/Components/Modal.vue'
import { computed } from 'vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    editTarget: { type: Object, default: null },
    form: { type: Object, required: true },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'submit'])

const visibilityDescription = computed(() => {
    return props.visibilities.find((visibility) => visibility.value === props.form.visibility)?.description
})

const submitLabel = computed(() => {
    if (props.form.processing) {
        return 'Speichern...'
    }

    return props.editTarget ? 'Änderungen speichern' : 'Fahrt anbieten'
})
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <form class="max-h-[calc(100dvh-3.5rem)] overflow-y-auto p-1 pr-2" @submit.prevent="emit('submit')">
            <div class="sticky top-0 z-10 border-b border-border bg-card pb-4 pr-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Fahrgemeinschaft</p>
                        <h2 class="mt-1 text-lg font-semibold text-primary">
                            {{ editTarget ? 'Fahrt bearbeiten' : 'Neue Fahrt anbieten' }}
                        </h2>
                        <p class="mt-1 text-sm text-secondary">
                            Erstelle eine datenschutzfreundliche Fahrt mit öffentlichem Treffpunkt statt privater Adresse.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary"
                        @click="emit('close')"
                    >
                        <i class="las la-times text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Sichtbarkeit</label>
                    <select v-model="form.visibility" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option v-for="visibility in visibilities" :key="visibility.value" :value="visibility.value">
                            {{ visibility.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-secondary">
                        {{ visibilityDescription }}
                    </p>
                    <p v-if="form.errors.visibility" class="mt-1 text-xs text-error">{{ form.errors.visibility }}</p>
                </div>

                <div v-if="form.visibility === 'club'">
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Verein</label>
                    <select v-model="form.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option value="">Bitte wählen</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <p v-if="form.errors.club_id" class="mt-1 text-xs text-error">{{ form.errors.club_id }}</p>
                </div>

                <div v-if="form.visibility === 'team'">
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Team</label>
                    <select v-model="form.team_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option value="">Bitte wählen</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                    <p v-if="form.errors.team_id" class="mt-1 text-xs text-error">{{ form.errors.team_id }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Plätze</label>
                    <input
                        v-model="form.seats"
                        type="number"
                        min="1"
                        max="20"
                        class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                        required
                    >
                    <p v-if="form.errors.seats" class="mt-1 text-xs text-error">{{ form.errors.seats }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Von</label>
                    <input v-model="form.from" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                    <p v-if="form.errors.from" class="mt-1 text-xs text-error">{{ form.errors.from }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Nach</label>
                    <input v-model="form.to" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                    <p v-if="form.errors.to" class="mt-1 text-xs text-error">{{ form.errors.to }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Abfahrt</label>
                    <input
                        v-model="form.departure_time"
                        type="datetime-local"
                        class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                        required
                    >
                    <p v-if="form.errors.departure_time" class="mt-1 text-xs text-error">{{ form.errors.departure_time }}</p>
                </div>

                <div class="rounded-lg border border-border bg-bg p-4 md:col-span-2">
                    <h3 class="text-sm font-semibold text-primary">Treffpunkt</h3>
                    <p class="mt-1 text-xs text-secondary">
                        Bitte möglichst einen öffentlichen Treffpunkt angeben, keine private Wohnadresse.
                    </p>

                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Treffpunkt / Ort</label>
                            <input
                                v-model="form.pickup_name"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                placeholder="z. B. Vereinsheim, Parkplatz Sporthalle"
                            >
                            <p v-if="form.errors.pickup_name" class="mt-1 text-xs text-error">{{ form.errors.pickup_name }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Stadt</label>
                            <input
                                v-model="form.pickup_city"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                placeholder="Stadt"
                            >
                            <p v-if="form.errors.pickup_city" class="mt-1 text-xs text-error">{{ form.errors.pickup_city }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">PLZ</label>
                            <input
                                v-model="form.pickup_postal_code"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                placeholder="PLZ"
                            >
                            <p v-if="form.errors.pickup_postal_code" class="mt-1 text-xs text-error">
                                {{ form.errors.pickup_postal_code }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Land</label>
                            <input
                                v-model="form.pickup_country"
                                maxlength="2"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg uppercase text-primary"
                                placeholder="DE"
                            >
                            <p v-if="form.errors.pickup_country" class="mt-1 text-xs text-error">{{ form.errors.pickup_country }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Straße optional</label>
                            <input
                                v-model="form.pickup_street"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                placeholder="Straße"
                            >
                            <p v-if="form.errors.pickup_street" class="mt-1 text-xs text-error">{{ form.errors.pickup_street }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Hausnummer optional</label>
                            <input
                                v-model="form.pickup_house_number"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                placeholder="Nr."
                            >
                            <p v-if="form.errors.pickup_house_number" class="mt-1 text-xs text-error">
                                {{ form.errors.pickup_house_number }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Hinweis optional</label>
                            <input
                                v-model="form.pickup_note"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                placeholder="z. B. Eingang Nord, bei den Fahrradständern"
                            >
                            <p v-if="form.errors.pickup_note" class="mt-1 text-xs text-error">{{ form.errors.pickup_note }}</p>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        Kontakt / Treffpunkt nach Beitritt
                    </label>
                    <textarea
                        v-model="form.contact_details"
                        rows="3"
                        class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                        placeholder="Optional, z. B. genauer Treffpunkt oder Kontaktinfo. Wird erst nach Beitritt angezeigt."
                    ></textarea>
                    <p class="mt-1 text-xs text-secondary">Datenschutz: Diese Info sehen nur Fahrer und beigetretene Mitfahrer.</p>
                    <p v-if="form.errors.contact_details" class="mt-1 text-xs text-error">{{ form.errors.contact_details }}</p>
                </div>

                <div class="md:col-span-2">
                    <button
                        class="w-full rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ submitLabel }}
                    </button>
                </div>
            </div>
        </form>
    </Modal>
</template>

