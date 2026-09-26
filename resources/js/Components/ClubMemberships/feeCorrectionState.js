import { reactive, watch } from 'vue'

// A transport failure never creates a new logical request or an automatic retry.
export function createFeeCorrectionState({ current, post, uuid, errorText, onSaved }) {
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
            const f = state.form, value = String(f.amount).replace(',', '.')
            const cents = Math.round(Number(value) * 100)
            if (!/^\d{1,6}(?:\.\d{1,2})?$/.test(value) || cents === current().amount
                || !/^\d{4}-\d{2}-\d{2}$/.test(f.date) || f.date < current().date
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
