import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createSSRApp } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createI18n } from 'vue-i18n'

const root = new URL('../../', import.meta.url)
const texts = JSON.parse(await readFile(new URL('resources/js/i18n/sepaBatchLocalization.json', root), 'utf8'))
const source = await readFile(new URL('resources/js/Components/ClubMemberships/ClubSepaBatches.vue', root), 'utf8')
const { descriptor } = parse(source.replace('</script>', `
import { onServerPrefetch } from 'vue'
onServerPrefetch(async () => { await load(); collectionDate.value = '2026-10-10' })
</script>`))
let script = compileScript(descriptor, { id: 'sepa-batch-workflow-test', inlineTemplate: true }).content
script = script.replace(/from ["']vue["']/g, `from '${import.meta.resolve('vue')}'`)
    .replace(/from ["']vue-i18n["']/g, `from '${import.meta.resolve('vue-i18n')}'`)
    .replace("import { usePage } from '@inertiajs/vue3'", 'const usePage = () => ({ props: { auth: { user: { id: 7 } } } })')
    .replace("import translations from '@/i18n/sepaBatchLocalization.json'", `const translations = ${JSON.stringify(texts)}`)
    .replace(/import (ClubSepa\w+) from '\.\/[^']+'/g, 'const $1 = { render: () => null }')
const Component = (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default

async function render(options = {}, invoices = [], locale = 'de') {
    const { status = 'draft', ...permissions } = options
    globalThis.window = { axios: { get: async () => ({ data: {
        can_manage: false, can_approve: false, can_export: false, approval_mode: 'dual',
        reserved_invoice_ids: [], ...permissions,
        data: { current_page: 1, next_page_url: null, data: [{
            id: 1, reference: 'AIR-TEST', status, created_by: 7, creditor_name: 'Club',
            creditor_id: 'DE-TEST', collection_date: '2026-10-10', total_cents: 8000, items: [],
        }] },
    } }) } }
    try {
        const app = createSSRApp(Component, { clubId: 1, invoices })
        app.use(createI18n({ legacy: false, locale, messages: {} }))
        return await renderToString(app)
    } finally { delete globalThis.window }
}

test('separate export permission controls downloads without granting management', async () => {
    const exporter = await render({ can_export: true, status: 'notified' })
    assert.match(exporter, />Bankdatei herunterladen<\/button>/)
    assert.doesNotMatch(exporter, />Lauf vorbereiten<\/button>/)
    const manager = await render({ can_manage: true, status: 'notified' })
    assert.doesNotMatch(manager, />Bankdatei herunterladen<\/button>/)
})

test('single approval offers explicit review and dual approval prevents approving own draft', async () => {
    const dual = await render({ can_approve: true })
    assert.match(dual, /Eine andere berechtigte Person muss freigeben/)
    assert.doesNotMatch(dual, />Freigeben<\/button>/)
    const single = await render({ can_approve: true, approval_mode: 'single' })
    assert.match(single, /Ich habe Empfänger, Beträge und Einzugsdatum/)
    assert.match(single, /<button[^>]*disabled[^>]*>Freigeben<\/button>/)
})

test('selection excludes reserved, paid, zero and later due invoices', async () => {
    const invoice = { status: 'open', amount: '100.00', outstanding_amount: '80.00', due_date: '2026-10-10' }
    const html = await render({ can_manage: true, reserved_invoice_ids: [2] }, [
        { ...invoice, id: 1, number: 'ELIGIBLE' },
        { ...invoice, id: 2, number: 'RESERVED' },
        { ...invoice, id: 3, number: 'LATER', due_date: '2026-10-11' },
        { ...invoice, id: 4, number: 'PAID', status: 'paid' },
        { ...invoice, id: 5, number: 'ZERO', outstanding_amount: '0.00' },
    ])
    assert.match(html, /ELIGIBLE/)
    assert.match(html, /80,00/)
    for (const excluded of ['RESERVED', 'LATER', 'PAID', 'ZERO']) assert.ok(!html.includes(excluded))
})

test('workflow hints are translated in every supported language', async () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(texts[locale]).sort(), Object.keys(texts.de).sort())
        const html = await render({ can_manage: true }, [], locale)
        assert.ok(html.includes(texts[locale].exportHelp))
        assert.ok(html.includes(texts[locale].noEligible))
    }
})
