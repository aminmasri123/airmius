<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    event: { type: Object, required: true },
    participantStatuses: { type: Array, default: () => [] },
    currentParticipantStatus: { type: String, default: null },
    currentParticipantResponse: { type: Object, default: () => ({}) },
    participationPolicy: { type: Object, default: () => ({}) },
    participationForm: { type: Object, required: true },
    needsReason: { type: Boolean, default: false },
    deadlineLabel: { type: String, default: null },
    participationLocked: { type: Boolean, default: false },
    statusLabels: { type: Object, required: true },
    capacityLabel: { type: String, required: true },
    yesCount: { type: Number, default: 0 },
    lateCount: { type: Number, default: 0 },
    maybeCount: { type: Number, default: 0 },
    noCount: { type: Number, default: 0 },
    isFullForYes: { type: Boolean, default: false },
    setStatus: { type: Function, required: true },
})

const { locale } = useI18n()

const copy = {
    de: {
        title: 'Teilnahme',
        required: 'Antwort erforderlich',
        optional: 'Antwort optional',
        deadline: 'Frist',
        expired: 'Rückmeldefrist abgelaufen',
        reasonLabel: 'Kurzer Grund',
        reasonPlaceholder: 'z. B. krank, später da, beruflich verhindert',
        saveReason: 'Antwort speichern',
        current: 'Aktuelle Antwort',
        noParticipants: 'Noch keine Teilnehmer.',
        full: 'Dieses Event ist voll',
        cancelled: 'Event ist abgesagt',
        participants: 'Teilnehmer',
        yes: 'Zusagen',
        maybe: 'Vielleicht',
        no: 'Absagen',
        late: 'Verspätet',
    },
    en: {
        title: 'Attendance',
        required: 'Response required',
        optional: 'Response optional',
        deadline: 'Deadline',
        expired: 'Response deadline expired',
        reasonLabel: 'Short reason',
        reasonPlaceholder: 'e.g. ill, arriving later, work conflict',
        saveReason: 'Save response',
        current: 'Current response',
        noParticipants: 'No participants yet.',
        full: 'This event is full',
        cancelled: 'Event is cancelled',
        participants: 'Participants',
        yes: 'Yes',
        maybe: 'Maybe',
        no: 'No',
        late: 'Late',
    },
    fr: {
        title: 'Participation',
        required: 'Réponse requise',
        optional: 'Réponse optionnelle',
        deadline: 'Date limite',
        expired: 'Date limite de réponse dépassée',
        reasonLabel: 'Motif court',
        reasonPlaceholder: 'ex. malade, en retard, empêchement professionnel',
        saveReason: 'Enregistrer la réponse',
        current: 'Réponse actuelle',
        noParticipants: 'Aucun participant.',
        full: 'Cet événement est complet',
        cancelled: 'Événement annulé',
        participants: 'Participants',
        yes: 'Présents',
        maybe: 'Peut-être',
        no: 'Absents',
        late: 'En retard',
    },
    ar: {
        title: 'الحضور',
        required: 'الرد مطلوب',
        optional: 'الرد اختياري',
        deadline: 'الموعد النهائي',
        expired: 'انتهت مهلة الرد',
        reasonLabel: 'سبب مختصر',
        reasonPlaceholder: 'مثلاً: مريض، سأتأخر، التزام في العمل',
        saveReason: 'حفظ الرد',
        current: 'الرد الحالي',
        noParticipants: 'لا يوجد مشاركون بعد.',
        full: 'هذا الموعد ممتلئ',
        cancelled: 'تم إلغاء الموعد',
        participants: 'المشاركون',
        yes: 'موافق',
        maybe: 'ربما',
        no: 'رفض',
        late: 'متأخر',
    },
}

const labels = computed(() => copy[locale.value] || copy.de)
const responsePill = computed(() => props.currentParticipantStatus
    ? (props.statusLabels[props.currentParticipantStatus] || props.currentParticipantStatus)
    : '-')
</script>

