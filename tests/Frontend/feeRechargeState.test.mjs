import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createFeeRechargeState } from '../../resources/js/Components/ClubMemberships/feeRechargeState.js'

function setup(post) {
    let identifiers = 0, saves = 0
    const flow = createFeeRechargeState({ current: () => ({ amount: 350, revision: 2 }), post,
        uuid: () => `request-${++identifiers}`, errorText: () => 'Invalid', onSaved: () => saves++ })
    return { ...flow, saves: () => saves }
}

test('proposal freezes UUID, revision and amount across ambiguous retries', async () => {
    const calls = []; let fail = true
    const f = setup(async (path, body) => { calls.push({ path, body }); if (fail) throw Error('lost response') })
    f.select('propose'); Object.assign(f.state.form, { amount: '2,00', date: '2026-11-01', basis: 'Approved rule', reason: 'Bank proof' })
    f.state.confirmed = true; assert.equal(await f.submit(), false)
    assert.deepEqual(calls[0], { path: 'fee-recharges', body: { confirmed: true, request_id: 'request-1', expected_revision: 2,
        amount_cents: 200, due_date: '2026-11-01', basis: 'Approved rule', reason: 'Bank proof' } })
    f.select('approve', 99); assert.equal(f.state.action.kind, 'propose')
    await f.submit(); assert.equal(calls.length, 1)
    fail = false; f.state.confirmed = true; await f.submit()
    assert.deepEqual(calls[1], calls[0]); assert.equal(f.saves(), 1)
    f.state.confirmed = true; await f.submit(); assert.equal(calls.length, 2)
})

test('approval requires separate basis confirmation and has no default accounting account', async () => {
    const calls = [], f = setup(async (path, body) => calls.push({ path, body }))
    f.select('approve', 5); f.state.confirmed = true; await f.submit(); assert.equal(calls.length, 0)
    f.state.form.account = '4990'; f.state.confirmed = true; await f.submit(); assert.equal(calls.length, 0)
    f.state.basisConfirmed = true; await f.submit()
    assert.deepEqual(calls, [{ path: 'fee-recharges/5/approve', body: { confirmed: true, basis_confirmed: true, revenue_account: '4990' } }])
})

test('cancel and void actions use exact scoped endpoints and required evidence', async () => {
    for (const [kind, path] of [['cancel', 'fee-recharges/5/cancel'], ['requestVoid', 'fee-recharges/5/void-requests'],
        ['approveVoid', 'fee-recharges/5/void-requests/7/approve'], ['withdrawVoid', 'fee-recharges/5/void-requests/7/withdraw']]) {
        const calls = [], f = setup(async (path, body) => calls.push({ path, body }))
        f.select(kind, 5, 7); f.state.form.reason = 'Reviewed evidence'; f.state.confirmed = true
        await f.submit(); assert.equal(calls[0].path, path)
        assert.equal(calls[0].body.confirmed, true)
        if (kind === 'requestVoid') assert.equal(calls[0].body.request_id, 'request-1')
        if (kind !== 'approveVoid') assert.equal(calls[0].body.reason, 'Reviewed evidence')
    }
})

test('credit approval, withdrawal and refund use scoped evidence endpoints', async () => {
    for (const [kind, child, path] of [['requestCredit', null, 'fee-recharges/5/credit-requests'],
        ['approveCredit', 9, 'fee-recharges/5/credit-requests/9/approve'],
        ['withdrawCredit', 9, 'fee-recharges/5/credit-requests/9/withdraw']]) {
        const calls = [], f = setup(async (path, body) => calls.push({ path, body }))
        f.select(kind, 5, child); f.state.form.reason = 'Reviewed correction'; f.state.confirmed = true
        await f.submit(); assert.equal(calls[0].path, path); assert.equal(calls[0].body.confirmed, true)
        if (kind === 'requestCredit') assert.equal(calls[0].body.request_id, 'request-1')
        if (kind !== 'approveCredit') assert.equal(calls[0].body.reason, 'Reviewed correction')
    }
    const calls = [], f = setup(async (path, body) => calls.push({ path, body }))
    f.select('refund', 5, 9); Object.assign(f.state.form, { date: '2026-11-02', reference: ' BANK-REFUND ', financeEntryId: '44' })
    f.state.confirmed = true; await f.submit()
    assert.deepEqual(calls, [{ path: 'fee-recharges/5/credit-requests/9/refund', body: { confirmed: true,
        request_id: 'request-1', booked_on: '2026-11-02', reference: 'BANK-REFUND', finance_entry_id: 44 } }])
})

test('ambiguous refund retry preserves UUID and bank evidence', async () => {
    const calls = []; let fail = true
    const f = setup(async (path, body) => { calls.push({ path, body }); if (fail) throw Error('lost') })
    f.select('refund', 5, 9); Object.assign(f.state.form, { date: '2026-11-02', reference: 'BANK-REFUND' })
    f.state.confirmed = true; await f.submit(); assert.ok(f.state.request)
    fail = false; f.state.confirmed = true; await f.submit()
    assert.deepEqual(calls[1], calls[0]); assert.equal(f.saves(), 1)
})

test('edits revoke confirmations and invalid proposals never send', async () => {
    const calls = [], f = setup(async (...args) => calls.push(args))
    for (const amount of ['0', '-1', '3.51', '1.234']) {
        f.select('propose'); Object.assign(f.state.form, { amount, date: '2026-11-01', basis: 'Rule', reason: 'Proof' })
        f.state.confirmed = true; await f.submit()
    }
    assert.equal(calls.length, 0)
    f.select('approve', 5); f.state.confirmed = true; f.state.basisConfirmed = true; f.state.form.account = '4991'
    assert.equal(f.state.confirmed, false); assert.equal(f.state.basisConfirmed, false)
})

test('in-flight calls cannot be reset or duplicated and a refresh releases an uncertain request', async () => {
    let reject; const calls = [], f = setup((path, body) => { calls.push(body); return new Promise((_, r) => { reject = r }) })
    f.select('requestVoid', 5); f.state.form.reason = 'Bank proof'; f.state.confirmed = true
    const pending = f.submit(); await f.submit(); f.reset(); assert.equal(calls.length, 1); assert.ok(f.state.request)
    reject(Error('timeout')); await pending
    f.reset(); assert.equal(f.state.request, null); assert.equal(f.state.action, null)
    assert.equal(f.state.confirmed, false)
})
