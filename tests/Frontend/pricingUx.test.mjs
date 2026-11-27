import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { runInNewContext } from 'node:vm'
import { computed, nextTick, reactive, ref } from 'vue'
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc'

const source = await readFile(new URL('../../resources/js/Pages/Guest/Pricing.vue', import.meta.url), 'utf8')
const pricingCopy = JSON.parse(await readFile(new URL('../../resources/js/Pages/Guest/pricingCopy.json', import.meta.url), 'utf8'))
const { descriptor } = parse(source)
const longNote = 'A requirement that must remain available in the full plan details. '.repeat(20)
const plans = [
    { id: 1, name: 'Free', features: ['Profile', 'Community', 'Training', 'Files', longNote] },
    { id: 2, name: 'Pro', features: ['Analysis', longNote] },
]

function pricing(search = '') {
    const props = reactive({ planGroups: { sportler: plans, verein: [{ id: 3, features: ['Members'] }] }, checkoutClubs: [] })
    return runInNewContext(`${descriptor.scriptSetup.content.replace(/^import .*$/gm, '')};
        ({ props, activeView, selectedAudience, selectedPlanId, detailPlan, visiblePlans, summaryFeatures, showDetails, selectView, detailTab, planTab, ui, tx, formatPrice })`, {
        computed, nextTick, ref, pricingCopy, URLSearchParams, Intl,
        defineProps: () => props, usePage: () => ({ props: { auth: { user: null } } }),
        useI18n: () => ({ locale: ref('de'), te: () => false, t: value => value }),
        window: { location: { search } },
    })
}

test('overview stays short while every full feature and limitation is retained', () => {
    const p = pricing()
    assert.equal(p.activeView.value, 'plans')
    assert.deepEqual(Array.from(p.summaryFeatures(plans[0])), ['Profile', 'Community', 'Training'])
    assert.equal(p.detailPlan.value.features.at(-1), longNote)
    assert.equal(p.detailPlan.value.features.length, plans[0].features.length)
})

test('details open the chosen plan and focus the active tab; changing audience cannot show a stale plan', async () => {
    const p = pricing()
    let focused = false
    p.detailTab.value = { focus: () => { focused = true } }
    await p.showDetails(plans[1])
    assert.equal(p.activeView.value, 'details')
    assert.equal(p.detailPlan.value.id, 2)
    assert.equal(focused, true)
    p.selectedAudience.value = 'verein'
    assert.equal(p.detailPlan.value.id, 3)
    p.selectedAudience.value = 'werbeagentur'
    assert.equal(p.detailPlan.value, undefined)
    p.selectedAudience.value = 'sportler'
    p.selectedPlanId.value = '1'
    assert.equal(p.detailPlan.value.id, 1)
})

test('audience deep links and untranslated country placeholders remain usable', () => {
    assert.equal(pricing('?audience=verein').selectedAudience.value, 'verein')
    assert.equal(pricing('?audience=invalid').selectedAudience.value, 'sportler')
    assert.equal(pricing().tx('Lokaler Preis für {country}', { country: 'DE' }), 'Lokaler Preis für DE')
})

test('prices retain cents instead of rounding a paid plan to whole euros', () => {
    assert.match(pricing().formatPrice(499, 'EUR'), /4,99/)
    assert.match(pricing().formatPrice(1299, 'EUR'), /12,99/)
})

test('all supported languages provide complete pricing navigation', () => {
    for (const language of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(pricingCopy[language]).sort(), Object.keys(pricingCopy.de).sort())
        assert.ok(Object.values(pricingCopy[language]).every(value => value.trim().length > 0))
    }
})

test('pricing script and template compile together', () => {
    const script = compileScript(descriptor, { id: 'pricing-ux' })
    const template = compileTemplate({ source: descriptor.template.content, filename: 'Pricing.vue', id: 'pricing-ux', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
})
