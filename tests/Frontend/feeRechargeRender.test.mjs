import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createSSRApp } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createI18n } from 'vue-i18n'

const root = new URL('../../', import.meta.url)
const source = await readFile(new URL('resources/js/Components/ClubMemberships/ClubSepaFeeRecharge.vue', root), 'utf8')
const texts = JSON.parse(await readFile(new URL('resources/js/i18n/sepaFeeRechargeLocalization.json', root), 'utf8'))
const { descriptor } = parse(source)
let script = compileScript(descriptor, { id: 'fee-recharge-test', inlineTemplate: true }).content
script = script.replace(/from ["']vue["']/g, `from '${import.meta.resolve('vue')}'`)
    .replace(/from ["']vue-i18n["']/g, `from '${import.meta.resolve('vue-i18n')}'`)
    .replace("import translations from '@/i18n/sepaFeeRechargeLocalization.json'", `const translations = ${JSON.stringify(texts)}`)
    .replace("from './feeRechargeState'", `from '${new URL('resources/js/Components/ClubMemberships/feeRechargeState.js', root).href}'`)
const Component = (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default
const proposal = { id: 4, status: 'draft', active_settlement_id: 1, amount_cents: 200, fee_amount_cents: 350,
    fee_revision: 0, member_id: 9, member: { id: 9, name: 'Member Example' }, source_invoice_id: 3,
    due_date: '2026-11-01', basis: '<script>not executable</script>', reason: 'Documented fee', proposed_by: 7, void_requests: [] }
const item = { id: 1, name: 'Member Example', number: 'SOURCE-1', settlement: { fee_entry: { amount: '3.50' }, fee_recharges: [proposal] } }
async function render(overrides = {}, locale = 'de') {
    const app = createSSRApp(Component, { clubId: 1, batchId: 1, item, userId: 8, canManage: false,
        draftsAvailable: true, approvalsAvailable: true, voidsAvailable: true, creditsAvailable: true, ...overrides })
    app.use(createI18n({ legacy: false, locale, messages: {} }))
    return renderToString(app)
}

test('read-only roles see recipient and evidence without action controls', async () => {
    const html = await render()
    assert.match(html, /Member Example/); assert.match(html, /2,00/); assert.match(html, /&lt;script&gt;/)
    assert.doesNotMatch(html, /<button|<form|<script/)
})

test('self-approval and unavailable migrations hide approval actions', async () => {
    assert.match(await render({ canManage: true }), /Weiterbelastung freigeben/)
    assert.doesNotMatch(await render({ canManage: true, userId: 7 }), /Weiterbelastung freigeben/)
    assert.doesNotMatch(await render({ canManage: true, approvalsAvailable: false }), /Weiterbelastung freigeben/)
    assert.doesNotMatch(await render({ canManage: true, userId: undefined }), /Weiterbelastung freigeben/)
})

test('review warning and void-request history are visible while self-approval remains hidden', async () => {
    const approved = { ...proposal, status: 'approved', review_required: true,
        invoice: { number: 'AIR-FEE-1-4', status: 'open', amount: '2.00' },
        void_requests: [{ id: 5, status: 'pending', requested_by: 8, reason: 'Review charge' }] }
    const html = await render({ canManage: true, item: { ...item, settlement: { ...item.settlement, fee_recharges: [approved] } } })
    assert.match(html, /Erneute Prüfung erforderlich/); assert.match(html, /AIR-FEE-1-4/)
    assert.match(html, /Stornoantrag zurücknehmen/); assert.doesNotMatch(html, /Storno freigeben/)
})

test('credit history exposes document and refund state with second-person controls', async () => {
    const credited = { ...proposal, status: 'credited', active_settlement_id: null,
        invoice: { number: 'AIR-FEE-1-4', status: 'cancelled', amount: '2.00' },
        credit_requests: [{ id: 6, status: 'issued', requested_by: 7, reason: 'Paid claim correction',
            amount_cents: 200, refund_due_cents: 50, credit_note_number: 'AIR-GS-FEE-1-6' }] }
    const html = await render({ canManage: true, item: { ...item, settlement: { ...item.settlement, fee_recharges: [credited] } } })
    assert.match(html, /AIR-GS-FEE-1-6/); assert.match(html, /Gutschrift als PDF öffnen/)
    assert.match(html, /Erstattung dokumentieren/); assert.match(html, /0,50/)
})

test('all supported languages have complete keys and render localized proposal statuses', async () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(texts[locale]).sort(), Object.keys(texts.de).sort())
        const html = await render({}, locale)
        assert.ok(html.includes(texts[locale].title)); assert.ok(html.includes(texts[locale].status_draft))
    }
})
