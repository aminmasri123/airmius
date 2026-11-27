import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { runInNewContext } from 'node:vm'
import { parse } from '@vue/compiler-sfc'

const source = await readFile(new URL('../../resources/js/Components/Ads/AdSlot.vue', import.meta.url), 'utf8')
const script = parse(source).descriptor.scriptSetup.content.replace(/^import .*$/gm, '')

function harness(response) {
    const calls = []
    const intervals = new Map()
    const timeouts = new Map()
    const listeners = new Map()
    const hooks = {}
    let intersection
    let next = response
    let id = 0
    const document = {
        visibilityState: 'visible',
        addEventListener: (event, callback) => listeners.set(event, callback),
        removeEventListener: (event) => listeners.delete(event),
    }
    const window = {
        axios: { get: async (url, options) => {
            calls.push({ url, placement: options.params.placement })
            if (next instanceof Error) throw next
            return { data: next }
        } },
        setTimeout: (fn, ms) => { timeouts.set(++id, { fn, ms }); return id },
        setInterval: (fn, ms) => { intervals.set(++id, { fn, ms }); return id },
    }
    const context = {
        document, window,
        defineProps: () => ({ placement: 'feed', variant: 'card', fallback: false }),
        ref: (value) => ({ value }),
        computed: (fn) => ({ get value() { return fn() } }),
        onMounted: (fn) => { hooks.mount = fn },
        onBeforeUnmount: (fn) => { hooks.unmount = fn },
        watch: (_, callback) => { hooks.update = callback },
        clearTimeout: (handle) => timeouts.delete(handle),
        clearInterval: (handle) => intervals.delete(handle),
        IntersectionObserver: class {
            constructor(callback) { intersection = callback }
            observe() {}
            disconnect() { hooks.disconnected = true }
        },
    }
    runInNewContext(`${script}\nglobalThis.slot = { ad, hasAd, imageRatio, slotElement, fetchAd }`, context)
    context.slot.slotElement.value = { getBoundingClientRect: () => ({ width: 500 }) }
    hooks.mount()
    return {
        ...context.slot, calls, timeouts, intervals, document, hooks, listeners,
        setResponse: (value) => { next = value },
        enter: async () => { intersection([{ isIntersecting: true }]); await new Promise(setImmediate) },
    }
}

const ad = (attributes = {}) => ({ id: 17, headline: 'Sport im Verein', click_url: '/ads/17/click', ...attributes })

test('web waits for a visible placement and renders every configured image format', async () => {
    const slot = harness(ad())
    assert.equal(slot.calls.length, 0)
    await slot.enter()
    assert.equal(slot.calls[0].placement, 'feed')
    assert.equal(slot.hasAd.value, true)
    for (const [format, ratio] of Object.entries({
        feed_square: '1 / 1', feed_portrait: '4 / 5', story_vertical: '9 / 16', banner_wide: '1200 / 628',
    })) {
        slot.setResponse(ad({ creative_format: format }))
        await slot.fetchAd()
        assert.equal(slot.imageRatio.value, ratio)
    }
    slot.hooks.unmount()
})

test('web revalidation clears paused campaigns and hides failed deliveries', async () => {
    const slot = harness(ad())
    await slot.enter()
    assert.equal(slot.hasAd.value, true)
    slot.setResponse({})
    await [...slot.intervals.values()][0].fn()
    assert.equal(slot.hasAd.value, false)
    slot.setResponse(new Error('offline'))
    await slot.fetchAd()
    assert.equal(slot.hasAd.value, false)
    slot.hooks.unmount()
})

test('web removes an ad at its deadline and rejects already expired responses', async () => {
    const slot = harness(ad({ ends_at: new Date(Date.now() + 5000).toISOString() }))
    await slot.enter()
    assert.equal(slot.hasAd.value, true)
    const timeout = [...slot.timeouts.values()][0]
    assert.ok(timeout.ms > 0 && timeout.ms <= 5000)
    slot.setResponse(ad({ ends_at: new Date(Date.now() - 1000).toISOString() }))
    timeout.fn()
    await new Promise(setImmediate)
    assert.equal(slot.hasAd.value, false)
    slot.hooks.unmount()
})

test('hidden pages do not poll and unmount releases timers and observers', async () => {
    const slot = harness(ad())
    await slot.enter()
    slot.document.visibilityState = 'hidden'
    await slot.fetchAd()
    assert.equal(slot.calls.length, 1)
    slot.setResponse({})
    slot.document.visibilityState = 'visible'
    slot.listeners.get('visibilitychange')()
    await new Promise(setImmediate)
    assert.equal(slot.hasAd.value, false)
    slot.hooks.unmount()
    assert.equal(slot.intervals.size, 0)
    assert.equal(slot.timeouts.size, 0)
    assert.equal(slot.listeners.size, 0)
    assert.equal(slot.hooks.disconnected, true)
})
