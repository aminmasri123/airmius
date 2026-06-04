<script setup>
defineProps({
    club: { type: Object, required: true },
    openJobModal: { type: Function, required: true },
    editJob: { type: Function, required: true },
    deleteJob: { type: Function, required: true },
})
</script>

<template>
    <div class="rounded-xl border border-border bg-bg p-4">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Engagement</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">
                    Jobs & Ehrenamt
                </h2>

                <p class="text-xs text-secondary">
                    Veröffentliche bezahlte Stellen, Ehrenamtsrollen und konkrete Aufgaben direkt auf der Jobs-Seite.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">
                    {{ club.jobs?.length || 0 }} Einträge
                </span>
                <button
                    v-if="club.can_manage_jobs"
                    type="button"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                    @click="openJobModal(club)"
                >
                    Eintrag hinzufügen
                </button>
            </div>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            <article
                v-for="job in club.jobs || []"
                :key="job.id"
                class="rounded-lg border border-border bg-card p-4"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <span
                            class="rounded-full px-2 py-1 text-xs"
                            :class="job.type === 'volunteer'
                                ? 'bg-air-green/15 text-air-green'
                                : 'bg-air-blue/15 text-air-blue'"
                        >
                            {{ job.type === 'volunteer' ? 'Ehrenamt' : 'Beruf' }}
                        </span>

                        <h3 class="mt-3 font-semibold text-primary">
                            {{ job.title }}
                        </h3>

                        <p class="mt-1 break-words text-xs text-secondary">
                            {{ job.location || 'Ort offen' }} · {{ job.workload || 'Umfang offen' }} · {{ job.employment_type || 'Art offen' }}
                        </p>
                    </div>

                    <span
                        class="rounded-full px-2 py-1 text-xs"
                        :class="job.is_published ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'"
                    >
                        {{ job.is_published ? 'Online' : 'Entwurf' }}
                    </span>
                </div>

                <p class="mt-3 line-clamp-3 text-sm text-secondary">
                    {{ job.description }}
                </p>

                <div
                    v-if="job.contact_email || job.application_url"
                    class="mt-3 flex flex-wrap gap-2 text-xs"
                >
                    <a
                        v-if="job.contact_email"
                        :href="`mailto:${job.contact_email}`"
                        class="rounded-full border border-border px-3 py-1 text-secondary hover:bg-muted hover:text-primary"
                    >
                        Kontakt: {{ job.contact_email }}
                    </a>
                    <a
                        v-if="job.application_url"
                        :href="job.application_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-full border border-air-blue/30 px-3 py-1 text-air-blue hover:bg-air-blue/10"
                    >
                        Bewerbungslink prüfen
                    </a>
                </div>

                <div v-if="club.can_manage_jobs" class="mt-4 grid grid-cols-2 gap-2 sm:flex">
                    <button
                        type="button"
                        class="rounded border border-border px-3 py-2 text-sm text-primary hover:bg-muted"
                        @click="editJob(club, job)"
                    >
                        Bearbeiten
                    </button>

                    <button
                        type="button"
                        class="rounded bg-error px-3 py-2 text-sm text-white"
                        @click="deleteJob(job)"
                    >
                        Löschen
                    </button>
                </div>
            </article>

            <div
                v-if="!club.jobs?.length"
                class="rounded-lg border border-dashed border-border bg-card p-5 text-sm text-secondary lg:col-span-2"
            >
                <p class="font-semibold text-primary">Noch keine Stellen veröffentlicht.</p>
                <p class="mt-1">
                    Lege den ersten Eintrag an, damit interessierte Menschen passende Jobs oder Ehrenamtsrollen finden.
                </p>
                <button
                    v-if="club.can_manage_jobs"
                    type="button"
                    class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                    @click="openJobModal(club)"
                >
                    Ersten Eintrag erstellen
                </button>
            </div>
        </div>
    </div>
</template>


