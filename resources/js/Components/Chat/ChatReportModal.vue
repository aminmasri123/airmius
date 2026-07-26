<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const tx = (key, params = {}) => t(key, params)

defineProps({
    reportTarget: { type: Object, default: null },
    reportForm: { type: Object, required: true },
    closeReport: { type: Function, required: true },
    submitReport: { type: Function, required: true },
})
</script>

<template>
    <div
        v-if="reportTarget"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4"
        @click.self="closeReport"
    >
        <form class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-xl" @submit.prevent="submitReport">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ tx('chat.ui.report_message') }}</p>
                    <h2 class="mt-1 text-xl font-semibold text-primary">{{ tx('chat.ui.report_question') }}</h2>
                </div>
                <button type="button" class="rounded p-2 text-secondary hover:bg-muted" :aria-label="tx('chat.ui.close')" @click="closeReport">
                    <i class="las la-times"></i>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <select v-model="reportForm.reason" class="w-full rounded-lg border-border bg-inputBg text-primary">
                    <option value="insult">{{ tx('chat.ui.report_reasons.insult') }}</option>
                    <option value="bullying">{{ tx('chat.ui.report_reasons.bullying') }}</option>
                    <option value="hate">{{ tx('chat.ui.report_reasons.hate') }}</option>
                    <option value="sexual">{{ tx('chat.ui.report_reasons.sexual') }}</option>
                    <option value="violence">{{ tx('chat.ui.report_reasons.violence') }}</option>
                    <option value="threat">{{ tx('chat.ui.report_reasons.threat') }}</option>
                    <option value="image_rights">{{ tx('chat.ui.report_reasons.image_rights') }}</option>
                    <option value="spam">{{ tx('chat.ui.report_reasons.spam') }}</option>
                    <option value="other">{{ tx('chat.ui.report_reasons.other') }}</option>
                </select>
                <p v-if="reportForm.errors.reason" class="text-sm text-error">{{ reportForm.errors.reason }}</p>

                <textarea
                    v-model="reportForm.details"
                    rows="4"
                    class="w-full rounded-lg border-border bg-inputBg text-primary"
                    :placeholder="tx('chat.ui.report_details_placeholder')"
                />
                <p v-if="reportForm.errors.details" class="text-sm text-error">{{ reportForm.errors.details }}</p>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn" @click="closeReport">{{ tx('chat.ui.cancel') }}</button>
                <button type="submit" class="btn-primary" :disabled="reportForm.processing">
                    {{ tx('chat.ui.submit_report') }}
                </button>
            </div>
        </form>
    </div>
</template>
