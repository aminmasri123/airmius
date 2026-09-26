<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import translations from '@/i18n/sepaFeeCorrectionLocalization.json'
import { createFeeCorrectionState } from './feeCorrectionState'

const props = defineProps({ clubId: Number, batchId: Number, item: Object, canManage: Boolean, disabled: Boolean, available: Boolean })
const emit = defineEmits(['saved'])
const { locale } = useI18n()
const c = key => (translations[locale.value] || translations.en)[key] || key
const history = computed(() => [...(props.item.settlement?.fee_corrections || [])].sort((a, b) => a.revision - b.revision))
const current = computed(() => {
    const latest = history.value.at(-1), original = props.item.settlement.fee_entry
    return { amount: latest ? latest.amount_cents : Math.round(Number(original.amount) * 100),
        revision: latest?.revision || 0, date: (latest?.booked_on || original.booked_on).slice(0, 10) }
})
const money = cents => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }).format(cents / 100)
const { state, submit, reset } = createFeeCorrectionState({ current: () => current.value,
    uuid: () => window.crypto.randomUUID(), errorText: () => c('error'), onSaved: () => emit('saved'),
    post: body => window.axios.post(`/api/v1/clubs/${props.clubId}/sepa-batches/${props.batchId}/items/${props.item.id}/fee-corrections`, body),
})
// Only a freshly fetched item releases a frozen request after reload.
watch(() => props.item, reset)
function save() { if (props.available && props.canManage && !props.disabled) return submit() }
</script>

<template>
    <section class="mt-2 space-y-2" :aria-busy="state.busy">
        <p class="font-semibold">{{ c('current') }}: {{ money(current.amount) }}</p>
        <details v-if="history.length">
            <summary class="cursor-pointer">{{ c('history') }}</summary>
            <ol class="space-y-2 break-words">
                <li v-for="entry in history" :key="entry.id" class="rounded border border-border p-2">
                    #{{ entry.revision }} · {{ entry.booked_on.slice(0, 10) }} · {{ money(entry.previous_amount_cents) }} → {{ money(entry.amount_cents) }}
                    <p>{{ entry.reference }}</p><p class="whitespace-pre-wrap">{{ entry.reason }}</p>
                </li>
            </ol>
        </details>
        <details v-if="available && canManage">
            <summary class="cursor-pointer font-semibold">{{ c('title') }}</summary>
            <p class="my-2 text-sm">{{ c('help') }}</p>
            <form class="space-y-2" @submit.prevent="save">
                <fieldset :disabled="state.busy || disabled || !!state.request" class="space-y-2">
                    <label class="block">{{ c('amount') }}<input v-model="state.form.amount" type="text" inputmode="decimal" required class="block w-full rounded border border-border bg-inputBg"></label>
                    <label class="block">{{ c('date') }}<input v-model="state.form.date" type="date" :min="current.date" required class="block max-w-full rounded border border-border bg-inputBg"></label>
                    <label class="block">{{ c('reference') }}<input v-model="state.form.reference" maxlength="180" required class="block w-full rounded border border-border bg-inputBg"></label>
                    <label class="block">{{ c('reason') }}<textarea v-model="state.form.reason" maxlength="2000" required class="block w-full rounded border border-border bg-inputBg" /></label>
                </fieldset>
                <p v-if="state.phase === 'uncertain'" role="status">{{ c('uncertain') }}</p>
                <p v-if="state.phase === 'saved'" role="status">{{ c('saved') }}</p>
                <template v-if="state.phase !== 'saved'">
                    <label class="flex gap-2"><input v-model="state.confirmed" type="checkbox" required :disabled="state.busy || disabled">{{ c('confirm') }}</label>
                    <button type="submit" :disabled="state.busy || disabled || !state.confirmed" class="rounded border border-border px-3 py-2">{{ c(state.request ? 'retry' : 'save') }}</button>
                </template>
                <button v-if="state.request" type="button" :disabled="state.busy || disabled" class="ml-2 rounded border border-border px-3 py-2" @click="emit('saved')">{{ c('reload') }}</button>
            </form>
            <p v-if="state.error" role="alert" class="text-error">{{ state.error }}</p>
        </details>
    </section>
</template>
