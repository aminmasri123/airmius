<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const { t, locale } = useI18n()
const tx = (key, fallback, values = {}) => {
    const translated = t(key, values)
    return translated === key ? fallback : translated
}

const props = defineProps({
    rides: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    visibilities: { type: Array, default: () => [] },
})

const showCreateModal = ref(false)
const showDeleteModal = ref(false)
const deleteTarget = ref(null)
const deleteConfirmation = ref('')
const editTarget = ref(null)
const requestTarget = ref(null)
const requestMessage = ref('')
const removeMemberTarget = ref(null)
const showPastRides = ref(false)

const joinBlockLabels = computed(() => ({
    driver: tx('rides.blocks.driver', 'Du bist der Fahrer dieser Fahrt.'),
    already_joined: tx('rides.blocks.already_joined', 'Du bist bereits beigetreten.'),
    pending_request: tx('rides.blocks.pending_request', 'Anfrage ausstehend.'),
    full: tx('rides.blocks.full', 'Diese Fahrgemeinschaft ist voll.'),
    past: tx('rides.blocks.past', 'Die Abfahrtszeit liegt in der Vergangenheit.'),
    not_allowed: tx('rides.blocks.not_allowed', 'Du hast keinen Zugriff auf diese Fahrt.'),
    unavailable: tx('rides.blocks.unavailable', 'Diese Fahrt ist aktuell nicht buchbar.'),
}))

const joinStateMeta = computed(() => ({
    driver: {
        icon: 'la-user-check',
        label: tx('rides.states.driver', 'Eigene Fahrt'),
        statusClass: 'border border-air-blue/40 bg-air-blue/10 text-air-blue',
    },
    pending_request: {
        icon: 'la-hourglass-half',
        label: tx('rides.states.pending', 'Anfrage offen'),
        statusClass: 'border border-air-blue/40 bg-air-blue/10 text-air-blue',
    },
    full: {
        icon: 'la-exclamation-triangle',
        label: tx('rides.states.full', 'Voll'),
        statusClass: 'border border-error/40 bg-error/10 text-error',
    },
    past: {
        icon: 'la-calendar-times',
        label: tx('rides.states.past', 'Vergangenheit'),
        statusClass: 'border border-border bg-muted text-secondary',
    },
    not_allowed: {
        icon: 'la-lock',
        label: tx('rides.states.not_visible', 'Nicht sichtbar'),
        statusClass: 'border border-border bg-muted text-secondary',
    },
    unavailable: {
        icon: 'la-ban',
        label: tx('rides.states.unavailable', 'Nicht buchbar'),
        statusClass: 'border border-border bg-muted text-secondary',
    },
}))

const joinStateFor = (reason) => joinStateMeta.value[reason] || joinStateMeta.value.unavailable
const joinBlockLegend = computed(() => [
    { reason: 'not_allowed', label: tx('rides.legend.not_allowed', 'Kein Zugriff (Sichtbarkeit/Status).') },
    { reason: 'full', label: tx('rides.legend.full', 'Volle Fahrt.') },
    { reason: 'past', label: tx('rides.legend.past', 'Vergangene Fahrt.') },
    { reason: 'pending_request', label: tx('rides.legend.pending', 'Anfrage ausstehend.') },
])

const form = useForm({
    visibility: 'friends',
    club_id: '',
    team_id: '',
    from: '',
    to: '',
    pickup_name: '',
    pickup_street: '',
    pickup_house_number: '',
    pickup_postal_code: '',
    pickup_city: '',
    pickup_country: 'DE',
    pickup_note: '',
    departure_time: '',
    seats: 3,
    contact_details: '',
})

