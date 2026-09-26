<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePage } from '@inertiajs/vue3'
import translations from '@/i18n/sepaBatchLocalization.json'
import ClubSepaResult from './ClubSepaResult.vue'
import ClubSepaFee from './ClubSepaFee.vue'
import ClubSepaReturnImport from './ClubSepaReturnImport.vue'

const props = defineProps({ clubId: { type: Number, required: true }, invoices: { type: Array, default: () => [] } })
const { locale } = useI18n()
const currentPage = usePage()
const userId = computed(() => currentPage.props.auth?.user?.id)
const c = (key) => (translations[locale.value] || translations.en)[key] || key
const base = computed(() => `/api/v1/clubs/${props.clubId}/sepa-batches`)
const money = (cents) => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }).format(cents / 100)
const open = ref(false), busy = ref(false), error = ref(''), feedback = ref(''), batches = ref([]), page = ref(1), more = ref(false)
const canManage = ref(false)
const noticesAvailable = ref(false)
const resultsAvailable = ref(false)
const feesAvailable = ref(false)
const feeCorrectionsAvailable = ref(false)
const feeRechargeDrafts = ref(false), feeRechargeApprovals = ref(false), feeRechargeVoids = ref(false)
const feeRechargeCredits = ref(false)
const selected = ref([]), collectionDate = ref(''), noticeDays = ref(14)
const eligible = computed(() => {
    const current = new Map(props.invoices.map(invoice => [invoice.id, invoice]))
    for (const batch of batches.value) for (const item of batch.items) {
        if (item.invoice) current.set(item.invoice.id, { ...current.get(item.invoice.id), ...item.invoice })
    }
    return [...current.values()].filter(invoice => ['open', 'overdue'].includes(invoice.status))
})
async function failure(e) {
    let data = e.response?.data
    if (data instanceof Blob) { try { data = JSON.parse(await data.text()) } catch { data = null } }
    error.value = data?.message || c('error')
}
async function fetchPage(append = false) {
    const response = await window.axios.get(`${base.value}?page=${append ? page.value + 1 : 1}`)
    canManage.value = response.data.can_manage === true
    noticesAvailable.value = response.data.notices_available === true
    resultsAvailable.value = response.data.results_available === true
    feesAvailable.value = response.data.fees_available === true
    feeCorrectionsAvailable.value = response.data.fee_corrections_available === true
    feeRechargeDrafts.value = response.data.fee_recharge_drafts_available === true
    feeRechargeApprovals.value = response.data.fee_recharge_approvals_available === true
    feeRechargeVoids.value = response.data.fee_recharge_voids_available === true
    feeRechargeCredits.value = response.data.fee_recharge_credits_available === true
    const result = response.data.data
    const rows = result.data.map(b => ({ ...b, mailConfirmed: false, notice: { sent_on: '', channel: 'email', reference: '', confirmed: false }, reason: '' }))
    batches.value = append ? [...batches.value, ...rows] : rows
    page.value = result.current_page
    more.value = Boolean(result.next_page_url)
}
async function load(append = false) {
    if (busy.value) return
    open.value = true; busy.value = true; error.value = ''
    try { await fetchPage(append) } catch (e) { await failure(e) } finally { busy.value = false }
}
async function act(path, data = {}, download = false) {
    if (busy.value) return
    busy.value = true; error.value = ''; feedback.value = ''
    try {
        const response = await window.axios.post(`${base.value}${path}`, data, download ? { responseType: 'blob' } : {})
        if (download) {
            const url = URL.createObjectURL(response.data)
            const link = document.createElement('a'); link.href = url; link.download = `sepa-${props.clubId}-${path.split('/')[1]}.xml`
            document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000)
        }
        await fetchPage(); selected.value = []; feedback.value = c('saved')
    } catch (e) { await failure(e) } finally { busy.value = false }
}
</script>

