<script setup>
defineProps({
    invitations: { type: Array, default: () => [] },
    initials: { type: Function, required: true },
    teamRoleLabel: { type: Function, required: true },
})

const emit = defineEmits(['accept', 'decline'])
</script>

<template>
    <div
        v-if="invitations.length"
        class="space-y-3 rounded-xl border border-border bg-card p-4 sm:p-5"
    >
        <div class="flex flex-col gap-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                Offene Team-Einladungen
            </p>
            <h2 class="text-lg font-semibold text-primary">
                Du wurdest zu einem Team eingeladen
            </h2>
            <p class="text-sm text-secondary">
                Nimm die Einladung an, um dem Team und dem zugehörigen Verein beizutreten.
            </p>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <div
                v-for="invitation in invitations"
                :key="invitation.id"
                class="rounded-lg border border-border bg-bg p-4"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-bold text-primary">
                        {{ initials(invitation.team?.name) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <h3 class="truncate font-semibold text-primary">
                            {{ invitation.team?.name || 'Team' }}
                        </h3>
                        <p class="mt-1 text-sm text-secondary">
                            {{ invitation.team?.club?.name || 'Verein' }} - Rolle: {{ teamRoleLabel(invitation.role) }}
                        </p>
                        <p v-if="invitation.inviter?.name" class="mt-1 text-xs text-secondary">
                            Eingeladen von {{ invitation.inviter.name }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <button
                        type="button"
                        class="inline-flex flex-1 items-center justify-center rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                        @click="emit('accept', invitation)"
                    >
                        Annehmen
                    </button>
                    <button
                        type="button"
                        class="inline-flex flex-1 items-center justify-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-muted"
                        @click="emit('decline', invitation)"
                    >
                        Ablehnen
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
