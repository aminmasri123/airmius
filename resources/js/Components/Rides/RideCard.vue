<script setup>
import {
    formatDateTime,
    joinStatusMeta,
    rideActionHint,
    rideCardClass,
    rideIsFull,
    roleBadgeMeta,
    seatBadgeMeta,
    visibilityLabel,
} from '@/Components/Rides/rideDisplay'
import { computed } from 'vue'

const props = defineProps({
    ride: { type: Object, required: true },
    visibilities: { type: Array, default: () => [] },
})

const emit = defineEmits([
    'approve',
    'delete',
    'edit',
    'leave',
    'reject',
    'remove-member',
    'request',
])

const cardClass = computed(() => rideCardClass(props.ride))
const seatMeta = computed(() => seatBadgeMeta(props.ride))
const roleBadge = computed(() => roleBadgeMeta(props.ride))
const joinStatus = computed(() => joinStatusMeta(props.ride))
const actionHint = computed(() => rideActionHint(props.ride))
const formattedDeparture = computed(() => formatDateTime(props.ride.departure_time))
const visibilityName = computed(() => visibilityLabel(props.visibilities, props.ride.visibility))
</script>

<template>
    <article class="rounded-lg border bg-card p-5" :class="cardClass">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-primary">{{ ride.from }} -> {{ ride.to }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ formattedDeparture }}</p>
            </div>
            <span
                class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold"
                :class="seatMeta.className"
                :title="seatMeta.hint"
            >
                <i :class="['las', seatMeta.icon]" aria-hidden="true"></i>
                <span class="ml-1">{{ seatMeta.label }}</span>
            </span>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <span
                v-if="roleBadge"
                class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold"
                :class="roleBadge.className"
            >
                <i :class="['las', roleBadge.icon]" aria-hidden="true"></i>
                {{ roleBadge.label }}
            </span>
            <span class="rounded-full bg-muted px-2 py-1 text-xs font-semibold text-secondary">
                {{ visibilityName }}
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
                        @click="emit('remove-member', ride, member)"
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
                                @click="emit('approve', ride, request)"
                            >
                                <i class="las la-check" aria-hidden="true"></i>
                                Annehmen
                            </button>
                            <button class="inline-flex items-center gap-1 rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error" @click="emit('reject', ride, request)">
                                <i class="las la-times" aria-hidden="true"></i>
                                Ablehnen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="mt-4 text-xs text-secondary">
            {{ actionHint }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            <button
                v-if="ride.can_update"
                class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                @click="emit('edit', ride)"
            >
                Bearbeiten
            </button>
            <button
                v-if="!ride.is_joined && ride.can_join"
                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                @click="emit('request', ride)"
            >
                Anfrage senden
            </button>
            <button
                v-else-if="ride.has_pending_request"
                class="inline-flex items-center gap-2 rounded-lg border border-air-blue/40 px-4 py-2 text-sm font-semibold text-air-blue"
                type="button"
                @click="emit('leave', ride)"
            >
                <i class="las la-hourglass-half" aria-hidden="true"></i>
                Anfrage zurückziehen
            </button>
            <span
                v-else-if="!ride.is_joined && joinStatus"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold"
                :class="joinStatus.statusClass"
                role="status"
                :aria-label="joinStatus.label"
                :title="joinStatus.label"
            >
                <i :class="['las', joinStatus.icon]" aria-hidden="true"></i>
                <span>{{ joinStatus.label }}</span>
            </span>
            <button
                v-else-if="!ride.is_driver"
                class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                @click="emit('leave', ride)"
            >
                Verlassen
            </button>
            <button
                v-if="ride.can_delete"
                class="rounded-lg border border-error/40 px-4 py-2 text-sm font-semibold text-error hover:bg-error/10"
                @click="emit('delete', ride)"
            >
                Löschen
            </button>
        </div>
    </article>
</template>