const formatDateTime = (value) => {
    if (!value) return tx('rides.not_set', 'Nicht gesetzt')

    const localeCode = ({ ar: 'ar-EG', fr: 'fr-FR', en: 'en-US', de: 'de-DE' })[locale.value] || 'de-DE'
    return new Intl.DateTimeFormat(localeCode, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

const toDateTimeLocal = (value) => {
    if (!value) return ''

    const date = new Date(value)
    const offset = date.getTimezoneOffset()
    const local = new Date(date.getTime() - offset * 60 * 1000)

    return local.toISOString().slice(0, 16)
}

const visibleRides = computed(() => {
    const now = new Date()

    return [...props.rides]
        .filter((ride) => {
            if (showPastRides.value) {
                return true
            }

            if (!ride.departure_time) {
                return true
            }

            return new Date(ride.departure_time) >= now
        })
        .sort((a, b) => {
            if (!a.departure_time) return 1
            if (!b.departure_time) return -1

            return new Date(a.departure_time) - new Date(b.departure_time)
        })
})

const resetForm = () => {
    form.reset()
    form.visibility = 'friends'
    form.seats = 3
    form.pickup_country = 'DE'
}

const openCreateModal = () => {
    editTarget.value = null
    resetForm()
    showCreateModal.value = true
}

const openEditModal = (ride) => {
    editTarget.value = ride
    form.visibility = ride.visibility || 'friends'
    form.club_id = ride.club_id || ''
    form.team_id = ride.team_id || ''
    form.from = ride.from || ''
    form.to = ride.to || ''
    form.pickup_name = ride.pickup_name || ''
    form.pickup_street = ride.pickup_street || ''
    form.pickup_house_number = ride.pickup_house_number || ''
    form.pickup_postal_code = ride.pickup_postal_code || ''
    form.pickup_city = ride.pickup_city || ''
    form.pickup_country = ride.pickup_country || 'DE'
    form.pickup_note = ride.pickup_note || ''
    form.departure_time = toDateTimeLocal(ride.departure_time)
    form.seats = ride.seats || 1
    form.contact_details = ride.contact_details || ''
    form.clearErrors()
    showCreateModal.value = true
}

const closeCreateModal = () => {
    showCreateModal.value = false
    editTarget.value = null
    form.clearErrors()
}

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetForm()
            closeCreateModal()
        },
    }

    if (editTarget.value) {
        form.put(route('auth.rides.update', editTarget.value.id), options)
        return
    }

    form.post(route('auth.rides.store'), options)
}

const visibilityLabel = (value) => tx(props.visibilities.find((visibility) => visibility.value === value)?.label || value, value)
const joinBlockLabel = (reason) => joinBlockLabels.value[reason] || tx('Nicht verfügbar.', 'Nicht verfügbar.')
const rideIsFull = (ride) => Number(ride.participants_count) >= Number(ride.seats)
const joinStatusMeta = (ride) => {
    if (ride.can_join) {
        return null
    }

    return joinStateFor(ride.join_block_reason || 'unavailable')
}

const roleBadgeMeta = (ride) => {
    if (ride.is_driver) {
        return {
            icon: 'la-steering-wheel',
            label: tx('rides.role.driver', 'Fahrer'),
            className: 'border border-air-blue/40 bg-air-blue/10 text-air-blue',
        }
    }

    if (ride.is_joined) {
        return {
            icon: 'la-check-circle',
            label: tx('rides.role.joined', 'Dabei'),
            className: 'border border-success/40 bg-success/10 text-success',
        }
    }

    if (ride.has_pending_request) {
        return {
            icon: 'la-hourglass-half',
            label: tx('rides.states.pending', 'Anfrage offen'),
            className: 'border border-air-blue/40 bg-air-blue/10 text-air-blue',
        }
    }

    return null
}

const rideCardClass = (ride) => {
    if (rideIsFull(ride)) {
        return 'border-error/40'
    }

    if (ride.has_pending_request || ride.is_driver || ride.is_joined) {
        return 'border-air-blue/40'
    }

    return 'border-border'
}

