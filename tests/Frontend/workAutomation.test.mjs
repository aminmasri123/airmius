import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { createRequest, pendingKey, readPending, uncertainResponse } from '../../resources/js/Pages/Auth/Dashboard/WorkAutomation/requests.js'

test('ambiguous creation retains the exact request after reload and separates accounts and clubs', () => {
    const values = new Map()
    const storage = { getItem: (key) => values.get(key), setItem: (key, value) => values.set(key, value) }
    const form = { kind: 'reminder', title: ' Reminder ', body: ' Message ', recipient_roles: ['finance.manage'], scheduled_at: '2027-01-10T12:00:00Z' }
    const request = createRequest(form, 'stable-key')
    const key = pendingKey(7, 9)
    storage.setItem(key, JSON.stringify(request))
    form.title = 'Changed'
    form.recipient_roles.push('members.manage')
    assert.deepEqual(readPending(storage, key), {
        kind: 'reminder', idempotency_key: 'stable-key', recipient_roles: ['finance.manage'],
        payload: { title: 'Reminder', body: 'Message' }, scheduled_at: '2027-01-10T12:00:00.000Z',
    })
    assert.equal(readPending(storage, pendingKey(8, 9)), null)
    assert.equal(readPending(storage, pendingKey(7, 10)), null)
    assert.ok(uncertainResponse({}))
    assert.ok(uncertainResponse({ response: { status: 503 } }))
    assert.equal(uncertainResponse({ response: { status: 422 } }), false)
})

test('all automation labels have locale parity and describe notification jobs', async () => {
    const messages = JSON.parse(await readFile(new URL('../../resources/js/i18n/workAutomation.json', import.meta.url), 'utf8'))
    for (const language of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[language]).sort(), Object.keys(messages.en).sort())
        assert.ok(Object.values(messages[language]).every((value) => value.trim()))
    }
    assert.match(messages.en.supported, /notifications/)
})
