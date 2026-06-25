<script setup>
defineProps({
    form: { type: Object, required: true },
    recurrenceLabel: { type: Function, required: true },
    recurrenceSummary: { type: String, default: '' },
    selectedClubName: { type: String, default: '-' },
    selectedTeamName: { type: String, default: '-' },
    typeLabels: { type: Object, default: () => ({}) },
    visibilityLabels: { type: Object, default: () => ({}) },
})
</script>

<template>
    <section class="space-y-4">
        <div>
            <h3 class="text-base font-semibold text-primary">
                Prüfen
            </h3>

            <p class="mt-1 text-sm text-secondary">
                Kontrolliere deine Angaben vor dem Speichern.
            </p>
        </div>

        <div class="rounded-xl border border-border bg-inputBg p-4">
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        Titel
                    </p>
                    <p class="mt-1 font-semibold text-primary">
                        {{ form.title || '-' }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                            Typ
                        </p>
                        <p class="mt-1 text-primary">
                            {{ $t(typeLabels[form.type] || form.type) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                            Sichtbarkeit
                        </p>
                        <p class="mt-1 text-primary">
                            {{ $t(visibilityLabels[form.visibility] || form.visibility) }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                            Verein
                        </p>
                        <p class="mt-1 text-primary">
                            {{ selectedClubName }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                            Team
                        </p>
                        <p class="mt-1 text-primary">
                            {{ selectedTeamName }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                            Start
                        </p>
                        <p class="mt-1 text-primary">
                            {{ form.start_time || '-' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                            Ende
                        </p>
                        <p class="mt-1 text-primary">
                            {{ form.end_time || '-' }}
                        </p>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        Erinnerung
                    </p>
                    <p class="mt-1 text-primary">
                        {{ form.reminder_at || '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        Wiederholung
                    </p>
                    <p class="mt-1 text-primary">
                        {{ recurrenceSummary || recurrenceLabel(form.recurring) || 'Keine' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        Teilnehmerlimit
                    </p>
                    <p class="mt-1 text-primary">
                        {{ form.max_participants ? `${form.max_participants} Personen` : 'Unbegrenzt' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        Strafkatalog
                    </p>
                    <p class="mt-1 text-primary">
                        {{ form.uses_penalty_catalog ? 'Aktiv für dieses Team-Event' : 'Nicht aktiv' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        Ort
                    </p>
                    <p class="mt-1 text-primary">
                        {{ form.location || [form.location_name, [form.location_street, form.location_house_number].filter(Boolean).join(' '), [form.location_postal_code, form.location_city].filter(Boolean).join(' ')].filter(Boolean).join(', ') || '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                        Notizen
                    </p>
                    <p class="mt-1 whitespace-pre-line break-words text-primary">
                        {{ form.notes || '-' }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

