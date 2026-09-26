<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import translations from '@/i18n/sepaReturnImportLocalization.json'

const props = defineProps({ clubId: Number, batchId: Number, disabled: Boolean })
const emit = defineEmits(['imported'])
const { locale } = useI18n()
const c = key => (translations[locale.value] || translations.en)[key] || key
const file = ref(null), report = ref(null), busy = ref(false), error = ref(''), saved = ref(false)
const confirmed = ref(false), confirmUnlinked = ref(false)
const columns = ref(null), mapping = ref({}), ignoreConfirmed = ref(false)
const fileFormat = ref('')
const fields = ['end_to_end_id', 'booking_date', 'amount', 'currency', 'reference', 'reason', 'iban']
const ignored = computed(() => (columns.value || []).map((_, index) => index).filter(index => !Object.values(mapping.value).includes(index)))
const mappingReady = computed(() => !columns.value || (fields.slice(0, 6).every(key => Number.isInteger(mapping.value[key])) && (!ignored.value.length || ignoreConfirmed.value)))
watch(mapping, () => { report.value = null; confirmed.value = false; confirmUnlinked.value = false; ignoreConfirmed.value = false }, { deep: true })
watch(ignoreConfirmed, () => { report.value = null; confirmed.value = false; confirmUnlinked.value = false })
const base = computed(() => `/api/v1/clubs/${props.clubId}/sepa-batches/${props.batchId}/returns`)
const header = 'end_to_end_id;booking_date;amount;currency;reference;reason\n'
const templateUrl = `data:text/csv;charset=utf-8,${encodeURIComponent(header)}`
function choose(event) {
    file.value = event.target.files?.[0] || null
    columns.value = null; mapping.value = {}; ignoreConfirmed.value = false; fileFormat.value = ''
    report.value = null; confirmed.value = false; confirmUnlinked.value = false; saved.value = false; error.value = ''
}
async function readColumns() {
    if (busy.value || props.disabled || !file.value) return
    busy.value = true; error.value = ''; saved.value = false
    report.value = null; confirmed.value = false; confirmUnlinked.value = false
    const data = new FormData(); data.append('file', file.value)
    try {
        const response = await window.axios.post(`${base.value}/columns`, data)
        fileFormat.value = response.data.data.format || 'csv'
        columns.value = fileFormat.value === 'csv' ? response.data.data.columns : null
        mapping.value = columns.value ? Object.fromEntries(fields.map(key => [key, columns.value.includes(key) ? columns.value.indexOf(key) : ''])) : {}
    } catch (e) { error.value = e.response?.data?.message || c('error') }
    finally { busy.value = false }
}
async function send(importing = false) {
    if (busy.value || props.disabled || !file.value) return
    if (!importing && !mappingReady.value) return
    busy.value = true; error.value = ''; saved.value = false
    const data = new FormData(); data.append('file', file.value)
    if (importing) {
        if (!report.value?.can_import || !confirmed.value || (report.value.unlinked_count && !confirmUnlinked.value)) { busy.value = false; return }
        data.append('preview_token', report.value.preview_token)
        data.append('confirmed', '1'); data.append('confirm_unlinked', confirmUnlinked.value ? '1' : '0')
    } else {
        report.value = null; confirmed.value = false; confirmUnlinked.value = false
        if (columns.value) {
            for (const [key, index] of Object.entries(mapping.value)) if (Number.isInteger(index)) data.append(`mapping[${key}]`, String(index))
            for (const index of ignored.value) data.append('ignored_columns[]', String(index))
        }
    }
    try {
        const response = await window.axios.post(`${base.value}/${importing ? 'import' : 'preview'}`, data)
        if (importing) { report.value = null; saved.value = true; emit('imported') }
        else report.value = response.data.data
    } catch (e) {
        error.value = e.response?.data?.message || c('error')
        if (importing) { report.value = null; confirmed.value = false; confirmUnlinked.value = false }
    } finally { busy.value = false }
}
</script>

<template>
    <details class="rounded border border-border p-3" :aria-busy="busy">
        <summary class="cursor-pointer font-semibold">{{ c('title') }}</summary>
        <p class="my-2 text-sm text-secondary">{{ c('help') }}</p>
        <a :href="templateUrl" download="sepa-return-header.csv" class="underline">{{ c('template') }}</a>
        <fieldset :disabled="busy || disabled" class="my-3 space-y-3">
            <label class="block">{{ c('file') }} <input type="file" accept=".csv,.xml,text/csv,application/xml,text/xml" @change="choose"></label>
            <button type="button" :disabled="!file" class="rounded border border-border px-3 py-2" @click="readColumns">{{ c('mapColumns') }}</button>
            <p v-if="fileFormat" class="text-sm">{{ c('detectedFormat') }}: {{ fileFormat }}</p>
            <div v-if="columns" class="space-y-2">
                <label v-for="field in fields" :key="field" class="block">{{ c(`field_${field}`) }}
                    <select v-model="mapping[field]" class="block w-full rounded border border-border">
                        <option value="">{{ c('notMapped') }}</option>
                        <option v-for="(column, index) in columns" :key="index" :value="index">{{ index + 1 }} · {{ column }}</option>
                    </select>
                </label>
                <label v-if="ignored.length" class="flex gap-2"><input v-model="ignoreConfirmed" type="checkbox">{{ c('ignoreConfirm') }}: {{ ignored.map(index => columns[index]).join(', ') }}</label>
            </div>
            <button type="button" :disabled="!file || !mappingReady" class="rounded border border-border px-3 py-2" @click="send(false)">{{ c('preview') }}</button>
            <template v-if="report">
                <p v-if="report.ignored_columns?.length" class="text-sm">{{ c('ignoredColumns') }}: {{ report.ignored_columns.join(', ') }}</p>
                <ol class="space-y-2">
                    <li v-for="row in report.rows" :key="row.row" class="rounded border border-border p-2 text-sm">
                        <p>{{ row.row }} · {{ row.end_to_end_id }} · {{ row.booking_date || '—' }} · {{ row.amount_cents === null ? '—' : new Intl.NumberFormat(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(row.amount_cents / 100) }} {{ row.currency }}</p>
                        <p>{{ row.reference }} · {{ row.reason }}</p>
                        <p>{{ c(row.status) }} · {{ c(row.has_linked_receipt ? 'linked' : 'unlinked') }}</p>
                        <ul v-if="row.errors.length" class="text-error"><li v-for="issue in row.errors" :key="issue">{{ c(issue) }}</li></ul>
                    </li>
                </ol>
                <label v-if="report.unlinked_count" class="flex gap-2"><input v-model="confirmUnlinked" type="checkbox">{{ c('unlinkedConfirm') }}</label>
                <label class="flex gap-2"><input v-model="confirmed" type="checkbox">{{ c('confirm') }}</label>
                <button type="button" :disabled="!report.can_import || !confirmed || (report.unlinked_count > 0 && !confirmUnlinked)" class="rounded border border-border px-3 py-2" @click="send(true)">{{ c('apply') }}</button>
            </template>
        </fieldset>
        <p v-if="error" role="alert" class="text-error">{{ error }}</p>
        <p v-if="saved" role="status">{{ c('saved') }}</p>
    </details>
</template>
