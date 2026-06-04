<script setup>
defineProps({
    clubRequests: { type: Array, default: () => [] },
    pendingRequests: { type: Array, default: () => [] },
    processingJoinRequestIds: { type: Object, required: true },
    formatMoney: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    intervalLabel: { type: Function, required: true },
    approveClubRequest: { type: Function, required: true },
    declineClubRequest: { type: Function, required: true },
    approveRequest: { type: Function, required: true },
    declineRequest: { type: Function, required: true },
})

const clubRequestCopy = (request) => {
    if (request.type === 'pause') return 'möchte die Mitgliedschaft pausieren'
    if (request.type === 'removal_objection') return 'widerspricht der Entfernung aus dem Verein'

    return 'möchte Vereinsmitglied werden'
}
</script>

<template>
    <section class="surface-card p-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-primary">Offene Beitrittsanfragen</h2>
                <p class="mt-1 text-sm text-secondary">
                    Personen treten erst nach Annahme dem Team und Verein bei.
                </p>
            </div>
        </div>

        <div class="mt-4 space-y-3">
            <article v-for="request in clubRequests" :key="`club-${request.id}`" class="rounded-lg border border-air-blue/30 bg-air-blue/5 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="font-semibold text-primary">{{ request.user.name }}</p>
                        <p class="text-sm text-secondary">{{ request.user.email }} {{ clubRequestCopy(request) }}</p>
                        <p v-if="request.membership_type" class="mt-1 text-xs text-secondary">
                            Typ: {{ request.membership_type.name }} / Vorschau {{ formatMoney(request.preview_amount) }} / {{ intervalLabel(request.preview_interval) }}
                        </p>
                        <p v-if="request.requested_pause_from" class="mt-1 text-xs text-secondary">
                            Pause: {{ formatDate(request.requested_pause_from) }} bis {{ formatDate(request.requested_pause_until) }}
                        </p>
                        <p v-if="request.message" class="mt-2 text-sm text-secondary">{{ request.message }}</p>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="approveClubRequest(request)">
                            Annehmen
                        </button>
                        <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary" @click="declineClubRequest(request)">
                            Ablehnen
                        </button>
                    </div>
                </div>
            </article>

            <article v-for="request in pendingRequests" :key="request.id" class="rounded-lg border border-border bg-bg p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="font-semibold text-primary">{{ request.user.name }}</p>
                        <p class="text-sm text-secondary">
                            {{ request.user.email }} möchte zu {{ request.team.name }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60"
                            :disabled="processingJoinRequestIds.has(request.id)"
                            @click="approveRequest(request)"
                        >
                            Annehmen
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary disabled:opacity-60"
                            :disabled="processingJoinRequestIds.has(request.id)"
                            @click="declineRequest(request)"
                        >
                            Ablehnen
                        </button>
                    </div>
                </div>
            </article>

            <p v-if="!pendingRequests.length && !clubRequests.length" class="rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                Keine offenen Anfragen.
            </p>
        </div>
    </section>
</template>

