import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createRenderer, h, ref, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import axios from 'axios'

const root = new URL('../../', import.meta.url)
const source = await readFile(new URL('resources/js/Components/Rides/RideLocationField.vue', root), 'utf8')
const { descriptor } = parse(source)
let script = compileScript(descriptor, { id: 'ride-location-test', inlineTemplate: true }).content
for (const dependency of ['vue', 'vue-i18n', 'axios']) script = script.replaceAll(`from '${dependency}'`, `from '${import.meta.resolve(dependency)}'`).replaceAll(`from "${dependency}"`, `from '${import.meta.resolve(dependency)}'`)
script = script.replace("from '@/support/rideLocations'", `from '${new URL('resources/js/support/rideLocations.js', root).href}'`)
const Component = (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default
const node = (type, text = '') => ({ type, text, props: {}, children: [], parent: null })
const renderer = createRenderer({
    createElement: type => node(type), createText: text => node('#text', text), createComment: text => node('#comment', text),
    setText: (el, text) => { el.text = text }, setElementText: (el, text) => { el.text = text; el.children = [] },
    patchProp: (el, key, previous, value) => { el.props[key] = value }, parentNode: el => el.parent,
    nextSibling: el => el.parent?.children[el.parent.children.indexOf(el) + 1] || null,
    remove(el) { if (el.parent) el.parent.children.splice(el.parent.children.indexOf(el), 1) },
    insert(el, parent, anchor) { if (el.parent) el.parent.children.splice(el.parent.children.indexOf(el), 1); el.parent = parent; const i = anchor ? parent.children.indexOf(anchor) : -1; parent.children.splice(i < 0 ? parent.children.length : i, 0, el) },
})
const walk = el => [el, ...el.children.flatMap(walk)]
const text = el => el.text + el.children.map(text).join('')
const pause = async () => { await new Promise(resolve => setTimeout(resolve, 480)); await nextTick() }

test('web address suggestions debounce, ignore obsolete replies, select and recover from failures', async () => {
    const previousGet = axios.get, previousRoute = globalThis.route
    const requests = []
    axios.get = (url, options) => new Promise((resolve, reject) => requests.push({ resolve, reject, options }))
    globalThis.route = () => '/rides/locations'
    const value = ref(''), selected = ref(null), host = node('root')
    const app = renderer.createApp({ render: () => h(Component, { modelValue: value.value, label: 'Start', area: true, 'onUpdate:modelValue': v => { value.value = v; selected.value = null }, onSelect: v => { selected.value = v } }) })
    app.use(createI18n({ legacy: false, locale: 'de', messages: {} })); app.mount(host)
    const input = () => walk(host).find(el => el.type === 'input')
    const type = async value => { input().props.onInput({ target: { value } }); await nextTick() }
    try {
        await type('M'); await type('Me'); await type('Metz'); await pause()
        assert.equal(requests.length, 1)
        assert.equal(requests[0].options.params.area, 1)
        await type('Paris'); await pause()
        requests[0].resolve({ data: { data: [{ label: 'Obsolete' }] } }); await nextTick(); await nextTick()
        assert.doesNotMatch(text(host), /Obsolete/)
        const place = { label: 'Paris, France', city: 'Paris', latitude: 48.85, longitude: 2.35 }
        requests[1].resolve({ data: { data: [place] } }); await nextTick(); await nextTick()
        walk(host).find(el => el.type === 'button').props.onClick(); await nextTick()
        assert.equal(value.value, place.label); assert.deepEqual(selected.value, place)
        await type('Missing'); await pause(); requests[2].reject(new Error('provider secret')); await nextTick(); await nextTick()
        assert.match(text(host), /Adresssuche derzeit nicht erreichbar/); assert.doesNotMatch(text(host), /provider secret/)
        await type(''); assert.equal(selected.value, null); assert.doesNotMatch(text(host), /Adresssuche derzeit nicht erreichbar/)
    } finally { app.unmount(); axios.get = previousGet; globalThis.route = previousRoute }
})
