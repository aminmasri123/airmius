import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'

const root = new URL('../../', import.meta.url)
const copy = JSON.parse(await readFile(new URL('resources/data/ride-pricing-copy.json', root)))
const currencyData = JSON.parse(await readFile(new URL('resources/data/ride-currencies.json', root)))
const asModule = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
const helperSource = (await readFile(new URL('resources/js/support/ridePricing.js', root), 'utf8'))
    .replace(/import copy from [^\n]+/, `const copy = ${JSON.stringify(copy)}`)
    .replace(/import currencyData from [^\n]+/, `const currencyData = ${JSON.stringify(currencyData)}`)
const helperUrl = asModule(helperSource)
const { ridePriceLabel, ridePricingText, rideCurrencyCatalog } = await import(helperUrl)
async function component(name) {
    const source = await readFile(new URL(`resources/js/Components/Rides/${name}.vue`, root), 'utf8')
    const { descriptor } = parse(source)
    let script = compileScript(descriptor, { id: name, inlineTemplate: true }).content
    for (const dep of ['vue', 'vue-i18n']) {
        script = script.replaceAll(`from '${dep}'`, `from '${import.meta.resolve(dep)}'`).replaceAll(`from "${dep}"`, `from '${import.meta.resolve(dep)}'`)
    }
    return (await import(asModule(script.replaceAll("from '@/support/ridePricing'", `from '${helperUrl}'`)))).default
}
const Summary = await component('RidePriceSummary')
const Fields = await component('RidePriceFields')
async function render(Component, props, locale = 'de') {
    const app = createSSRApp({ render: () => h(Component, props) })
    app.use(createI18n({ legacy: false, locale, messages: {} }))
    return renderToString(app)
}

test('international money labels preserve ISO currency and currency precision', () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        const yen = ridePriceLabel({ mode: 'contribution', currency: 'JPY', digits: 0, amount: '1200' }, locale)
        assert.match(yen, /JPY/)
        const dinar = ridePriceLabel({ mode: 'contribution', currency: 'KWD', digits: 3, amount: '1.234' }, locale)
        assert.match(dinar, /KWD/)
        assert.match(dinar, /234|٢٣٤/)
        assert.equal(ridePriceLabel({}, locale), copy[locale].unspecified)
        assert.notEqual(ridePriceLabel({}, locale), copy[locale].free)
        assert.deepEqual(Object.keys(copy[locale]).sort(), Object.keys(copy.en).sort())
        for (const value of Object.values(copy[locale])) assert.ok(value.length > 0)
    }
    assert.equal(ridePricingText('fr-FR', 'free'), copy.fr.free)
})

test('missing server currency catalog falls back to CLDR and the account country', async () => {
    for (const [country, currency] of Object.entries({ DE: 'EUR', MA: 'MAD', JP: 'JPY', KW: 'KWD' })) {
        const catalog = rideCurrencyCatalog({}, country)
        assert.equal(catalog.default_currency, currency)
        assert.ok(Object.keys(catalog.currencies).length > 100)
    }
    assert.equal(rideCurrencyCatalog({}, null).default_currency, null)
    const html = await render(Fields, { form: { cost_mode: 'contribution', cost_currency: '', cost_amount: '10', errors: {} }, catalog: {} })
    assert.match(html, /value="EUR"/)
    assert.match(html, /value="MAD"/)
    assert.match(html, /value="JPY"/)
})

test('web summary displays both the agreed and changed price before confirmation', async () => {
    const html = await render(Summary, { pricing: {
        mode: 'contribution', currency: 'MAD', amount: '125.50', digits: 2, requires_confirmation: true,
        agreed: { mode: 'contribution', currency: 'EUR', amount: '10.00', digits: 2 },
    } })
    assert.match(html, /MAD/)
    assert.match(html, /EUR/)
    assert.match(html, /125,50/)
    assert.match(html, /10,00/)
    assert.ok(html.includes(copy.de.changed))
    assert.ok(html.includes(copy.de.update_warning))
    assert.ok(html.includes(copy.de.direct_payment))
})

test('web editor requires a mode and offers international currencies with search', async () => {
    const catalog = { default_currency: 'MAD', currencies: { EUR: 2, MAD: 2, JPY: 0, KWD: 3 } }
    const form = { cost_mode: 'contribution', cost_currency: 'MAD', cost_amount: '12.50', errors: {} }
    const html = await render(Fields, { form, catalog })
    assert.ok(html.includes(copy.de.country_hint))
    assert.match(html, /type="search"/)
    for (const code of Object.keys(catalog.currencies)) assert.ok(html.includes(`value="${code}"`))
    const empty = await render(Fields, { form: { ...form, cost_mode: '' }, catalog })
    assert.match(empty, /<select[^>]*required/)
    assert.ok(empty.includes(copy.de.choose_mode))
    const free = await render(Summary, { pricing: { mode: 'free' } }, 'fr')
    assert.ok(free.includes(copy.fr.free))
    assert.ok(!free.includes(copy.fr.changed))
})
