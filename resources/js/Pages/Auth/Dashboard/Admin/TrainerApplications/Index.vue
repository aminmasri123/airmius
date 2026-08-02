<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ layout: AppLayout })

const props = defineProps({
    applications: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const { t, te, locale, messages } = useI18n({ useScope: 'global' })
const notice = ref(null)
const forms = reactive(Object.fromEntries(props.applications.map((application) => [application.id, {
    review_notes: application.review_notes || '',
    processing: false,
}])))

const tAuto = (value) => {
    const source = String(value || '').trim()
    if (!source || locale.value === 'de') return source

    const dictionary = messages.value?.[locale.value]?.auto || {}
    if (dictionary[source]) return dictionary[source]

    return te(source) ? t(source) : source
}

const pendingCount = computed(() => props.applications.filter((application) => application.status === 'pending').length)

const statusLabel = (status) => ({
    pending: tAuto('Wartet auf Prüfung'),
    approved: tAuto('Freigegeben'),
    rejected: tAuto('Abgelehnt'),
}[status] || status)

const statusClass = (status) => ({
    pending: 'border-warning/30 bg-warning/10 text-warning',
    approved: 'border-success/30 bg-success/10 text-success',
    rejected: 'border-error/30 bg-error/10 text-error',
}[status] || 'border-border bg-muted text-secondary')

const dateLabel = (value) => value
    ? new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'de-DE', { dateStyle: 'medium' }).format(new Date(value))
    : '-'

const submit = (application, action) => {
    const form = forms[application.id]
    form.processing = true
    notice.value = null

    router.put(route(`admin.trainer-applications.${action}`, application.id), {
        review_notes: form.review_notes,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = { type: 'success', message: page.props.flash?.success || tAuto('Änderung gespeichert.') }
        },
        onError: () => {
            notice.value = { type: 'error', message: tAuto('Die Änderung konnte nicht gespeichert werden.') }
        },
        onFinish: () => {
            form.processing = false
        },
    })
}
</script>

<template>
    <Head :title="tAuto('Traineranträge')" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-primary">{{ tAuto('Traineranträge') }}</h1>
                    <p class="mt-1 text-sm text-secondary">
                        {{ tAuto('Trainerzugänge sind sofort aktiv und werden hier nachträglich geprüft.') }}
                    </p>
                </div>
                <span class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm font-semibold text-primary">
                    {{ pendingCount }} {{ tAuto('offen') }}
                </span>
            </div>
        </section>

        <div
            v-if="notice"
            class="rounded-lg border px-4 py-3 text-sm"
            :class="notice.type === 'success' ? 'border-success/30 bg-success/10 text-success' : 'border-error/30 bg-error/10 text-error'"
        >
            {{ notice.message }}
        </div>

        <section v-if="applications.length" class="space-y-3">
            <article v-for="application in applications" :key="application.id" class="surface-card p-5">
                <div class="grid gap-5 lg:grid-cols-[1fr_340px]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-primary">{{ application.user?.name || '-' }}</h2>
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="statusClass(application.status)">
                                {{ statusLabel(application.status) }}
                            </span>
                        </div>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-secondary">{{ tAuto('E-Mail') }}</dt>
                                <dd class="text-primary">{{ application.user?.email || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-secondary">{{ tAuto('Beantragt am') }}</dt>
                                <dd class="text-primary">{{ dateLabel(application.requested_at) }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-secondary">{{ tAuto('Nachricht') }}</dt>
                                <dd class="whitespace-pre-wrap text-primary">{{ application.message || '-' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-secondary">{{ tAuto('Sportarten & Schwerpunkte') }}</dt>
                                <dd class="whitespace-pre-wrap text-primary">
                                    {{ application.application_data?.sports || '-' }}
                                    <span v-if="application.application_data?.specialties">
                                        · {{ application.application_data.specialties }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="space-y-3 rounded-lg border border-border bg-bg p-4">
                        <label class="block">
                            <span class="text-sm font-semibold text-primary">{{ tAuto('Prüfnotiz') }}</span>
                            <textarea v-model="forms[application.id].review_notes" class="input min-h-24" :placeholder="tAuto('Optional bei Freigabe, erforderlich bei Ablehnung')"></textarea>
                        </label>
                        <div v-if="application.status === 'pending'" class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="forms[application.id].processing" @click="submit(application, 'approve')">
                                {{ tAuto('Freigeben') }}
                            </button>
                            <button type="button" class="rounded-lg border border-error px-4 py-2 text-sm font-semibold text-error disabled:opacity-50" :disabled="forms[application.id].processing" @click="submit(application, 'reject')">
                                {{ tAuto('Ablehnen') }}
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section v-else class="surface-card p-8 text-center text-sm text-secondary">
            {{ tAuto('Keine Traineranträge vorhanden.') }}
        </section>
    </div>
</template>
