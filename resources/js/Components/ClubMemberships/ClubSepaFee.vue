<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ClubSepaFeeRecharge from './ClubSepaFeeRecharge.vue'
import ClubSepaFeeCorrection from './ClubSepaFeeCorrection.vue'
import translations from '@/i18n/sepaFeeLocalization.json'

const props = defineProps({ clubId: Number, batchId: Number, item: Object, canManage: Boolean, disabled: Boolean, correctionsAvailable: Boolean, userId: Number, draftsAvailable: Boolean, approvalsAvailable: Boolean, voidsAvailable: Boolean, creditsAvailable: Boolean })
const emit = defineEmits(['saved'])
const { locale } = useI18n()
const c = key => (translations[locale.value] || translations.en)[key] || key
const base = computed(() => `/api/v1/clubs/${props.clubId}/sepa-batches/${props.batchId}/items/${props.item.id}`)
const expense = computed(() => props.item.settlement?.fee_entry)
const mode = ref(''), query = ref(''), options = ref([]), page = ref(1), more = ref(false), searched = ref(false)
const selected = ref(null), amount = ref(''), date = ref(''), reference = ref(''), confirmed = ref(false), busy = ref(false), error = ref('')
watch(mode, () => { selected.value = null; confirmed.value = false; error.value = ''; amount.value = ''; date.value = ''; reference.value = '' })
watch([amount, date, reference, selected], () => { confirmed.value = false })
const money = value => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }).format(Number(value))
async function search(targetPage = 1) {
    if (busy.value || props.disabled) return
    busy.value = true; error.value = ''
    try {
        const response = await window.axios.get(`${base.value}/fee-options`, { params: { q: query.value, page: targetPage } })
        options.value = response.data.data.data; page.value = response.data.data.current_page
        more.value = !!response.data.data.next_page_url; searched.value = true
    } catch (e) { error.value = e.response?.data?.message || c('error') }
    finally { busy.value = false }
}
function choose(entry) {
    selected.value = entry; amount.value = entry.amount; date.value = entry.booked_on.slice(0, 10); reference.value = entry.reference
}
async function save() {
    if (busy.value || props.disabled || !props.canManage || !confirmed.value || !mode.value || (mode.value === 'existing' && !selected.value)) return
    const value = String(amount.value)
    if (!/^\d{1,6}(?:\.\d{1,2})?$/.test(value) || Number(value) <= 0) { error.value = c('error'); return }
    busy.value = true; error.value = ''
    try {
        await window.axios.post(`${base.value}/fee`, {
            confirmed: true, amount_cents: Math.round(Number(value) * 100), booked_on: date.value, reference: reference.value,
            ...(mode.value === 'existing' ? { finance_entry_id: selected.value.id } : {}),
        })
        confirmed.value = false; mode.value = ''; emit('saved')
    } catch (e) { confirmed.value = false; error.value = e.response?.data?.message || c('error') }
    finally { busy.value = false }
}
</script>

<template>
    <div class="my-2 rounded border border-border p-3" :aria-busy="busy">
        <p v-if="expense">{{ c('recorded') }} #{{ expense.id }} · {{ money(expense.amount) }} · {{ expense.booked_on?.slice(0, 10) }} · {{ expense.reference }}</p>
        <ClubSepaFeeCorrection v-if="expense" :club-id="clubId" :batch-id="batchId" :item="item" :can-manage="canManage" :disabled="disabled || busy" :available="correctionsAvailable" @saved="emit('saved')" />
        <ClubSepaFeeRecharge v-if="expense && (draftsAvailable || item.settlement?.fee_recharges?.length)" :club-id="clubId" :batch-id="batchId" :item="item" :user-id="userId" :can-manage="canManage" :disabled="disabled || busy" :drafts-available="draftsAvailable" :approvals-available="approvalsAvailable" :voids-available="voidsAvailable" :credits-available="creditsAvailable" @saved="emit('saved')" />
        <details v-if="!expense && canManage">
            <summary class="cursor-pointer font-semibold">{{ c('title') }}</summary>
            <p class="my-2 text-sm">{{ c('help') }}</p>
            <form class="space-y-2" @submit.prevent="save">
                <fieldset :disabled="busy || disabled" class="space-y-2">
                    <label class="block">{{ c('choice') }}
                        <select v-model="mode" required class="block rounded border border-border bg-inputBg">
                            <option value="" disabled>{{ c('choice') }}</option>
                            <option value="existing">{{ c('existing') }}</option><option value="new">{{ c('new') }}</option>
                        </select>
                    </label>
                    <template v-if="mode === 'existing'">
                        <label class="block">{{ c('reference') }} <input v-model="query" maxlength="180" class="rounded border border-border bg-inputBg"></label>
                        <button type="button" class="rounded border border-border px-3 py-2" @click="search(1)">{{ c('search') }}</button>
                        <p v-if="searched && !options.length">{{ c('empty') }}</p>
                        <ul><li v-for="entry in options" :key="entry.id"><label class="flex gap-2"><input type="radio" :name="`fee-entry-${item.id}`" :checked="selected?.id === entry.id" @change="choose(entry)">#{{ entry.id }} · {{ money(entry.amount) }} · {{ entry.booked_on.slice(0, 10) }} · {{ entry.reference }}</label></li></ul>
                        <div v-if="searched" class="flex gap-2"><button type="button" :disabled="page <= 1" @click="search(page - 1)">{{ c('previous') }}</button><span>{{ page }}</span><button type="button" :disabled="!more" @click="search(page + 1)">{{ c('next') }}</button></div>
                        <p v-if="selected">{{ c('selected') }} #{{ selected.id }} · {{ money(selected.amount) }} · {{ selected.booked_on.slice(0, 10) }} · {{ selected.reference }}</p>
                    </template>
                    <template v-if="mode === 'new'">
                        <label class="block">{{ c('amount') }} <input v-model="amount" type="number" min="0.01" max="999999.99" step="0.01" required class="rounded border border-border bg-inputBg"></label>
                        <label class="block">{{ c('date') }} <input v-model="date" type="date" :min="item.settlement.returned_on.slice(0, 10)" required class="rounded border border-border bg-inputBg"></label>
                        <label class="block">{{ c('reference') }} <input v-model="reference" maxlength="180" required class="rounded border border-border bg-inputBg"></label>
                    </template>
                    <label v-if="mode" class="flex gap-2"><input v-model="confirmed" type="checkbox" required>{{ c('confirm') }}</label>
                    <button type="submit" :disabled="!confirmed || !mode || (mode === 'existing' && !selected)" class="rounded border border-border px-3 py-2">{{ c(mode === 'existing' ? 'link' : 'save') }}</button>
                </fieldset>
            </form>
            <p v-if="error" role="alert" class="text-error">{{ error }}</p>
        </details>
    </div>
</template>
