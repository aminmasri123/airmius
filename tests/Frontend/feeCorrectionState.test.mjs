import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createFeeCorrectionState } from '../../resources/js/Components/ClubMemberships/feeCorrectionState.js'

function setup(post) {
    let snapshot = { amount: 350, revision: 0, date: '2026-10-11' }, saved = 0, identifiers = 0
    const flow = createFeeCorrectionState({ current: () => snapshot, post, uuid: () => `request-${++identifiers}`,
        errorText: () => 'Check inputs', onSaved: () => saved++ })
    function fill(amount = '2,00') {
        Object.assign(flow.state.form, { amount, date: '2026-10-12', reference: 'REF-1', reason: 'Bank evidence' })
        flow.state.confirmed = true
    }
    return { ...flow, fill, saved: () => saved, identifiers: () => identifiers, reload: next => { snapshot = next; flow.reset() } }
}

test('ambiguous transport failure retains the exact request and requires confirmation to retry', async () => {
    const calls = []; let attempts = 0
    const flow = setup(async body => { calls.push(body); if (++attempts === 1) throw new Error('timeout') })
    flow.fill()
    assert.equal(await flow.submit(), false)
    assert.equal(flow.state.phase, 'uncertain'); assert.equal(calls.length, 1)
    assert.equal(await flow.submit(), false); assert.equal(calls.length, 1)
    flow.state.form.amount = '9.00' // Even an unexpected programmatic edit cannot mutate the frozen body.
    flow.state.confirmed = true
    assert.equal(await flow.submit(), true)
    assert.deepEqual(calls[0], calls[1]); assert.equal(calls[1].amount_cents, 200)
    assert.equal(flow.identifiers(), 1); assert.equal(flow.saved(), 1)
    flow.state.confirmed = true; await flow.submit(); assert.equal(calls.length, 2)
})

test('edits revoke confirmation, zero cancels, and concurrent submits do not duplicate bookings', async () => {
    let finish; const calls = []
    const flow = setup(body => { calls.push(body); return new Promise(resolve => { finish = resolve }) })
    flow.fill(); flow.state.form.amount = '0'
    assert.equal(flow.state.confirmed, false); await flow.submit(); assert.equal(calls.length, 0)
    flow.state.confirmed = true
    const pending = flow.submit(); await flow.submit()
    assert.equal(calls.length, 1); assert.equal(calls[0].amount_cents, 0)
    flow.reset(); assert.equal(flow.state.request.request_id, 'request-1')
    finish(); await pending
    flow.reload({ amount: 0, revision: 1, date: '2026-10-12' })
    assert.equal(flow.state.phase, 'ready'); assert.equal(flow.state.form.amount, '0.00')
    assert.equal(flow.state.request, null)
})

test('invalid input and unchanged amount never reach the transport', async () => {
    const calls = [], flow = setup(async body => calls.push(body))
    for (const changes of [{ amount: '-1' }, { amount: '3.50' }, { amount: '1.234' }, { amount: '1000000' },
        { date: '2026-10-10' }, { reason: ' ' }, { reference: ' ' }]) {
        flow.fill(); Object.assign(flow.state.form, changes); flow.state.confirmed = true
        assert.equal(await flow.submit(), false)
    }
    assert.equal(calls.length, 0); assert.equal(flow.identifiers(), 0)
})

test('refresh after stale revision uses the freshly loaded amount and revision', async () => {
    const calls = [], flow = setup(async body => { calls.push(body); throw { response: { data: { message: 'Stale revision' } } } })
    flow.fill(); await flow.submit(); assert.equal(flow.state.error, 'Stale revision')
    flow.reload({ amount: 150, revision: 2, date: '2026-10-12' })
    flow.fill('1.00'); await flow.submit()
    assert.equal(calls[1].expected_revision, 2); assert.equal(calls[1].request_id, 'request-2')
})
