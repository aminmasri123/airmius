<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import translations from '@/i18n/sepaFeeRechargeLocalization.json'
import { createFeeRechargeState } from './feeRechargeState'
const props = defineProps({ clubId: Number, batchId: Number, item: Object, userId: Number, canManage: Boolean,
    disabled: Boolean, draftsAvailable: Boolean, approvalsAvailable: Boolean, voidsAvailable: Boolean, creditsAvailable: Boolean })
const emit = defineEmits(['saved'])
const { locale } = useI18n()
const c = key => (translations[locale.value] || translations.en)[key] || key
const money = cents => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }).format(cents / 100)
const proposals = computed(() => props.item.settlement?.fee_recharges || [])
const current = computed(() => {
    const latest = [...(props.item.settlement?.fee_corrections || [])].sort((a, b) => b.revision - a.revision)[0]
    return { revision: latest?.revision || 0, amount: latest ? latest.amount_cents : Math.round(Number(props.item.settlement.fee_entry.amount) * 100) }
})
const hasActive = computed(() => proposals.value.some(p => p.active_settlement_id != null))
const { state, select, submit, reset } = createFeeRechargeState({ current: () => current.value,
    uuid: () => window.crypto.randomUUID(), errorText: () => c('error'), onSaved: () => emit('saved'),
    post: (path, body) => window.axios.post(`/api/v1/clubs/${props.clubId}/sepa-batches/${props.batchId}/items/${props.item.id}/${path}`, body),
})
const selected = computed(() => proposals.value.find(p => p.id === state.action?.proposalId))
const selectedVoid = computed(() => selected.value?.void_requests?.find(v => v.id === state.action?.voidId))
const selectedCredit = computed(() => selected.value?.credit_requests?.find(v => v.id === state.action?.creditId))
const blocked = computed(() => state.busy || props.disabled || !!state.request)
const needsReason = computed(() => ['propose', 'cancel', 'requestVoid', 'withdrawVoid', 'requestCredit', 'withdrawCredit'].includes(state.action?.kind))
const secondPerson = id => Number.isInteger(props.userId) && id != null && props.userId !== id
watch(() => props.item, reset)
function save() { if (props.canManage && !props.disabled) return submit() }
</script>

