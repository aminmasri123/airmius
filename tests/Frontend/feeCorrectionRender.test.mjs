import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createSSRApp } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createI18n } from 'vue-i18n'

const root = new URL('../../', import.meta.url)
const source = await readFile(new URL('resources/js/Components/ClubMemberships/ClubSepaFeeCorrection.vue', root), 'utf8')
const texts = JSON.parse(await readFile(new URL('resources/js/i18n/sepaFeeCorrectionLocalization.json', root), 'utf8'))
const { descriptor } = parse(source)
let script = compileScript(descriptor, { id: 'fee-correction-test', inlineTemplate: true }).content
script = script.replace(/from ["']vue["']/g, `from '${import.meta.resolve('vue')}'`)
    .replace(/from ["']vue-i18n["']/g, `from '${import.meta.resolve('vue-i18n')}'`)
    .replace("import translations from '@/i18n/sepaFeeCorrectionLocalization.json'", `const translations = ${JSON.stringify(texts)}`)
    .replace("from './feeCorrectionState'", `from '${new URL('resources/js/Components/ClubMemberships/feeCorrectionState.js', root).href}'`)
const Component = (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default
const item = { id: 1, settlement: { fee_entry: { id: 7, amount: '3.50', booked_on: '2026-10-11' },
    fee_corrections: [{ id: 1, revision: 1, previous_amount_cents: 350, amount_cents: 0, booked_on: '2026-10-12', reference: 'REF', reason: '<script>alert(1)</script>' }] } }
async function render(locale, overrides = {}) {
    const app = createSSRApp(Component, { clubId: 1, batchId: 1, item, available: true, canManage: false, ...overrides })
    app.use(createI18n({ legacy: false, locale, messages: {} }))
    return renderToString(app)
}

test('read-only roles see actual fee total and escaped history without correction controls', async () => {
    const html = await render('de')
    assert.match(html, /Aktueller Gebührenbetrag/); assert.match(html, /0,00/)
    assert.match(html, /Korrekturhistorie/); assert.match(html, /&lt;script&gt;/)
    assert.doesNotMatch(html, /<form|<button|<script/)
})

test('correction controls require schema availability and management rights', async () => {
    const writable = await render('de', { canManage: true })
    assert.match(writable, /<form/); assert.match(writable, /Begründung/); assert.match(writable, /Korrektur buchen/)
    assert.doesNotMatch(await render('de', { canManage: true, available: false }), /<form/)
})

test('all four languages supply identical keys and render their actual labels', async () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(texts[locale]).sort(), Object.keys(texts.de).sort())
        const html = await render(locale)
        assert.ok(html.includes(texts[locale].current)); assert.ok(html.includes(texts[locale].history))
    }
})