const rideActionHint = (ride) => {
    if (ride.can_join) {
        return tx('rides.hints.can_join', 'Du kannst eine Anfrage senden. Private Treffpunkt- und Kontaktdaten werden erst nach Annahme sichtbar.')
    }

    if (ride.has_pending_request) {
        return tx('rides.hints.pending', 'Deine Anfrage wartet auf Rückmeldung. Du kannst sie jederzeit zurückziehen.')
    }

    if (ride.is_joined) {
        return tx('rides.hints.joined', 'Du bist dabei und siehst die freigegebenen Treffpunkt- und Kontaktdaten.')
    }

    if (ride.is_driver) {
        return ride.pending_requests?.length
            ? tx('rides.hints.driver_pending', 'Prüfe offene Anfragen und halte die Sitzplätze aktuell.')
            : tx('rides.hints.driver', 'Du verwaltest diese Fahrt.')
    }

    return joinBlockLabel(ride.join_block_reason)
}

const seatBadgeMeta = (ride) => {
    if (rideIsFull(ride)) {
        return {
            icon: 'la-exclamation-circle',
            label: `${ride.participants_count}/${ride.seats} ${tx('rides.seats', 'Plätze')} - ${tx('rides.states.full', 'Voll')}`,
            className: 'border border-error/40 bg-error/10 text-error',
            hint: tx('rides.blocks.full', 'Diese Fahrgemeinschaft ist voll.'),
        }
    }

    if (ride.join_block_reason === 'past') {
        return {
            icon: 'la-calendar-times',
            label: `${ride.participants_count}/${ride.seats} ${tx('rides.seats', 'Plätze')} - ${tx('rides.states.past_short', 'Vergangen')}`,
            className: 'border border-border bg-muted text-secondary',
            hint: joinBlockLabel(ride.join_block_reason),
        }
    }

    return {
        icon: 'la-users',
        label: `${ride.participants_count}/${ride.seats} ${tx('rides.seats', 'Plätze')}`,
        className: 'border border-border bg-inputBg text-secondary',
        hint: tx('rides.seat_hint', 'Platzbelegung'),
    }
}

const openRequestModal = (ride) => {
    requestTarget.value = ride
    requestMessage.value = ''
}

const closeRequestModal = () => {
    requestTarget.value = null
    requestMessage.value = ''
}

const sendRideRequest = () => {
    if (!requestTarget.value) return

    router.post(route('auth.rides.join', requestTarget.value.id), {
        message: requestMessage.value,
    }, {
        preserveScroll: true,
        onSuccess: () => closeRequestModal(),
    })
}

const leaveRide = (ride) => {
    router.post(route('auth.rides.leave', ride.id), {}, { preserveScroll: true })
}

const approveRequest = (ride, request) => {
    router.post(route('auth.rides.requests.approve', [ride.id, request.id]), {}, { preserveScroll: true })
}

const rejectRequest = (ride, request) => {
    router.post(route('auth.rides.requests.reject', [ride.id, request.id]), {}, { preserveScroll: true })
}

const openRemoveMemberModal = (ride, member) => {
    removeMemberTarget.value = { ride, member }
}

const closeRemoveMemberModal = () => {
    removeMemberTarget.value = null
}

const removeAcceptedMember = () => {
    if (!removeMemberTarget.value) return

    router.delete(route('auth.rides.members.destroy', [
        removeMemberTarget.value.ride.id,
        removeMemberTarget.value.member.id,
    ]), {
        preserveScroll: true,
        onSuccess: () => closeRemoveMemberModal(),
    })
}

const openDeleteModal = (ride) => {
    deleteTarget.value = ride
    deleteConfirmation.value = ''
    showDeleteModal.value = true
}

const closeDeleteModal = () => {
    showDeleteModal.value = false
    deleteTarget.value = null
    deleteConfirmation.value = ''
}

