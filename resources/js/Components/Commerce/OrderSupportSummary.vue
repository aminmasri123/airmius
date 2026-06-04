<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    support: { type: Object, default: null },
    dense: { type: Boolean, default: false },
})

const { locale, t } = useI18n()

const hasSupport = computed(() => Boolean(props.support))

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(locale.value || 'de-DE', { dateStyle: 'medium' }).format(new Date(value))
    : '-'

const returnStatusLabel = (status) => ({
    requested: t('Rücksendung angefragt'),
    approved: t('Rücksendung freigegeben'),
    received: t('Rücksendung eingegangen'),
    refunded: t('Erstattet'),
    rejected: t('Abgelehnt'),
}[status] || status)

const returnBlockedLabel = (reason) => ({
    payment_pending: t('Nach Zahlung verfügbar.'),
    digital_or_service: t('Digitales Angebot oder Service: keine Rücksendung.'),
    shipping_pending: t('Nach Zustellung kannst du eine Rücksendung anfragen.'),
    return_already_requested: t('Rücksendung läuft bereits.'),
    return_window_closed: t('Rücksendefrist abgelaufen oder ausgeschlossen.'),
    not_marketplace: t('Bei Problemen kannst du Airmius informieren.'),
}[reason] || t('Aktuell keine Rücksendung möglich.'))

const supportHint = computed(() => {
    const support = props.support || {}

    if (support.next_step === 'issue_under_review') {
        return t('Airmius prüft deinen Problemfall.')
    }

    if (support.next_step === 'return_under_review') {
        return returnStatusLabel(support.latest_return_request_status)
    }

    if (support.can_request_return) {
        return support.return_deadline
            ? t('Rücksendung möglich bis {date}.', { date: formatDate(support.return_deadline) })
            : t('Rücksendung ist möglich.')
    }

    if (support.can_report_issue) {
        return returnBlockedLabel(support.return_blocked_reason)
    }

    return t('Aktuell ist keine Aktion nötig.')
})
</script>

<template>
    <div
        v-if="hasSupport"
        class="rounded-lg border border-border bg-bg text-left"
        :class="dense ? 'p-3 text-xs' : 'p-4 text-sm'"
    >
        <p class="text-xs font-semibold uppercase text-secondary">{{ t('Support & Rückgabe') }}</p>
        <p class="text-secondary" :class="dense ? 'mt-1' : 'mt-2'">{{ supportHint }}</p>
        <p v-if="support.return_deadline" class="mt-1 text-secondary">
            {{ t('Rückgabefrist:') }} {{ formatDate(support.return_deadline) }}
        </p>
    </div>
</template>

