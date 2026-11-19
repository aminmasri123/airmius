import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createRenderer, h, ref, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
const root = new URL('../../', import.meta.url)
const copy = JSON.parse(await readFile(new URL('resources/data/learning-deletion-copy.json', root)))
const moduleUrl = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
const modalUrl = moduleUrl(`import {h} from '${import.meta.resolve('vue')}'; export default {props:['show'],setup: (props,{slots})=>()=>props.show?h('section',slots.default?.()):null}`)
const { descriptor } = parse(await readFile(new URL('resources/js/Components/Learning/LearningDeleteDialog.vue', root), 'utf8'))
let script = compileScript(descriptor, { id: 'learning-delete-test', inlineTemplate: true }).content
for (const dep of ['vue', 'vue-i18n']) script = script.replaceAll(`from '${dep}'`, `from '${import.meta.resolve(dep)}'`).replaceAll(`from "${dep}"`, `from '${import.meta.resolve(dep)}'`)
script = script.replace(/import copy from [^\n]+/, `const copy = ${JSON.stringify(copy)}`).replace("from '@/Components/Modal.vue'", `from '${modalUrl}'`)
const Component = (await import(moduleUrl(script))).default
const node = (type, text = '') => ({ type, tagName: type.toUpperCase(), text, props: {}, children: [], parent: null, value: '', addEventListener() {}, removeEventListener() {}, getRootNode() { return {} } })
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
for (const locale of ['de', 'en', 'fr', 'ar']) {
    test(`web deletion warns, requires exact confirmation, resets and cancels in ${locale}`, async () => {
        const oldDocument = globalThis.document
        const oldDocumentClass = globalThis.Document, oldShadowRoot = globalThis.ShadowRoot
        globalThis.Document = class {}
        globalThis.ShadowRoot = class {}
        globalThis.document = { activeElement: null }
        const target = ref({ kind: 'course', title: 'My course', id: 4 })
        const busy = ref(false), confirmed = [], host = node('root')
        let closed = 0
        const app = renderer.createApp({ render: () => h(Component, { target: target.value, busy: busy.value, onConfirm: value => confirmed.push(value), onClose: () => closed++ }) })
        app.use(createI18n({ legacy: false, locale, messages: {} })); app.mount(host)
        const button = () => walk(host).find(el => el.type === 'button' && text(el) === copy[locale].delete)
        const type = async value => { walk(host).find(el => el.type === 'input').props['onUpdate:modelValue'](value); await nextTick() }
        try {
            assert.ok(text(host).includes(copy[locale].warn_course))
            assert.equal(button().props.disabled, true)
            await type('delete'); assert.equal(button().props.disabled, true)
            await type('DELETE'); assert.equal(button().props.disabled, false)
            busy.value = true; await nextTick(); assert.equal(button().props.disabled, true)
            busy.value = false; await nextTick(); button().props.onClick()
            assert.equal(confirmed.length, 1)
            assert.equal(confirmed[0].id, 4)
            target.value = { kind: 'section', id: 8, title: 'My section' }; await nextTick()
            assert.ok(text(host).includes(copy[locale].warn_section))
            assert.equal(button().props.disabled, true)
            walk(host).find(el => el.type === 'button' && text(el) === copy[locale].cancel).props.onClick()
            assert.equal(closed, 1)
            assert.equal(confirmed.length, 1)
        } finally { app.unmount(); globalThis.document = oldDocument; globalThis.Document = oldDocumentClass; globalThis.ShadowRoot = oldShadowRoot }
    })
}
