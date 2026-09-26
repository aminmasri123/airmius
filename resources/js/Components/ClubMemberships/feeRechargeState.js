import { reactive, watch } from 'vue'

export function createFeeRechargeState({ current, post, uuid, errorText, onSaved }) {
    const state = reactive({ action: null, form: {}, confirmed: false, basisConfirmed: false,
        busy: false, request: null, phase: 'ready', error: '' })
    const reset = () => {
        if (state.busy) return
        state.action = null; state.form = {}; state.confirmed = false; state.basisConfirmed = false
        state.request = null; state.phase = 'ready'; state.error = ''
    }
    const select = (kind, proposalId = null, childId = null) => {
        if (state.busy || state.request) return
        state.action = { kind, proposalId, voidId: kind.includes('Void') ? childId : null,
            creditId: ['approveCredit', 'withdrawCredit', 'refund'].includes(kind) ? childId : null }
        state.form = { amount: (current().amount / 100).toFixed(2), date: '', reason: '', basis: '', account: '', reference: '', financeEntryId: '' }
        state.confirmed = false; state.basisConfirmed = false; state.error = ''
    }
    watch(() => Object.values(state.form), () => { state.confirmed = false; state.basisConfirmed = false }, { flush: 'sync' })
    async function submit() {
        if (state.busy || !state.confirmed || !state.action || state.phase === 'saved') return false
        if (!state.request) {
                const { kind, proposalId, voidId, creditId } = state.action, f = state.form
            let path = 'fee-recharges', body = { confirmed: true }
            try {
                if (kind === 'propose') {
                    const value = String(f.amount).replace(',', '.'), cents = Math.round(Number(value) * 100)
                    if (!/^\d{1,6}(?:\.\d{1,2})?$/.test(value) || cents <= 0 || cents > current().amount
                        || !/^\d{4}-\d{2}-\d{2}$/.test(f.date) || !f.basis.trim() || !f.reason.trim()) throw Error()
                    body = { ...body, request_id: uuid(), expected_revision: current().revision, amount_cents: cents,
                        due_date: f.date, basis: f.basis.trim(), reason: f.reason.trim() }
                } else {
                    if (!Number.isInteger(proposalId) || proposalId <= 0) throw Error()
                    path += `/${proposalId}`
                    if (kind === 'approve') {
                        if (!state.basisConfirmed || !/^\d{1,20}$/.test(f.account)) throw Error()
                        path += '/approve'; body = { ...body, basis_confirmed: true, revenue_account: f.account }
                    } else if (kind === 'cancel' || kind === 'requestVoid') {
                        if (!f.reason.trim()) throw Error()
                        path += kind === 'cancel' ? '/cancel' : '/void-requests'
                        body = { ...body, reason: f.reason.trim(), ...(kind === 'requestVoid' ? { request_id: uuid() } : {}) }
                    } else if (kind === 'approveVoid' || kind === 'withdrawVoid') {
                        if (!Number.isInteger(voidId) || voidId <= 0) throw Error()
                        path += `/void-requests/${voidId}/${kind === 'approveVoid' ? 'approve' : 'withdraw'}`
                        if (kind === 'withdrawVoid') {
                            if (!f.reason.trim()) throw Error()
                            body.reason = f.reason.trim()
                        }
                    } else if (kind === 'requestCredit') {
                        if (!f.reason.trim()) throw Error()
                        path += '/credit-requests'; body = { ...body, request_id: uuid(), reason: f.reason.trim() }
                    } else if (kind === 'approveCredit' || kind === 'withdrawCredit' || kind === 'refund') {
                        if (!Number.isInteger(creditId) || creditId <= 0) throw Error()
                        path += `/credit-requests/${creditId}`
                        if (kind === 'approveCredit') path += '/approve'
                        if (kind === 'withdrawCredit') {
                            if (!f.reason.trim()) throw Error()
                            path += '/withdraw'; body.reason = f.reason.trim()
                        }
                        if (kind === 'refund') {
                            if (!/^\d{4}-\d{2}-\d{2}$/.test(f.date) || !f.reference.trim() || f.reference.length > 180) throw Error()
                            const financeEntryId = String(f.financeEntryId || '').trim()
                            if (financeEntryId && (!/^\d+$/.test(financeEntryId) || Number(financeEntryId) <= 0)) throw Error()
                            path += '/refund'; body = { ...body, request_id: uuid(), booked_on: f.date,
                                reference: f.reference.trim(), ...(financeEntryId ? { finance_entry_id: Number(financeEntryId) } : {}) }
                        }
                    } else throw Error()
                }
                if ((body.reason?.length || 0) > 2000 || (body.basis?.length || 0) > 2000) throw Error()
                state.request = Object.freeze({ path, body: Object.freeze(body) })
            } catch { state.error = errorText(); return false }
        }
        state.busy = true; state.error = ''
        try {
            await post(state.request.path, state.request.body)
            state.phase = 'saved'; onSaved(); return true
        } catch (e) {
            state.phase = 'uncertain'; state.error = e.response?.data?.message || errorText(); return false
        } finally { state.busy = false; state.confirmed = false }
    }
    return { state, select, submit, reset }
}
