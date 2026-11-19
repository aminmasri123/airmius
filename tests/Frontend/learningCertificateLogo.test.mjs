import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createRenderer, h, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'

const root = new URL('../../', import.meta.url)
const copy = JSON.parse(await readFile(new URL('resources/js/i18n/learningContentLocalization.json', root)))
const { descriptor } = parse(await readFile(new URL('resources/js/Components/Learning/LearningCertificateLogoField.vue', root), 'utf8'))
let script = compileScript(descriptor, { id: 'certificate-logo-test', inlineTemplate: true }).content
for (const dep of ['vue', 'vue-i18n']) script = script.replaceAll(`from '${dep}'`, `from '${import.meta.resolve(dep)}'`).replaceAll(`from "${dep}"`, `from '${import.meta.resolve(dep)}'`)
script = script.replace(/import copy from [^\n]+/, `const copy = ${JSON.stringify(copy)}`)
const Component = (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default
const node = (type, text = '') => ({ type, text, children: [], props: {}, parent: null })
const renderer = createRenderer({
    createElement: type => node(type), createText: text => node('#text', text), createComment: text => node('#comment', text),
    setText: (el, text) => { el.text = text }, setElementText: (el, text) => { el.text = text; el.children = [] },
    patchProp: (el, key, prev, value) => { el.props[key] = value }, parentNode: el => el.parent,
    nextSibling: el => el.parent?.children[el.parent.children.indexOf(el) + 1] || null,
    remove(el) { if (el.parent) el.parent.children.splice(el.parent.children.indexOf(el), 1) },
    insert(el, parent, anchor) { if (el.parent) el.parent.children.splice(el.parent.children.indexOf(el), 1); el.parent = parent; const index = anchor ? parent.children.indexOf(anchor) : -1; parent.children.splice(index < 0 ? parent.children.length : index, 0, el) },
})
const walk = el => [el, ...el.children.flatMap(walk)]
const content = el => el.text + el.children.map(content).join('')

test('web logo upload updates the URL, reports busy state and safely recovers from rejection', async () => {
    const oldWindow = globalThis.window, oldRoute = globalThis.route
    const requests = [], changes = [], busy = []
    globalThis.route = name => name
    globalThis.window = { axios: { post: (url, body) => new Promise((resolve, reject) => requests.push({ url, body, resolve, reject })) } }
    const host = node('root')
    const app = renderer.createApp({ render: () => h(Component, { modelValue: 'https://example.test/old.png', 'onUpdate:modelValue': value => changes.push(value), onBusy: value => busy.push(value) }) })
    app.use(createI18n({ legacy: false, locale: 'de', messages: {} })); app.mount(host)
    const input = () => walk(host).find(el => el.type === 'input' && el.props.type === 'file')
    try {
        const target = { files: [new File(['logo'], 'logo.png', { type: 'image/png' })], value: 'logo.png' }
        const pending = input().props.onChange({ target })
        await nextTick()
        assert.equal(input().props.disabled, true)
        assert.equal(requests[0].url, 'auth.learning.studio.certificate-logo.store')
        assert.equal(requests[0].body.get('file').name, 'logo.png')
        requests[0].resolve({ data: { url: 'https://example.test/new.png' } })
        await pending; await nextTick()
        assert.deepEqual(changes, ['https://example.test/new.png'])
        assert.deepEqual(busy, [true, false])
        assert.equal(target.value, '')
        const retry = input().props.onChange({ target })
        requests[1].reject({ response: { data: { errors: { file: ['Invalid image'] } } } })
        await retry; await nextTick()
        assert.match(content(host), /Invalid image/)
        assert.equal(changes.length, 1)
        assert.equal(input().props.disabled, false)
        const large = { files: [new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'large.png')], value: 'large.png' }
        await input().props.onChange({ target: large }); await nextTick()
        assert.equal(requests.length, 2)
        assert.match(content(host), /5 MB/)
    } finally { app.unmount(); globalThis.window = oldWindow; globalThis.route = oldRoute }
})
