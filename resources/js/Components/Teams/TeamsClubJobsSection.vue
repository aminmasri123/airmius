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
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ $t('teams_workspace.jobs.eyebrow') }}</p>
                <h2 class="mt-1 text-lg font-semibold text-primary">
                    {{ $t('teams_workspace.jobs.title') }}
                </h2>

                <p class="text-xs text-secondary">
                    {{ $t('teams_workspace.jobs.intro') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-muted px-3 py-1 text-xs text-secondary">
                    {{ $t('teams_workspace.jobs.count', { count: club.jobs?.length || 0 }) }}
                </span>
                <button
                    v-if="club.can_manage_jobs"
                    type="button"
                    class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                    @click="openJobModal(club)"
                >
                    {{ $t('teams_workspace.jobs.add') }}
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
                            {{ job.type === 'volunteer' ? $t('teams_workspace.jobs.volunteer') : $t('teams_workspace.jobs.professional') }}
                        </span>

                        <h3 class="mt-3 font-semibold text-primary">
                            {{ job.title }}
                        </h3>

                        <p class="mt-1 break-words text-xs text-secondary">
                            {{ job.location || $t('teams_workspace.jobs.location_open') }} · {{ job.workload || $t('teams_workspace.jobs.workload_open') }} · {{ job.employment_type || $t('teams_workspace.jobs.type_open') }}
                        </p>
                    </div>

                    <span
                        class="rounded-full px-2 py-1 text-xs"
                        :class="job.is_published ? 'bg-air-green/15 text-air-green' : 'bg-muted text-secondary'"
                    >
                        {{ job.is_published ? $t('teams_workspace.jobs.published') : $t('teams_workspace.jobs.draft') }}
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
                        {{ $t('teams_workspace.jobs.contact', { email: job.contact_email }) }}
                    </a>
                    <a
                        v-if="job.application_url"
                        :href="job.application_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-full border border-air-blue/30 px-3 py-1 text-air-blue hover:bg-air-blue/10"
                    >
                        {{ $t('teams_workspace.jobs.check_application') }}
                    </a>
                </div>

                <div v-if="club.can_manage_jobs" class="mt-4 grid grid-cols-2 gap-2 sm:flex">
                    <button
                        type="button"
                        class="rounded border border-border px-3 py-2 text-sm text-primary hover:bg-muted"
                        @click="editJob(club, job)"
                    >
                        {{ $t('teams_workspace.jobs.edit') }}
                    </button>

                    <button
                        type="button"
                        class="rounded bg-error px-3 py-2 text-sm text-white"
                        @click="deleteJob(job)"
                    >
                        {{ $t('teams_workspace.jobs.delete') }}
                    </button>
                </div>
            </article>

            <div
                v-if="!club.jobs?.length"
                class="rounded-lg border border-dashed border-border bg-card p-5 text-sm text-secondary lg:col-span-2"
            >
                <p class="font-semibold text-primary">{{ $t('teams_workspace.jobs.empty_title') }}</p>
                <p class="mt-1">
                    {{ $t('teams_workspace.jobs.empty_body') }}
                </p>
                <button
                    v-if="club.can_manage_jobs"
                    type="button"
                    class="mt-4 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                    @click="openJobModal(club)"
                >
                    {{ $t('teams_workspace.jobs.create_first') }}
                </button>
            </div>
        </div>
    </div>
</template>