const confirmDeleteRide = () => {
    if (!deleteTarget.value || deleteConfirmation.value !== 'DELETE') return

    router.delete(route('auth.rides.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    })
}
</script>

<template>
    <Head :title="tx('Fahrgemeinschaften', 'Fahrgemeinschaften')" />

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-primary">{{ tx('Fahrgemeinschaften', 'Fahrgemeinschaften') }}</h1>
                <p class="mt-1 text-sm text-secondary">{{ tx('Organisiere gemeinsame Fahrten zu Training, Events und Vereinsaktivitaeten.', 'Organisiere gemeinsame Fahrten zu Training, Events und Vereinsaktivitäten.') }}</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                <label class="inline-flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3 py-2 text-xs font-semibold text-secondary">
                    <input v-model="showPastRides" type="checkbox" class="h-4 w-4 rounded border-border" />
                    {{ tx('Vergangene Fahrten anzeigen', 'Vergangene Fahrten anzeigen') }}
                </label>

                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover sm:w-auto"
                    @click="openCreateModal"
                >
                    <i class="las la-plus text-lg"></i>
                    {{ tx('Fahrt anbieten', 'Fahrt anbieten') }}
                </button>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-card p-3 text-xs text-secondary">
            <p class="font-semibold text-primary">{{ tx('Anzeige-Hinweise:', 'Anzeige-Hinweise:') }}</p>
            <ul class="mt-2 space-y-1">
                <li v-for="item in joinBlockLegend" :key="item.reason" class="flex items-start gap-2">
                    <span class="mt-0.5 inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-semibold" :class="joinStateFor(item.reason).statusClass">
                        <i :class="['las', joinStateFor(item.reason).icon]"></i>
                    </span>
                    <span>{{ item.label }}</span>
                </li>
            </ul>
        </div>

        <Modal :show="showCreateModal" max-width="2xl" @close="closeCreateModal">
            <form class="max-h-[calc(100dvh-3.5rem)] overflow-y-auto p-1 pr-2" @submit.prevent="submit">
                <div class="sticky top-0 z-10 border-b border-border bg-card pb-4 pr-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('Fahrgemeinschaft', 'Fahrgemeinschaft') }}</p>
                            <h2 class="mt-1 text-lg font-semibold text-primary">{{ editTarget ? tx('Fahrt bearbeiten', 'Fahrt bearbeiten') : tx('Neue Fahrt anbieten', 'Neue Fahrt anbieten') }}</h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ tx('Erstelle eine datenschutzfreundliche Fahrt mit öffentlichem Treffpunkt statt privater Adresse.', 'Erstelle eine datenschutzfreundliche Fahrt mit öffentlichem Treffpunkt statt privater Adresse.') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-secondary hover:bg-muted hover:text-primary"
                            @click="closeCreateModal"
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
                        {{ visibilities.find((visibility) => visibility.value === form.visibility)?.description }}
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

                <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4">
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
                            />
                            <p v-if="form.errors.pickup_name" class="mt-1 text-xs text-error">{{ form.errors.pickup_name }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Stadt</label>
                            <input v-model="form.pickup_city" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Stadt" />
                            <p v-if="form.errors.pickup_city" class="mt-1 text-xs text-error">{{ form.errors.pickup_city }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">PLZ</label>
                            <input v-model="form.pickup_postal_code" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="PLZ" />
                            <p v-if="form.errors.pickup_postal_code" class="mt-1 text-xs text-error">{{ form.errors.pickup_postal_code }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Land</label>
                            <input v-model="form.pickup_country" maxlength="2" class="mt-1 w-full rounded-lg border-border bg-inputBg uppercase text-primary" placeholder="DE" />
                            <p v-if="form.errors.pickup_country" class="mt-1 text-xs text-error">{{ form.errors.pickup_country }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Straße optional</label>
                            <input v-model="form.pickup_street" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Straße" />
                            <p v-if="form.errors.pickup_street" class="mt-1 text-xs text-error">{{ form.errors.pickup_street }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Hausnummer optional</label>
                            <input v-model="form.pickup_house_number" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Nr." />
                            <p v-if="form.errors.pickup_house_number" class="mt-1 text-xs text-error">{{ form.errors.pickup_house_number }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-secondary">Hinweis optional</label>
                            <input
                                v-model="form.pickup_note"
                                class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary"
                                placeholder="z. B. Eingang Nord, bei den Fahrradständern"
                            />
                            <p v-if="form.errors.pickup_note" class="mt-1 text-xs text-error">{{ form.errors.pickup_note }}</p>
                        </div>
                    </div>
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

                <div class="md:col-span-2">
                    <button class="w-full rounded-lg bg-buttonPrimary px-4 py-3 font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:opacity-60" :disabled="form.processing">
                        {{ form.processing ? 'Speichern...' : (editTarget ? 'Änderungen speichern' : 'Fahrt anbieten') }}
                    </button>
                </div>
                </div>
            </form>
        </Modal>

        <Modal :show="showDeleteModal" max-width="md" @close="closeDeleteModal">
            <div v-if="deleteTarget" class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-error">Fahrgemeinschaft löschen</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">
                        {{ deleteTarget.from }} -> {{ deleteTarget.to }}
                    </h2>
                    <p class="mt-2 text-sm text-secondary">
                        Diese Fahrt wird dauerhaft gelöscht. Beigetretene Mitfahrer verlieren den Zugriff auf Kontakt- und Treffpunktdaten.
                    </p>
                </div>

                <div class="rounded-lg border border-error/30 bg-error/10 p-3 text-sm text-error">
                    Bitte gib <strong>DELETE</strong> ein, um die Aktion zu bestätigen.
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Bestätigung</span>
                    <input
                        v-model="deleteConfirmation"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="DELETE"
                        autocomplete="off"
                    >
                </label>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeDeleteModal"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="deleteConfirmation !== 'DELETE'"
                        @click="confirmDeleteRide"
                    >
                        Endgültig löschen
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="Boolean(requestTarget)" max-width="md" @close="closeRequestModal">
            <div v-if="requestTarget" class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Mitfahranfrage</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">
                        {{ requestTarget.from }} -> {{ requestTarget.to }}
                    </h2>
                    <p class="mt-2 text-sm text-secondary">
                        Deine Anfrage wird an den Fahrer gesendet. Kontaktdaten und genaue Treffpunktdetails siehst du erst nach Annahme.
                    </p>
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-primary">Nachricht optional</span>
                    <textarea
                        v-model="requestMessage"
                        rows="4"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                        placeholder="z. B. Ich kann am Treffpunkt sein."
                    ></textarea>
                </label>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeRequestModal"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                        @click="sendRideRequest"
                    >
                        Anfrage senden
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="Boolean(removeMemberTarget)" max-width="md" @close="closeRemoveMemberModal">
            <div v-if="removeMemberTarget" class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-error">Mitfahrer entfernen</p>
                    <h2 class="mt-1 text-lg font-bold text-primary">
                        {{ removeMemberTarget.member.name }}
                    </h2>
                    <p class="mt-2 text-sm text-secondary">
                        Diese Person wird aus der Fahrgemeinschaft
                        <span class="font-semibold text-primary">{{ removeMemberTarget.ride.from }} -> {{ removeMemberTarget.ride.to }}</span>
                        entfernt und bekommt eine Benachrichtigung.
                    </p>
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="closeRemoveMemberModal"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white"
                        @click="removeAcceptedMember"
                    >
                        Entfernen
                    </button>
                </div>
            </div>
        </Modal>

        <section class="grid gap-4 md:grid-cols-2">
            <article v-for="ride in visibleRides" :key="ride.id" class="rounded-lg border bg-card p-5" :class="rideCardClass(ride)">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">{{ ride.from }} -> {{ ride.to }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ formatDateTime(ride.departure_time) }}</p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold"
                        :class="seatBadgeMeta(ride).className"
                        :title="seatBadgeMeta(ride).hint"
                    >
                        <i :class="['las', seatBadgeMeta(ride).icon]" aria-hidden="true"></i>
                        <span class="ml-1">{{ seatBadgeMeta(ride).label }}</span>
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-if="roleBadgeMeta(ride)"
                        class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold"
                        :class="roleBadgeMeta(ride).className"
                    >
                        <i :class="['las', roleBadgeMeta(ride).icon]" aria-hidden="true"></i>
                        {{ roleBadgeMeta(ride).label }}
                    </span>
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
                        <span v-if="!ride.users?.length" class="font-semibold text-primary">
                            Noch niemand
                        </span>
                    </p>
                    <div v-if="ride.users?.length" class="flex flex-wrap gap-2">
                        <span
                            v-for="member in ride.users"
                            :key="member.id"
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-inputBg px-3 py-1 text-xs font-semibold text-primary"
                        >
                            {{ member.name }}
                            <button
                                v-if="member.can_remove"
                                type="button"
                                class="text-error hover:text-error/80"
                                :aria-label="`${member.name} entfernen`"
                                @click="openRemoveMemberModal(ride, member)"
                            >
                                <i class="las la-times text-base"></i>
                            </button>
                        </span>
                    </div>
                    <p class="text-secondary">
                        Treffpunkt:
                        <span class="font-semibold text-primary">
                            {{ ride.pickup_private_label || ride.pickup_public_label || 'Keine Angabe' }}
                        </span>
                    </p>
                    <p class="text-secondary">
                        Kontakt:
                        <span class="font-semibold text-primary">
                            {{ ride.contact_details || (ride.is_joined ? 'Keine Angabe' : 'Nach Beitritt sichtbar') }}
                        </span>
                    </p>
                </div>

                <div v-if="ride.is_driver && ride.pending_requests?.length" class="mt-4 rounded-lg border border-air-blue/30 bg-air-blue/10 p-3">
                    <h3 class="text-sm font-semibold text-primary">Offene Anfragen</h3>
                    <div class="mt-3 space-y-3">
                        <div v-for="request in ride.pending_requests" :key="request.id" class="rounded-lg border border-border bg-card p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-semibold text-primary">{{ request.name }}</p>
                                    <p v-if="request.message" class="mt-1 text-sm text-secondary">{{ request.message }}</p>
                                    <p v-else class="mt-1 text-xs text-secondary">Keine Nachricht angegeben.</p>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        class="inline-flex items-center gap-1 rounded-lg bg-buttonPrimary px-3 py-2 text-xs font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="rideIsFull(ride)"
                                        :title="rideIsFull(ride) ? 'Keine freien Plätze mehr.' : 'Mitfahranfrage annehmen.'"
                                        @click="approveRequest(ride, request)"
                                    >
                                        <i class="las la-check" aria-hidden="true"></i>
                                        Annehmen
                                    </button>
                                    <button class="inline-flex items-center gap-1 rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error" @click="rejectRequest(ride, request)">
                                        <i class="las la-times" aria-hidden="true"></i>
                                        Ablehnen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-xs text-secondary">
                    {{ rideActionHint(ride) }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-if="ride.can_update"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="openEditModal(ride)"
                    >
                        Bearbeiten
                    </button>
                    <button
                        v-if="!ride.is_joined && ride.can_join"
                        class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                        @click="openRequestModal(ride)"
                    >
                        Anfrage senden
                    </button>
                    <button
                        v-else-if="ride.has_pending_request"
                        class="inline-flex items-center gap-2 rounded-lg border border-air-blue/40 px-4 py-2 text-sm font-semibold text-air-blue"
                        type="button"
                        @click="leaveRide(ride)"
                    >
                        <i class="las la-hourglass-half" aria-hidden="true"></i>
                        Anfrage zurückziehen
                    </button>
                    <span
                        v-else-if="!ride.is_joined && joinStatusMeta(ride)"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold"
                        :class="joinStatusMeta(ride).statusClass"
                        role="status"
                        :aria-label="joinStatusMeta(ride).label"
                        :title="joinStatusMeta(ride).label"
                    >
                        <i :class="['las', joinStatusMeta(ride).icon]" aria-hidden="true"></i>
                        <span>{{ joinStatusMeta(ride).label }}</span>
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
                        @click="openDeleteModal(ride)"
                    >
                        Löschen
                    </button>
                </div>
            </article>

            <div v-if="!visibleRides.length" class="rounded-lg border border-border bg-card p-8 text-center text-secondary md:col-span-2">
                Noch keine Fahrgemeinschaften vorhanden.
            </div>
        </section>
    </div>
</template>