<template>
    <aside class="space-y-4">
        <section class="rounded-lg border border-border bg-card p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary">{{ labels.title }}</h2>
                    <p class="mt-1 text-sm text-secondary">{{ capacityLabel }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="rounded-full bg-muted px-3 py-1 text-xs font-bold text-secondary">
                        {{ participationPolicy.response_required ? labels.required : labels.optional }}
                    </span>
                    <span v-if="deadlineLabel" class="rounded-full bg-muted px-3 py-1 text-xs font-bold text-secondary">
                        {{ labels.deadline }}: {{ deadlineLabel }}
                    </span>
                </div>
            </div>

            <p v-if="participationLocked" class="mt-3 rounded-lg border border-amber-400/30 bg-amber-500/10 p-3 text-sm text-amber-100">
                {{ labels.expired }}
            </p>

            <div class="mt-4 grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                <div class="rounded-lg bg-success/10 p-3 text-success">
                    <p class="text-2xl font-bold">{{ yesCount }}</p>
                    <p class="text-xs font-semibold">{{ labels.yes }}</p>
                </div>
                <div class="rounded-lg bg-amber-500/10 p-3 text-amber-200">
                    <p class="text-2xl font-bold">{{ lateCount }}</p>
                    <p class="text-xs font-semibold">{{ labels.late }}</p>
                </div>
                <div class="rounded-lg bg-air-blue/10 p-3 text-air-blue">
                    <p class="text-2xl font-bold">{{ maybeCount }}</p>
                    <p class="text-xs font-semibold">{{ labels.maybe }}</p>
                </div>
                <div class="rounded-lg bg-error/10 p-3 text-error">
                    <p class="text-2xl font-bold">{{ noCount }}</p>
                    <p class="text-xs font-semibold">{{ labels.no }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button
                    v-for="status in participantStatuses"
                    :key="status"
                    class="min-h-12 rounded-lg border px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50"
                    :class="participationForm.status === status ? 'border-buttonPrimary bg-buttonPrimary text-buttonTextPrimary' : 'border-border text-primary hover:bg-muted'"
                    :disabled="event.status === 'cancelled' || participationLocked || (status === 'yes' && isFullForYes)"
                    :title="event.status === 'cancelled' ? labels.cancelled : status === 'yes' && isFullForYes ? labels.full : ''"
                    @click="setStatus(status)"
                >
                    {{ statusLabels[status] || status }}
                </button>
            </div>

            <div v-if="needsReason" class="mt-4 rounded-lg border border-border bg-inputBg p-3">
                <label class="text-sm font-bold text-primary" for="participation-reason">{{ labels.reasonLabel }}</label>
                <textarea
                    id="participation-reason"
                    v-model="participationForm.response_reason"
                    rows="3"
                    maxlength="1000"
                    class="mt-2 w-full rounded-lg border-border bg-card text-primary"
                    :placeholder="labels.reasonPlaceholder"
                ></textarea>
                <p v-if="participationForm.errors.response_reason" class="mt-1 text-sm text-error">
                    {{ participationForm.errors.response_reason }}
                </p>
                <button
                    type="button"
                    class="mt-3 w-full rounded-lg bg-buttonPrimary px-4 py-3 text-sm font-bold text-buttonTextPrimary disabled:opacity-60"
                    :disabled="participationForm.processing || !participationForm.response_reason?.trim()"
                    @click="setStatus(participationForm.status)"
                >
                    {{ labels.saveReason }}
                </button>
            </div>

            <p v-if="currentParticipantStatus" class="mt-3 rounded-lg bg-inputBg p-3 text-sm text-secondary">
                {{ labels.current }}:
                <span class="font-bold text-primary">{{ responsePill }}</span>
                <span v-if="currentParticipantResponse.reason"> - {{ currentParticipantResponse.reason }}</span>
            </p>

            <p v-if="participationForm.errors.status" class="mt-2 text-sm text-error">
                {{ participationForm.errors.status }}
            </p>
        </section>

        <section class="rounded-lg border border-border bg-card p-5">
            <h2 class="text-lg font-semibold text-primary">{{ labels.participants }}</h2>
            <div class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-1">
                <div v-for="participant in event.participants" :key="participant.id" class="flex items-center justify-between gap-3 rounded-lg bg-inputBg px-3 py-2 text-sm">
                    <span class="truncate font-medium text-primary">{{ participant.name }}</span>
                    <span class="shrink-0 rounded-full bg-muted px-2 py-1 text-xs text-secondary">
                        {{ statusLabels[participant.pivot.status] || participant.pivot.status }}
                    </span>
                </div>
                <p v-if="!event.participants?.length" class="text-sm text-secondary">{{ labels.noParticipants }}</p>
            </div>
        </section>
    </aside>
</template>

