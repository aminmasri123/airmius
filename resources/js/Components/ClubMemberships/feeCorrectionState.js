import { reactive, watch } from 'vue'
import { parseFeeCents } from './feeAmount.js'

export function feeCorrectionToday() {
    const now = new Date()
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

function validBookingDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
    const parsed = new Date(`${value}T00:00:00Z`)
    return Number.isFinite(parsed.getTime()) && parsed.toISOString().slice(0, 10) === value
}

// A transport failure never creates a new logical request or an automatic retry.
export function createFeeCorrectionState({ current, post, uuid, errorText, onSaved, today = feeCorrectionToday }) {
    const state = reactive({ form: { amount: '', date: '', reference: '', reason: '' }, confirmed: false,
        busy: false, phase: 'ready', request: null, error: '' })
    const reset = () => {
        if (state.busy) return
        state.form = { amount: (current().amount / 100).toFixed(2), date: '', reference: '', reason: '' }
        state.confirmed = false; state.request = null; state.phase = 'ready'; state.error = ''
    }
    watch(() => [state.form.amount, state.form.date, state.form.reference, state.form.reason], () => {
        state.confirmed = false
    }, { flush: 'sync' })
    async function submit() {
        if (state.busy || !state.confirmed || state.phase === 'saved') return false
        if (!state.request) {
            const f = state.form, cents = parseFeeCents(f.amount)
            if (cents === null || cents === current().amount
                || !validBookingDate(f.date) || f.date < current().date || f.date > today()
                || !f.reference.trim() || !f.reason.trim() || f.reference.length > 180 || f.reason.length > 2000) {
                state.error = errorText(); return false
            }
            try {
                state.request = Object.freeze({ request_id: uuid(), expected_revision: current().revision,
                    amount_cents: cents, booked_on: f.date, reference: f.reference.trim(), reason: f.reason.trim(), confirmed: true })
            } catch { state.error = errorText(); return false }
        }
        state.busy = true; state.error = ''
        try {
            await post(state.request)
            state.phase = 'saved'; onSaved(); return true
        } catch (e) {
            state.phase = 'uncertain'; state.error = e.response?.data?.message || errorText(); return false
        } finally { state.busy = false; state.confirmed = false }
    }
    reset()
    return { state, submit, reset }
}
