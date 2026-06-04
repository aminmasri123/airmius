<script setup>
defineProps({
    event: { type: Object, required: true },
    timeRange: { type: String, required: true },
    hasParticipantLimit: { type: Boolean, default: false },
    recurrenceDaysLabel: { type: String, default: '' },
    formatDateTime: { type: Function, required: true },
    formatDate: { type: Function, required: true },
})
</script>

<template>
    <article class="rounded-lg border border-border bg-card p-5">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-lg bg-inputBg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Zeit</p>
                <p class="mt-2 text-sm font-semibold text-primary">{{ timeRange }}</p>
                <p v-if="event.reminder_at" class="mt-2 text-xs text-secondary">
                    Erinnerung: {{ formatDateTime(event.reminder_at) }}
                </p>
            </div>

            <div class="rounded-lg bg-inputBg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Ort</p>
                <p class="mt-2 text-sm font-semibold text-primary">{{ event.location || 'Kein Ort angegeben' }}</p>
            </div>

            <div class="rounded-lg bg-inputBg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Verein / Team</p>
                <p class="mt-2 text-sm font-semibold text-primary">
                    {{ event.team?.name || event.club?.name || 'Öffentliches Event' }}
                </p>
                <p v-if="event.team?.name && event.club?.name" class="mt-1 text-xs text-secondary">{{ event.club.name }}</p>
            </div>

            <div class="rounded-lg bg-inputBg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Besitzer</p>
                <p class="mt-2 text-sm font-semibold text-primary">{{ event.user?.name || 'Nicht gespeichert' }}</p>
            </div>

            <div class="rounded-lg bg-inputBg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Teilnehmerlimit</p>
                <p class="mt-2 text-sm font-semibold text-primary">{{ hasParticipantLimit ? `${event.max_participants} Personen` : 'Unbegrenzt' }}</p>
            </div>
        </div>

        <div class="mt-5 rounded-lg border border-border p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Beschreibung / Notizen</p>
            <p v-if="event.notes" class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-primary">{{ event.notes }}</p>
            <p v-else class="mt-3 text-sm text-secondary">Keine Notizen hinterlegt.</p>
        </div>

        <div class="mt-5 grid gap-3 text-sm md:grid-cols-3">
            <div class="rounded-lg bg-muted p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Wiederholung</p>
                <p class="mt-1 font-semibold text-primary">{{ event.recurring || 'Keine' }}</p>
                <p v-if="recurrenceDaysLabel" class="mt-1 text-xs text-secondary">{{ recurrenceDaysLabel }}</p>
            </div>
            <div class="rounded-lg bg-muted p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Wiederholung bis</p>
                <p class="mt-1 font-semibold text-primary">{{ event.recurrence_ends_at ? formatDate(event.recurrence_ends_at) : 'Nicht gesetzt' }}</p>
            </div>
            <div class="rounded-lg bg-muted p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Kommentare</p>
                <p class="mt-1 font-semibold text-primary">{{ event.comments?.length || 0 }}</p>
            </div>
        </div>
    </article>
</template>