<template>
    <section class="my-5 rounded-lg border border-border p-4 text-primary" :aria-busy="busy">
        <h3 class="text-lg font-semibold">{{ c('title') }}</h3>
        <p class="my-2 text-sm text-secondary">{{ c('intro') }}</p>
        <button type="button" class="rounded border border-border px-3 py-2" :disabled="busy" @click="load()">{{ c(open ? 'refresh' : 'load') }}</button>
        <p v-if="error" class="mt-3 text-error" role="alert">{{ error }}</p>
        <p v-if="feedback" class="mt-3 text-success" role="status">{{ feedback }}</p>
        <template v-if="open">
            <form v-if="canManage" class="my-4 space-y-3" @submit.prevent="act('', { invoice_ids: selected, collection_date: collectionDate, notice_days: noticeDays })">
                <fieldset :disabled="busy" class="space-y-2">
                    <legend class="font-semibold">{{ c('select') }}</legend>
                    <div class="max-h-64 overflow-y-auto">
                        <label v-for="invoice in eligible" :key="invoice.id" class="flex items-center gap-2 py-1">
                            <input v-model="selected" type="checkbox" :value="invoice.id">
                            {{ invoice.number }} · {{ invoice.user?.name }} · {{ money(Number(invoice.outstanding_amount ?? invoice.amount) * 100) }}
                        </label>
                    </div>
                    <label class="block">{{ c('date') }} <input v-model="collectionDate" type="date" required class="rounded border border-border bg-inputBg px-2 py-1"></label>
                    <label class="block">{{ c('days') }} <input v-model.number="noticeDays" type="number" min="1" max="60" required class="w-24 rounded border border-border bg-inputBg px-2 py-1"></label>
                    <button type="submit" :disabled="!selected.length" class="rounded bg-buttonPrimary px-3 py-2 text-buttonTextPrimary">{{ c('prepare') }}</button>
                </fieldset>
            </form>
            <p v-if="!batches.length && !busy">{{ c('empty') }}</p>
            <article v-for="batch in batches" :key="batch.id" class="my-4 space-y-2 rounded border border-border p-3">
                <h4 class="font-semibold">{{ batch.reference }} · {{ c(batch.status) }}</h4>
                <p>{{ batch.creditor_name }} · {{ batch.creditor_id }}</p>
                <p>{{ c('date') }}: {{ batch.collection_date }} · {{ c('total') }}: {{ money(batch.total_cents) }}</p>
                <ClubSepaReturnImport v-if="canManage && resultsAvailable && batch.status === 'exported'" :club-id="clubId" :batch-id="batch.id" :disabled="busy" @imported="load()" />
                <ul class="text-sm">
                    <li v-for="item in batch.items" :key="item.invoice_id">
                        {{ item.number }} · {{ item.name }} · {{ money(item.amount_cents) }} · {{ item.mandate_reference }} · •••• {{ item.iban_last4 }}
                        <ClubSepaResult v-if="resultsAvailable && batch.status === 'exported'" :key="`${item.id}-${item.settlement?.status || ''}-${item.settlement?.retry_authorized_at || ''}`" :item="item" :can-manage="canManage" :user-id="userId" :busy="busy" @record="(action, data) => act(`/${batch.id}/items/${item.id}/${action}`, data)" />
                        <ClubSepaFee v-if="feesAvailable && batch.status === 'exported' && item.settlement?.status === 'returned'" :club-id="clubId" :batch-id="batch.id" :item="item" :can-manage="canManage" :disabled="busy" :corrections-available="feeCorrectionsAvailable" :user-id="Number(userId)" :drafts-available="feeRechargeDrafts" :approvals-available="feeRechargeApprovals" :voids-available="feeRechargeVoids" :credits-available="feeRechargeCredits" @saved="load()" />
                    </li>
                </ul>
                <p v-if="canManage && batch.status === 'draft' && batch.created_by === userId" class="text-sm text-secondary">{{ c('secondPerson') }}</p>
                <button v-if="canManage && batch.status === 'draft'" type="button" :disabled="busy || batch.created_by === userId" class="rounded border border-border px-3 py-2" @click="act(`/${batch.id}/approve`)">{{ c('approve') }}</button>
                <template v-if="canManage && noticesAvailable">
                    <button v-if="batch.status === 'approved' && !batch.notices?.length" type="button" :disabled="busy" class="rounded border border-border px-3 py-2" @click="act(`/${batch.id}/notices`)">{{ c('prepareMessages') }}</button>
                    <details v-if="batch.notices?.length" class="rounded border border-border p-3">
                        <summary>{{ c('noticePreview') }}</summary>
                        <div v-for="notice in batch.notices" :key="notice.id" class="my-3 border-b border-border pb-3">
                            <p>{{ notice.content?.email }} · {{ c(`mail_${notice.status}`) }}</p>
                            <p v-if="notice.sent_at">{{ notice.sent_at }} · {{ notice.message_id }}</p>
                            <p v-if="notice.provider_status" :class="notice.provider_status === 'bounced' ? 'text-error' : 'text-secondary'">
                                {{ c(`provider_${notice.provider_status}`) }}<template v-if="notice.provider_status === 'bounced' && notice.bounce_type"> · {{ notice.bounce_type }}</template>
                            </p>
                            <p v-if="notice.error_code" class="text-error">{{ c(notice.error_code) }}</p>
                            <p class="font-semibold">{{ notice.content?.subject }}</p>
                            <p class="whitespace-pre-wrap">{{ notice.content?.body }}</p>
                        </div>
                    </details>
                    <p v-if="batch.notices?.length" class="text-sm text-secondary">{{ c('sendNoticeHelp') }}</p>
                    <form v-if="batch.status === 'approved' && batch.notices?.some(n => ['prepared', 'queued', 'blocked'].includes(n.status))" class="space-y-2" @submit.prevent="act(`/${batch.id}/notices/send`, { confirmed: batch.mailConfirmed })">
                        <label class="flex gap-2"><input v-model="batch.mailConfirmed" type="checkbox" required :disabled="busy"> {{ c('sendConfirm') }}</label>
                        <button type="submit" :disabled="busy || !batch.mailConfirmed" class="rounded border border-border px-3 py-2">{{ c('sendMessages') }}</button>
                    </form>
                </template>
                <form v-if="canManage && batch.status === 'approved'" class="space-y-2" @submit.prevent="act(`/${batch.id}/notice`, batch.notice)">
                    <p class="text-sm text-secondary">{{ c('noticeHelp') }}</p>
                    <fieldset :disabled="busy" class="space-y-2">
                        <label class="block">{{ c('sentOn') }} <input v-model="batch.notice.sent_on" type="date" required class="rounded border border-border bg-inputBg"></label>
                        <label class="block">{{ c('channel') }} <select v-model="batch.notice.channel" class="rounded border border-border bg-inputBg"><option v-for="channel in ['email', 'letter', 'portal']" :key="channel" :value="channel">{{ c(channel) }}</option></select></label>
                        <label class="block">{{ c('evidence') }} <textarea v-model="batch.notice.reference" required maxlength="2000" class="block w-full rounded border border-border bg-inputBg"></textarea></label>
                        <label class="flex gap-2"><input v-model="batch.notice.confirmed" type="checkbox" required> {{ c('confirm') }}</label>
                        <button type="submit" class="rounded border border-border px-3 py-2">{{ c('record') }}</button>
                    </fieldset>
                </form>
                <p v-if="batch.notice_sent_on" class="text-sm">{{ c('sentOn') }}: {{ batch.notice_sent_on }} · {{ batch.notice_reference }}</p>
                <button v-if="canManage && ['notified', 'exported'].includes(batch.status)" :disabled="busy" type="button" class="rounded bg-buttonPrimary px-3 py-2 text-buttonTextPrimary" @click="act(`/${batch.id}/export`, {}, true)">{{ c('export') }}</button>
                <form v-if="canManage && ['draft', 'approved', 'notified'].includes(batch.status)" @submit.prevent="act(`/${batch.id}/cancel`, { reason: batch.reason })">
                    <label class="block">{{ c('reason') }} <input v-model="batch.reason" required maxlength="2000" class="rounded border border-border bg-inputBg px-2 py-1" :disabled="busy"></label>
                    <button type="submit" :disabled="busy" class="mt-2 rounded border border-border px-3 py-2">{{ c('cancel') }}</button>
                </form>
            </article>
            <button v-if="more" type="button" :disabled="busy" @click="load(true)">{{ c('more') }}</button>
        </template>
    </section>
</template>
