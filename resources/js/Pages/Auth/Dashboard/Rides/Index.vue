<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    rides: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => [] },
})

const form = useForm({
    visibility: 'friends',
    club_id: '',
    team_id: '',
    from: '',
    to: '',
    departure_time: '',
    seats: 3,
    contact_details: '',
})

const formatDateTime = (value) => {
    if (!value) return 'Nicht gesetzt'

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

const submit = () => {
    form.post(route('auth.rides.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}

const visibilityLabel = (value) => props.visibilities.find((visibility) => visibility.value === value)?.label || value

const joinRide = (ride) => {
    router.post(route('auth.rides.join', ride.id), {}, { preserveScroll: true })
}

const leaveRide = (ride) => {
    router.post(route('auth.rides.leave', ride.id), {}, { preserveScroll: true })
}

const deleteRide = (ride) => {
    if (!window.confirm('Diese Fahrgemeinschaft wirklich loeschen?')) return

    router.delete(route('auth.rides.destroy', ride.id), { preserveScroll: true })
}
</script>

<template>
    <Head title="Fahrgemeinschaften" />

    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-primary">Fahrgemeinschaften</h1>
            <p class="mt-1 text-sm text-secondary">Organisiere gemeinsame Fahrten zu Training, Events und Vereinsaktivitaeten.</p>
        </div>

        <section class="rounded-lg border border-border bg-card p-5">
            <h2 class="text-lg font-semibold text-primary">Neue Fahrgemeinschaft</h2>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Sichtbarkeit</label>
                    <select v-model="form.visibility" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option v-for="visibility in visibilities" :key="visibility.value" :value="visibility.value">
                            {{ visibility.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-secondary">
                        {{ visibilities.find((visibility) => visibility.value === form.visibility)?.description }}
                    </p>
                    <p v-if="form.errors.visibility" class="mt-1 text-xs text-error">{{ form.errors.visibility }}</p>
                </div>

                <div v-if="form.visibility === 'club'">
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Verein</label>
                    <select v-model="form.club_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option value="">Bitte waehlen</option>
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <p v-if="form.errors.club_id" class="mt-1 text-xs text-error">{{ form.errors.club_id }}</p>
                </div>

                <div v-if="form.visibility === 'team'">
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Team</label>
                    <select v-model="form.team_id" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required>
                        <option value="">Bitte waehlen</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                    <p v-if="form.errors.team_id" class="mt-1 text-xs text-error">{{ form.errors.team_id }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Plaetze</label>
                    <input v-model="form.seats" type="number" min="1" max="20" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                    <p v-if="form.errors.seats" class="mt-1 text-xs text-error">{{ form.errors.seats }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Von</label>
                    <input v-model="form.from" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                    <p v-if="form.errors.from" class="mt-1 text-xs text-error">{{ form.errors.from }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Nach</label>
                    <input v-model="form.to" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                    <p v-if="form.errors.to" class="mt-1 text-xs text-error">{{ form.errors.to }}</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Abfahrt</label>
                    <input v-model="form.departure_time" type="datetime-local" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" required />
                    <p v-if="form.errors.departure_time" class="mt-1 text-xs text-error">{{ form.errors.departure_time }}</p>
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Kontakt / Treffpunkt nach Beitritt</label>
                    <textarea
                        v-model="form.contact_details"
                        rows="3"
                        class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                        placeholder="Optional, z. B. genauer Treffpunkt oder Kontaktinfo. Wird erst nach Beitritt angezeigt."
                    ></textarea>
                    <p class="mt-1 text-xs text-secondary">Datenschutz: Diese Info sehen nur Fahrer und beigetretene Mitfahrer.</p>
                    <p v-if="form.errors.contact_details" class="mt-1 text-xs text-error">{{ form.errors.contact_details }}</p>
                </div>

                <div class="flex items-end">
                    <button class="w-full rounded-lg bg-buttonPrimary px-4 py-2 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover" :disabled="form.processing">
                        {{ form.processing ? 'Speichern...' : 'Fahrt anbieten' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <article v-for="ride in rides" :key="ride.id" class="rounded-lg border border-border bg-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ ride.from }} -> {{ ride.to }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ formatDateTime(ride.departure_time) }}</p>
                    </div>
                    <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                        {{ ride.participants_count }}/{{ ride.seats }} Plaetze
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">
                        {{ visibilityLabel(ride.visibility) }}
                    </span>
                    <span v-if="ride.club" class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">
                        {{ ride.club.name }}
                    </span>
                    <span v-if="ride.team" class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">
                        {{ ride.team.name }}
                    </span>
                </div>

                <div class="mt-4 space-y-2 text-sm">
                    <p class="text-secondary">
                        Fahrer:
                        <span class="font-semibold text-primary">{{ ride.driver?.name || 'Unbekannt' }}</span>
                    </p>
                    <p class="text-secondary">
                        Mitfahrer:
                        <span class="font-semibold text-primary">
                            {{ ride.users?.length ? ride.users.map((user) => user.name).join(', ') : 'Noch niemand' }}
                        </span>
                    </p>
                    <p class="text-secondary">
                        Kontakt/Treffpunkt:
                        <span class="font-semibold text-primary">
                            {{ ride.contact_details || (ride.is_joined ? 'Keine Angabe' : 'Nach Beitritt sichtbar') }}
                        </span>
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-if="!ride.is_joined && ride.can_join"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                        @click="joinRide(ride)"
                    >
                        Beitreten
                    </button>
                    <span
                        v-else-if="!ride.is_joined"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-secondary"
                    >
                        Nicht verfuegbar
                    </span>
                    <button
                        v-else-if="!ride.is_driver"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="leaveRide(ride)"
                    >
                        Verlassen
                    </button>
                    <button
                        v-if="ride.can_delete"
                        class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                        @click="deleteRide(ride)"
                    >
                        Loeschen
                    </button>
                </div>
            </article>

            <div v-if="!rides.length" class="rounded-lg border border-border bg-card p-8 text-center text-secondary md:col-span-2">
                Noch keine Fahrgemeinschaften vorhanden.
            </div>
        </section>
    </div>
</template>