<template>
    <section class="mt-3 space-y-3 border-t border-border pt-3" :aria-busy="state.busy">
        <h4 class="font-semibold">{{ c('title') }}</h4>
        <p>{{ c('help') }}</p>
        <p v-if="!proposals.length">{{ c('empty') }}</p>
        <article v-for="p in proposals" :key="p.id" class="space-y-1 rounded border border-border p-3 break-words">
            <p class="font-semibold">#{{ p.id }} · {{ c(`status_${p.status}`) }} · {{ money(p.amount_cents) }}</p>
            <p>{{ c('recipient') }}: {{ p.member?.name || c('unavailable') }} (#{{ p.member_id }})</p>
            <p>{{ c('date') }}: {{ p.due_date?.slice(0, 10) }} · {{ c('source') }}: {{ item.invoice?.number || item.number || p.source_invoice_id }}</p>
            <p>{{ c('feeSnapshot') }}: {{ money(p.fee_amount_cents) }} · {{ c('revision') }} {{ p.fee_revision }}</p>
            <p class="whitespace-pre-wrap">{{ c('basis') }}: {{ p.basis }}</p><p class="whitespace-pre-wrap">{{ c('reason') }}: {{ p.reason }}</p>
            <p v-if="p.cancellation_reason">{{ c('cancel') }}: {{ p.cancellation_reason }}</p>
            <p v-if="p.invoice">{{ c('invoice') }}: {{ p.invoice.number }} · {{ c(`invoice_${p.invoice.status}`) }} · {{ money(Math.round(Number(p.invoice.amount) * 100)) }}</p>
            <p v-if="p.revenue_account">{{ c('account') }}: {{ p.revenue_account }}</p>
            <p v-if="p.review_required" role="status" class="font-semibold">{{ c('review') }}</p>
            <p v-if="p.status === 'draft' && p.fee_revision !== current.revision" role="status">{{ c('stale') }}</p>
            <div v-if="canManage" class="flex flex-wrap gap-2">
                <button v-if="p.status === 'draft' && draftsAvailable" type="button" :disabled="blocked" @click="select('cancel', p.id)">{{ c('cancel') }}</button>
                <button v-if="p.status === 'draft' && approvalsAvailable && secondPerson(p.proposed_by)" type="button" :disabled="blocked || p.fee_revision !== current.revision" @click="select('approve', p.id)">{{ c('approve') }}</button>
                <button v-if="p.status === 'approved' && voidsAvailable && !p.void_requests?.some(v => v.status === 'pending')" type="button" :disabled="blocked || (p.invoice && !['open', 'overdue'].includes(p.invoice.status))" @click="select('requestVoid', p.id)">{{ c('requestVoid') }}</button>
                <button v-if="p.status === 'approved' && creditsAvailable && !p.credit_requests?.some(v => v.status === 'pending')" type="button" :disabled="blocked" @click="select('requestCredit', p.id)">{{ c('requestCredit') }}</button>
            </div>
            <ol class="space-y-2">
                <li v-for="v in p.void_requests || []" :key="v.id" class="border-l border-border pl-2">
                    <p>{{ c('voidRequest') }} #{{ v.id }} · {{ c(`void_${v.status}`) }} · {{ v.reason }}</p>
                    <p v-if="v.review_reason">{{ v.review_reason }}</p>
                    <div v-if="canManage && voidsAvailable && v.status === 'pending'" class="flex flex-wrap gap-2">
                        <button v-if="secondPerson(v.requested_by)" type="button" :disabled="blocked" @click="select('approveVoid', p.id, v.id)">{{ c('approveVoid') }}</button>
                        <button type="button" :disabled="blocked" @click="select('withdrawVoid', p.id, v.id)">{{ c('withdrawVoid') }}</button>
                    </div>
                </li>
            </ol>
            <ol class="space-y-2">
                <li v-for="credit in p.credit_requests || []" :key="credit.id" class="border-l border-border pl-2">
                    <p>{{ c('creditRequest') }} #{{ credit.id }} · {{ c(`credit_${credit.status}`) }} · {{ money(credit.amount_cents) }}</p>
                    <p class="whitespace-pre-wrap">{{ c('reason') }}: {{ credit.reason }}</p>
                    <p v-if="credit.credit_note_number">{{ c('creditNote') }}: {{ credit.credit_note_number }}</p>
                    <p v-if="credit.refund_due_cents != null">{{ c('refundDue') }}: {{ money(credit.refund_due_cents) }}</p>
                    <p v-if="credit.refund_reference">{{ c('refundEvidence') }}: {{ credit.refund_booked_on?.slice(0, 10) }} · {{ credit.refund_reference }}</p>
                    <a v-if="credit.credit_note_number" class="underline" target="_blank" rel="noopener" :href="`/api/v1/clubs/${clubId}/sepa-batches/${batchId}/items/${item.id}/fee-recharges/${p.id}/credit-requests/${credit.id}/document`">{{ c('creditDocument') }}</a>
                    <div v-if="canManage && creditsAvailable && credit.status === 'pending'" class="flex flex-wrap gap-2">
                        <button v-if="secondPerson(credit.requested_by)" type="button" :disabled="blocked" @click="select('approveCredit', p.id, credit.id)">{{ c('approveCredit') }}</button>
                        <button type="button" :disabled="blocked" @click="select('withdrawCredit', p.id, credit.id)">{{ c('withdrawCredit') }}</button>
                    </div>
                    <button v-if="canManage && creditsAvailable && credit.status === 'issued' && credit.refund_due_cents > 0" type="button" :disabled="blocked" @click="select('refund', p.id, credit.id)">{{ c('refund') }}</button>
                </li>
            </ol>
        </article>
        <button v-if="canManage && draftsAvailable && !hasActive && current.amount > 0" type="button" :disabled="blocked" class="rounded border border-border px-3 py-2" @click="select('propose')">{{ c('propose') }}</button>
        <form v-if="state.action && canManage" class="space-y-2 rounded border border-border p-3" @submit.prevent="save">
            <h5 class="font-semibold">{{ c(state.action.kind) }} <span v-if="selected">#{{ selected.id }} · {{ money(selected.amount_cents) }} · {{ selected.member?.name || c('unavailable') }}</span></h5>
            <div v-if="selected" class="space-y-1 break-words">
                <p>{{ c('date') }}: {{ selected.due_date?.slice(0, 10) }} · {{ c('source') }}: {{ item.invoice?.number || item.number || selected.source_invoice_id }}</p>
                <p class="whitespace-pre-wrap">{{ c('basis') }}: {{ selected.basis }}</p>
                <p class="whitespace-pre-wrap">{{ c('reason') }}: {{ selected.reason }}</p>
                <p v-if="selected.invoice">{{ c('invoice') }}: {{ selected.invoice.number }} · {{ c(`invoice_${selected.invoice.status}`) }}</p>
                <p v-if="selectedVoid" class="whitespace-pre-wrap">{{ c('voidRequest') }} #{{ selectedVoid.id }}: {{ selectedVoid.reason }}</p>
                <p v-if="selectedCredit" class="whitespace-pre-wrap">{{ c('creditRequest') }} #{{ selectedCredit.id }}: {{ selectedCredit.reason }} · {{ money(selectedCredit.amount_cents) }}</p>
            </div>
            <p v-if="['requestVoid', 'approveVoid'].includes(state.action.kind)">{{ c('voidHelp') }}</p>
            <p v-if="state.action.kind === 'propose'">{{ c('source') }}: {{ item.number }} · {{ item.name }}</p>
            <fieldset :disabled="blocked" class="space-y-2">
                <template v-if="state.action.kind === 'propose'">
                    <label class="block">{{ c('amount') }}<input v-model="state.form.amount" inputmode="decimal" required class="block w-full rounded border border-border bg-inputBg"></label>
                    <label class="block">{{ c('date') }}<input v-model="state.form.date" type="date" required class="block max-w-full rounded border border-border bg-inputBg"></label>
                    <label class="block">{{ c('basis') }}<textarea v-model="state.form.basis" maxlength="2000" required class="block w-full rounded border border-border bg-inputBg" /></label>
                </template>
                <template v-if="state.action.kind === 'approve'">
                    <label class="block">{{ c('account') }}<input v-model="state.form.account" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" required class="block w-full rounded border border-border bg-inputBg"></label>
                    <p>{{ c('accountHelp') }}</p>
                    <label class="flex gap-2"><input v-model="state.basisConfirmed" type="checkbox" required>{{ c('basisConfirm') }}</label>
                </template>
                <template v-if="state.action.kind === 'refund'">
                    <p>{{ c('refundHelp') }}</p>
                    <label class="block">{{ c('refundDate') }}<input v-model="state.form.date" type="date" required class="block max-w-full rounded border border-border bg-inputBg"></label>
                    <label class="block">{{ c('refundReference') }}<input v-model="state.form.reference" maxlength="180" required class="block w-full rounded border border-border bg-inputBg"></label>
                    <label class="block">{{ c('financeEntry') }}<input v-model="state.form.financeEntryId" inputmode="numeric" pattern="[0-9]*" class="block w-full rounded border border-border bg-inputBg"></label>
                    <p>{{ c('financeEntryHelp') }}</p>
                </template>
                <label v-if="needsReason" class="block">{{ c('reason') }}<textarea v-model="state.form.reason" maxlength="2000" required class="block w-full rounded border border-border bg-inputBg" /></label>
            </fieldset>
            <p v-if="state.phase === 'uncertain'" role="status">{{ c('uncertain') }}</p>
            <p v-if="state.phase === 'saved'" role="status">{{ c('saved') }}</p>
            <template v-if="state.phase !== 'saved'">
                <label class="flex gap-2"><input v-model="state.confirmed" type="checkbox" required :disabled="state.busy || disabled">{{ c('confirm') }}</label>
                <button type="submit" :disabled="state.busy || disabled || !state.confirmed" class="rounded border border-border px-3 py-2">{{ c(state.request ? 'retry' : state.action.kind) }}</button>
            </template>
            <button v-if="state.request" type="button" :disabled="state.busy || disabled" @click="emit('saved')">{{ c('reload') }}</button>
            <button v-else type="button" :disabled="state.busy || disabled" @click="reset">{{ c('close') }}</button>
            <p v-if="state.error" role="alert" class="text-error">{{ state.error }}</p>
        </form>
    </section>
</template>
