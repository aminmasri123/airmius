import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createSSRApp } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createI18n } from 'vue-i18n'

const root = new URL('../../', import.meta.url)
const source = await readFile(new URL('resources/js/Components/ClubMemberships/ClubSepaReturnImport.vue', root), 'utf8')
const texts = JSON.parse(await readFile(new URL('resources/js/i18n/sepaReturnImportLocalization.json', root), 'utf8'))
const { descriptor } = parse(source)
let script = compileScript(descriptor, { id: 'sepa-return-import-test', inlineTemplate: true }).content
script = script.replace(/from ["']vue["']/g, `from '${import.meta.resolve('vue')}'`)
    .replace(/from ["']vue-i18n["']/g, `from '${import.meta.resolve('vue-i18n')}'`)
    .replace("import translations from '@/i18n/sepaReturnImportLocalization.json'", `const translations = ${JSON.stringify(texts)}`)
const Component = (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default

async function render(locale) {
    const app = createSSRApp(Component, { clubId: 1, batchId: 2, disabled: false })
    app.use(createI18n({ legacy: false, locale, messages: {} }))
    return renderToString(app)
}

test('return import accepts CSV and ISO 20022 XML without weakening explicit preview', async () => {
    const html = await render('de')
    assert.match(html, /accept="\.csv,\.xml,text\/csv,application\/xml,text\/xml"/)
    assert.match(html, /pain\.002/)
    assert.match(html, /camt\.053\/054/)
    assert.match(html, /Vorschau prüfen/)
    assert.match(source, /c\('apply'\)/)
})

test('all supported locales expose the same XML import labels', async () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(texts[locale]).sort(), Object.keys(texts.de).sort())
        const html = await render(locale)
        assert.ok(html.includes(texts[locale].help))
        assert.ok(html.includes(texts[locale].file))
    }
})
