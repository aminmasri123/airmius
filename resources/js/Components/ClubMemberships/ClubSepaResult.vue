<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import translations from '@/i18n/sepaBatchLocalization.json'

const props = defineProps({ item: Object, busy: Boolean, canManage: Boolean, userId: Number })
const emit = defineEmits(['record'])
const { locale } = useI18n()
const c = key => (translations[locale.value] || translations.en)[key] || key
const action = ref(''), bookedOn = ref(''), reference = ref(''), reason = ref(''), fee = ref(0), payment = ref(''), confirmed = ref(false)
const result = computed(() => props.item.settlement)
const title = computed(() => c({ settle: 'settleItem', return: 'returnItem', retry: 'retryItem' }[action.value]))
function choose(value) { action.value = value; confirmed.value = false; bookedOn.value = ''; reference.value = ''; reason.value = ''; fee.value = 0; payment.value = '' }
function submit() {
    const data = { confirmed: confirmed.value }
    if (action.value !== 'retry') { data.booked_on = bookedOn.value; data.reference = reference.value }
    if (action.value === 'settle' && payment.value !== 'new') data.payment_id = Number(payment.value)
    if (action.value !== 'settle') data.reason = reason.value
    if (action.value === 'return') data.fee_cents = Math.round(Number(fee.value) * 100)
    emit('record', action.value, data)
}
</script>

<template>
    <div class="my-2 rounded border border-border p-3">
        <p v-if="result">{{ c(`result_${result.status}`) }} · {{ result.settled_on?.slice(0, 10) }} {{ result.settlement_reference }}</p>
        <p v-if="result?.returned_on">{{ result.returned_on.slice(0, 10) }} · {{ result.return_reference }} · {{ result.return_reason }}</p>
        <p v-if="result?.return_fee_cents">{{ c('bankFee') }}: {{ (result.return_fee_cents / 100).toFixed(2) }} · {{ c('feeHelp') }}</p>
        <p v-if="result?.retry_authorized_at">{{ c('retryAllowed') }}</p>
        <template v-if="canManage">
            <p class="text-sm text-secondary">{{ c('bankHelp') }}</p>
            <div class="my-2 flex flex-wrap gap-2">
                <button v-if="!result" type="button" :disabled="busy" class="rounded border border-border px-3 py-2" @click="choose('settle')">{{ c('settleItem') }}</button>
                <button v-if="result?.status !== 'returned'" type="button" :disabled="busy" class="rounded border border-border px-3 py-2" @click="choose('return')">{{ c('returnItem') }}</button>
                <button v-if="result?.status === 'returned' && !result.retry_authorized_at" type="button" :disabled="busy || result.returned_by === userId" class="rounded border border-border px-3 py-2" @click="choose('retry')">{{ c('retryItem') }}</button>
            </div>
            <p v-if="result?.status === 'returned' && !result.retry_authorized_at && result.returned_by === userId" class="text-sm">{{ c('secondPerson') }}</p>
            <form v-if="action" class="space-y-2" @submit.prevent="submit">
                <fieldset :disabled="busy" class="space-y-2">
                    <legend>{{ title }}</legend>
                    <template v-if="action !== 'retry'">
                        <label class="block">{{ c('bookedOn') }} <input v-model="bookedOn" type="date" required class="rounded border border-border bg-inputBg"></label>
                        <label class="block">{{ c('bankReference') }} <input v-model="reference" maxlength="180" required class="rounded border border-border bg-inputBg"></label>
                    </template>
                    <label v-if="action === 'settle'" class="block">{{ c('paymentChoice') }}
                        <select v-model="payment" required class="rounded border border-border bg-inputBg">
                            <option value="" disabled>{{ c('paymentChoice') }}</option>
                            <option value="new">{{ c('newPayment') }}</option>
                            <option v-for="entry in item.payment_options" :key="entry.id" :value="String(entry.id)">{{ c('existingPayment') }} #{{ entry.id }} · {{ entry.amount }} EUR · {{ entry.paid_at }} · {{ entry.reference }}</option>
                        </select>
                    </label>
                    <p v-if="action === 'return' && !result?.payment_id" class="text-sm text-secondary">{{ c('returnHelp') }}</p>
                    <label v-if="action !== 'settle'" class="block">{{ c(action === 'return' ? 'returnReason' : 'reason') }} <textarea v-model="reason" required maxlength="2000" class="block w-full rounded border border-border bg-inputBg"></textarea></label>
                    <template v-if="action === 'return'">
                        <label class="block">{{ c('bankFee') }} <input v-model="fee" type="number" min="0" max="999999.99" step="0.01" required class="rounded border border-border bg-inputBg"></label>
                        <p class="text-sm text-secondary">{{ c('feeHelp') }}</p>
                    </template>
                    <label class="flex gap-2"><input v-model="confirmed" type="checkbox" required> {{ c(action === 'retry' ? 'retryConfirm' : 'bankConfirm') }}</label>
                    <button type="submit" :disabled="!confirmed" class="rounded border border-border px-3 py-2">{{ title }}</button>
                </fieldset>
            </form>
        </template>
    </div>
</template>
