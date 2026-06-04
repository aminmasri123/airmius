<script setup>
defineProps({
    externalMembers: { type: Array, default: () => [] },
    formatDate: { type: Function, required: true },
    inviteExternalMember: { type: Function, required: true },
})

const invitationLabel = (status) => ({
    pending: 'gesendet',
    linked: 'verknüpft',
}[status] || 'nicht gesendet')
</script>

<template>
    <section v-if="externalMembers.length" class="surface-card p-5">
        <h2 class="text-lg font-semibold text-primary">Externe Mitglieder ohne Verknuepfung</h2>
        <p class="mt-1 text-sm text-secondary">
            Diese Personen sind im Verein hinterlegt, aber noch nicht mit einem Airmius-Konto verbunden.
        </p>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            <article v-for="member in externalMembers" :key="member.id" class="rounded-lg border border-border bg-bg p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-primary">{{ member.name || member.email }}</h3>
                        <p class="text-sm text-secondary">{{ member.email }}</p>
                        <p class="mt-1 text-xs text-secondary">
                            Ende: {{ formatDate(member.membership_ends_on) }}
                        </p>
                        <p class="mt-2 text-xs text-secondary">
                            Mitgliedsnummer: {{ member.member_number || '-' }} / Lizenznummer: {{ member.athlete_license_number || '-' }}
                        </p>
                        <p class="mt-1 text-xs text-secondary">
                            Einladung: {{ invitationLabel(member.invitation_status) }}
                        </p>
                    </div>

                    <button
                        v-if="member.invitation_status !== 'linked'"
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm font-semibold text-buttonTextPrimary"
                        @click="inviteExternalMember(member)"
                    >
                        Einladung/Verknuepfung
                    </button>
                </div>
            </article>
        </div>
    </section>
</template>

