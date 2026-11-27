import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { runInNewContext } from 'node:vm'
import { computed, nextTick, reactive, ref } from 'vue'
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc'
import { splitPlanDetails, groupUsageNotes } from '../../resources/js/support/pricingDetails.js'

const source = await readFile(new URL('../../resources/js/Pages/Guest/Pricing.vue', import.meta.url), 'utf8')
const pricingCopy = JSON.parse(await readFile(new URL('../../resources/js/Pages/Guest/pricingCopy.json', import.meta.url), 'utf8'))
const { descriptor } = parse(source)
const longNote = 'A requirement that must remain available in the full plan details. '.repeat(20)
const plans = [
    { id: 1, name: 'Free', features: ['Profile', 'Community', 'Training', 'Files', longNote] },
    { id: 2, name: 'Pro', features: ['Analysis', longNote] },
]

function pricing(search = '', authenticated = false) {
    const props = reactive({ planGroups: { sportler: plans, verein: [{ id: 3, features: ['Members'] }] }, checkoutClubs: [] })
    return runInNewContext(`${descriptor.scriptSetup.content.replace(/^import .*$/gm, '')};
        ({ props, activeView, selectedAudience, selectedPlanId, detailPlan, visiblePlans, summaryFeatures, showDetails, selectView, selectAudience, selectDetailPlan, detailSearch, filteredFeatures, noteGroups, detailTab, planTab, ui, tx, formatPrice, requestCheckout, checkoutModal, checkoutCanSubmit, createCheckout })`, {
        computed, nextTick, ref, pricingCopy, splitPlanDetails, groupUsageNotes, URLSearchParams, Intl,
        defineProps: () => props, usePage: () => ({ props: { auth: { user: authenticated ? { id: 1 } : null } } }),
        useI18n: () => ({ locale: ref('de'), te: () => false, t: value => value }),
        window: { location: { search } },
        document: { cookie: '' }, route: name => `/${name}`,
        axios: { post: (url, payload, config) => ({ url, payload, config }) },
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
    const strings = value => Object.values(value).flatMap(item => typeof item === 'string' ? [item] : strings(item))
    for (const language of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(pricingCopy[language]).sort(), Object.keys(pricingCopy.de).sort())
        assert.deepEqual(Object.keys(pricingCopy[language].topics).sort(), Object.keys(pricingCopy.de.topics).sort())
        assert.ok(strings(pricingCopy[language]).every(value => value.trim().length > 0))
    }
})

test('catalog requirements are not mistaken for included features, even when short', () => {
    const included = ['Sportprofil', 'Eine lange Planleistung. '.repeat(20)]
    const notes = ['SEPA benötigt Vereinsplan Pro.', longNote]
    const plan = { included_feature_count: included.length, features: [...included, ...notes] }
    assert.deepEqual(splitPlanDetails(plan), { included, notes })
    const grouped = groupUsageNotes(notes)
    assert.deepEqual(grouped.flatMap(group => group.items).sort(), notes.slice().sort())
    assert.equal(grouped.find(group => group.key === 'finance').items[0], notes[0])
    assert.deepEqual(splitPlanDetails({ included_feature_count: 0, features: notes }).included, [])
})

test('detail search finds full notes and plan or audience changes clear stale filters', () => {
    const p = pricing()
    p.detailSearch.value = ' REQUIREMENT '
    assert.equal(p.filteredFeatures.value.length, 0)
    assert.equal(p.noteGroups.value.flatMap(group => group.items)[0], longNote)
    p.selectDetailPlan(2)
    assert.equal(p.detailSearch.value, '')
    assert.equal(p.filteredFeatures.value[0], 'Analysis')
    p.detailSearch.value = 'no matches at all'
    assert.equal(p.noteGroups.value.length, 0)
    p.selectAudience('verein')
    assert.equal(p.detailSearch.value, '')
    assert.equal(p.detailPlan.value.id, 3)
})

test('payment method selected in the dialog reaches checkout; consent and club remain required', () => {
    const p = pricing('', true)
    const plan = { id: 2, target_actor: 'sportler', monthly_price_cents: 499 }
    p.requestCheckout(plan, 'stripe')
    assert.equal(p.checkoutCanSubmit.value, false)
    p.checkoutModal.value.accepted = true
    const key = p.checkoutModal.value.requestId
    for (const provider of ['stripe', 'paypal', 'bank_transfer']) {
        p.checkoutModal.value.provider = provider
        const request = p.createCheckout(plan, 'test-csrf')
        assert.equal(request.payload.provider, provider)
        assert.equal(request.payload.accepted_terms, true)
        assert.equal(request.config.headers['Idempotency-Key'], key)
    }
    p.requestCheckout({ ...plan, target_actor: 'verein' }, 'stripe')
    p.checkoutModal.value.accepted = true
    assert.equal(p.checkoutCanSubmit.value, false)
    p.checkoutModal.value.clubId = 5
    assert.equal(p.checkoutCanSubmit.value, true)
})

test('pricing script and template compile together', () => {
    const script = compileScript(descriptor, { id: 'pricing-ux' })
    const template = compileTemplate({ source: descriptor.template.content, filename: 'Pricing.vue', id: 'pricing-ux', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
})
