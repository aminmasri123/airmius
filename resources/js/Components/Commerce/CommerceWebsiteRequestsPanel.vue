<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
    clubs: { type: Array, default: () => [] },
    websiteRequests: { type: Array, default: () => [] },
    websiteRequestModal: { type: Boolean, default: false },
    websiteForm: { type: Object, required: true },
})

const emit = defineEmits([
    'open-website-request-modal',
    'update:websiteRequestModal',
    'store-website-request',
])

const { t } = useI18n()
</script>

<template>
    <article class="surface-card p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">{{ t('agency.club_website') }}</h2>
                <p class="mt-1 text-sm text-secondary">{{ t('agency.club_scope') }}</p>
            </div>
            <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" @click="emit('open-website-request-modal')">
                {{ t('agency.request_website') }}
            </button>
        </div>
        <p class="mt-4 text-sm text-secondary">{{ t('agency.request_count', { count: websiteRequests.length }) }}</p>

        <div v-if="websiteRequestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <form class="relative max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-2xl" @submit.prevent="emit('store-website-request')">
                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-bg text-secondary transition hover:text-primary"
                    :aria-label="t('agency.close')"
                    @click="emit('update:websiteRequestModal', false)"
                >
                    <i class="las la-times text-xl"></i>
                </button>
                <div class="mb-4 pr-12">
                    <p class="text-xs font-semibold uppercase text-air-blue">{{ t('agency.club') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-primary">{{ t('agency.build_website') }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ t('agency.form_intro') }}</p>
                </div>
                <div class="grid gap-3">
                    <select v-model="websiteForm.club_id" class="rounded-lg border-border bg-inputBg text-sm text-primary">
                        <option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option>
                    </select>
                    <input v-model="websiteForm.domain" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('agency.domain')">
                    <textarea v-model="websiteForm.goals" rows="3" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('agency.goals')"></textarea>
                    <textarea v-model="websiteForm.notes" rows="2" class="rounded-lg border-border bg-inputBg text-sm text-primary" :placeholder="t('agency.notes')"></textarea>
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-border bg-bg p-3 text-sm font-semibold text-primary">
                        <input v-model="websiteForm.accepted_privacy" type="checkbox" class="mt-0.5 rounded border-border bg-inputBg text-air-blue focus:ring-air-blue">
                        <span>{{ t('agency.privacy_accept') }}</span>
                    </label>
                    <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="websiteForm.processing || !websiteForm.accepted_privacy">{{ t('agency.send') }}</button>
                </div>
            </form>
        </div>
    </article>
</template>
