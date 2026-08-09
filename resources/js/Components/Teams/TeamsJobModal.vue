<script setup>
import Modal from '@/Components/Modal.vue'

defineProps({
    editingJobId: {
        type: [Number, String, null],
        default: null,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    isSubmittingJob: {
        type: Boolean,
        default: false,
    },
    jobFormFor: {
        type: Function,
        required: true,
    },
    jobModalNotice: {
        type: Object,
        default: null,
    },
    selectedClub: {
        type: Object,
        default: null,
    },
    sports: {
        type: Array,
        default: () => [],
    },
    show: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['close', 'submit'])
</script>

<template>
    <Modal :show="show" max-width="xl" @close="$emit('close')">
        <form
            v-if="selectedClub"
            class="space-y-5"
            aria-labelledby="job-modal-title"
            @submit.prevent="$emit('submit', selectedClub)"
        >
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                    {{ selectedClub.name }}
                </p>
                <h2 id="job-modal-title" class="mt-1 text-lg font-bold text-primary">
                    {{ editingJobId ? $t('teams_workspace.jobs.modal.edit_title') : $t('teams_workspace.jobs.modal.create_title') }}
                </h2>
                <p class="mt-2 text-sm text-secondary">
                    {{ $t('teams_workspace.jobs.modal.intro') }}
                </p>
            </div>

            <div
                v-if="jobModalNotice"
                class="rounded-lg border px-4 py-3 text-sm"
                role="alert"
                :class="jobModalNotice.type === 'success'
                    ? 'border-success/30 bg-success/10 text-success'
                    : 'border-error/30 bg-error/10 text-error'"
            >
                {{ jobModalNotice.message }}
            </div>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">{{ $t('teams_workspace.jobs.modal.what') }}</h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('teams_workspace.jobs.modal.title') }} *</span>
                        <input
                            v-model="jobFormFor(selectedClub).title"
                            required
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('teams_workspace.jobs.modal.title_placeholder')"
                        >
                        <span v-if="errors.title" class="mt-1 block text-xs text-error">{{ errors.title }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('teams_workspace.jobs.modal.category') }}</span>
                        <select
                            v-model="jobFormFor(selectedClub).type"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        >
                            <option value="volunteer">{{ $t('teams_workspace.jobs.volunteer') }}</option>
                            <option value="professional">{{ $t('teams_workspace.jobs.modal.professional') }}</option>
                        </select>
                        <span v-if="errors.type" class="mt-1 block text-xs text-error">{{ errors.type }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('teams_workspace.jobs.modal.kind') }}</span>
                        <input
                            v-model="jobFormFor(selectedClub).employment_type"
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('teams_workspace.jobs.modal.kind_placeholder')"
                        >
                        <span v-if="errors.employment_type" class="mt-1 block text-xs text-error">{{ errors.employment_type }}</span>
                    </label>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('recruiting.criteria.sport') }}</span>
                        <select v-model="jobFormFor(selectedClub).sport_id" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">
                            <option value="">{{ $t('recruiting.criteria.no_sport') }}</option>
                            <option v-for="sport in sports" :key="sport.id" :value="sport.id">{{ sport.name }}</option>
                        </select>
                        <span v-if="errors.sport_id" class="mt-1 block text-xs text-error">{{ errors.sport_id }}</span>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('recruiting.criteria.minimum_experience') }}</span>
                        <select v-model="jobFormFor(selectedClub).minimum_experience_level" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary">
                            <option value="">{{ $t('recruiting.criteria.no_minimum') }}</option>
                            <option v-for="level in ['beginner', 'intermediate', 'advanced', 'expert', 'elite']" :key="level" :value="level">{{ $t(`recruiting.experience.${level}`) }}</option>
                        </select>
                        <span v-if="errors.minimum_experience_level" class="mt-1 block text-xs text-error">{{ errors.minimum_experience_level }}</span>
                    </label>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">{{ $t('teams_workspace.jobs.modal.framework') }}</h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('teams_workspace.jobs.modal.location') }}</span>
                        <input
                            v-model="jobFormFor(selectedClub).location"
                            autocomplete="address-line1"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('teams_workspace.jobs.modal.location_placeholder')"
                        >
                        <span v-if="errors.location" class="mt-1 block text-xs text-error">{{ errors.location }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('teams_workspace.jobs.modal.workload') }}</span>
                        <input
                            v-model="jobFormFor(selectedClub).workload"
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('teams_workspace.jobs.modal.workload_placeholder')"
                        >
                        <span v-if="errors.workload" class="mt-1 block text-xs text-error">{{ errors.workload }}</span>
                    </label>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-primary">{{ $t('teams_workspace.jobs.modal.description_contact') }}</h3>

                <label class="block">
                    <span class="text-xs font-semibold uppercase text-secondary">{{ $t('teams_workspace.jobs.modal.description') }} *</span>
                    <textarea
                        v-model="jobFormFor(selectedClub).description"
                        required
                        rows="5"
                        class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                        :placeholder="$t('teams_workspace.jobs.modal.description_placeholder')"
                    ></textarea>
                    <span v-if="errors.description" class="mt-1 block text-xs text-error">{{ errors.description }}</span>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('teams_workspace.jobs.modal.contact_email') }}</span>
                        <input
                            v-model="jobFormFor(selectedClub).contact_email"
                            type="email"
                            autocomplete="email"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('teams_workspace.jobs.modal.contact_placeholder')"
                        >
                        <span v-if="errors.contact_email" class="mt-1 block text-xs text-error">{{ errors.contact_email }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-secondary">{{ $t('teams_workspace.jobs.modal.application_url') }}</span>
                        <input
                            v-model="jobFormFor(selectedClub).application_url"
                            type="url"
                            inputmode="url"
                            autocomplete="url"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                            :placeholder="$t('teams_workspace.jobs.modal.application_url_placeholder')"
                        >
                        <span v-if="errors.application_url" class="mt-1 block text-xs text-error">{{ errors.application_url }}</span>
                        <span class="mt-1 block text-xs text-secondary">
                            {{ $t('teams_workspace.jobs.modal.application_url_hint') }}
                        </span>
                    </label>
                </div>
            </section>

            <label class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary">
                <input
                    v-model="jobFormFor(selectedClub).is_published"
                    type="checkbox"
                    class="mt-1 rounded border-border bg-inputBg"
                >
                <span>
                    <span class="block font-semibold">{{ $t('teams_workspace.jobs.modal.publish') }}</span>
                    <span class="block text-xs text-secondary">{{ $t('teams_workspace.jobs.modal.publish_hint') }}</span>
                </span>
            </label>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-primary hover:bg-muted"
                    @click="$emit('close')"
                >
                    {{ $t('teams_workspace.jobs.modal.cancel') }}
                </button>
                <button
                    class="rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-semibold text-buttonTextPrimary disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isSubmittingJob"
                    :aria-busy="isSubmittingJob"
                >
                    {{ isSubmittingJob ? $t('teams_workspace.jobs.modal.saving') : (editingJobId ? $t('teams_workspace.jobs.modal.update') : $t('teams_workspace.jobs.modal.create')) }}
                </button>
            </div>
        </form>
    </Modal>
</template>
