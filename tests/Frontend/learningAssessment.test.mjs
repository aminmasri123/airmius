import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createRenderer, h, ref, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'

const root = new URL('../../', import.meta.url)
const url = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
const copy = JSON.parse(await readFile(new URL('resources/data/learning-assessment-copy.json', root)))
const helper = url((await readFile(new URL('resources/js/support/learningAssessment.js', root), 'utf8')).replace(/import copy from [^\n]+/, `const copy = ${JSON.stringify(copy)}`))
const helpers = await import(helper)
const modules = {}
async function load(name) {
    if (modules[name]) return modules[name]
    const { descriptor } = parse(await readFile(new URL(`resources/js/Components/Learning/${name}.vue`, root), 'utf8'))
    let source = compileScript(descriptor, { id: name, inlineTemplate: true }).content
    for (const dep of ['vue', 'vue-i18n']) source = source.replaceAll(`from '${dep}'`, `from '${import.meta.resolve(dep)}'`).replaceAll(`from "${dep}"`, `from '${import.meta.resolve(dep)}'`)
    source = source.replaceAll("from '@/support/learningAssessment'", `from '${helper}'`)
    for (const match of [...source.matchAll(/from '\.\/(\w+)\.vue'/g)]) source = source.replace(match[0], `from '${await load(match[1])}'`)
    return modules[name] = url(source)
}
const Editor = (await import(await load('LearningAssessmentEditor'))).default
const Answers = (await import(await load('LearningAssessmentAnswers'))).default
const Review = (await import(await load('LearningQuizReview'))).default
const node = (type, text = '') => ({ type, tagName: type.toUpperCase(), text, props: {}, children: [], parent: null, value: '', get options() { return this.children.filter(el => el.type === 'option') }, addEventListener() {}, removeEventListener() {}, getRootNode() { return {} } })
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
function mount(component, props, locale = 'en') {
    const host = node('root')
    const app = renderer.createApp({ render: () => h(component, props()) })
    app.use(createI18n({ legacy: false, locale, messages: {} })); app.mount(host)
    return { host, app }
}
globalThis.Document = class {}
globalThis.ShadowRoot = class {}
globalThis.document = { activeElement: null }

for (const locale of ['de', 'en', 'fr', 'ar']) {
    test(`web editor supports multiple editable questions and single-answer selection in ${locale}`, async () => {
        const questions = ref([helpers.newAssessmentQuestion()])
        const { host, app } = mount(Editor, () => ({ modelValue: questions.value, solutions: true, courseId: 1, 'onUpdate:modelValue': v => { questions.value = v } }), locale)
        try {
            const buttons = label => walk(host).filter(el => el.type === 'button' && text(el) === label)
            const correct = () => walk(host).filter(el => el.type === 'input' && el.props.type === 'radio')
            correct()[0].props.onChange({ target: { checked: true } }); await nextTick()
            correct()[1].props.onChange({ target: { checked: true } }); await nextTick()
            assert.deepEqual([...questions.value[0].correct_options], [questions.value[0].options[1].id])
            buttons(copy[locale].add_question)[0].props.onClick(); await nextTick()
            assert.equal(questions.value.length, 2)
            const selects = walk(host).filter(el => el.type === 'select')
            selects[1].props['onUpdate:modelValue']('long_text'); selects[1].props.onChange(); await nextTick()
            assert.equal(questions.value[1].question_type, 'long_text')
            assert.ok(text(host).includes(copy[locale].manual_hint))
            buttons(copy[locale].remove_question)[0].props.onClick(); await nextTick()
            assert.equal(questions.value.length, 1)
            assert.equal(questions.value[0].question_type, 'long_text')
        } finally { app.unmount() }
    })
}

test('web participant retains text, single/multiple selections and ordered sequence', async () => {
    const options = [{ id: 'b', text: 'Second' }, { id: 'a', text: 'First' }]
    const questions = ['short_text', 'long_text', 'single_choice', 'multiple_choice', 'ordering'].map((type, id) => ({ id: id + 1, question: type, question_type: type, options }))
    const answers = ref({})
    const { host, app } = mount(Answers, () => ({ questions, modelValue: answers.value, 'onUpdate:modelValue': v => { answers.value = v } }))
    try {
        await nextTick()
        walk(host).find(el => el.type === 'input' && el.props['aria-label'] === 'short_text').props.onInput({ target: { value: 'Short answer' } })
        await nextTick()
        walk(host).find(el => el.type === 'textarea').props.onInput({ target: { value: 'Long answer' } }); await nextTick()
        const radios = walk(host).filter(el => el.type === 'input' && el.props.type === 'radio')
        radios[0].props.onChange({ target: { checked: true } }); await nextTick()
        radios[1].props.onChange({ target: { checked: true } }); await nextTick()
        for (const checkbox of walk(host).filter(el => el.type === 'input' && el.props.type === 'checkbox')) { checkbox.props.onChange({ target: { checked: true } }); await nextTick() }
        walk(host).filter(el => el.type === 'button' && el.props['aria-label'] === copy.en.up)[1].props.onClick(); await nextTick()
        assert.deepEqual(answers.value, { 1: 'Short answer', 2: 'Long answer', 3: ['a'], 4: ['b', 'a'], 5: ['a', 'b'] })
    } finally { app.unmount() }
})

test('web manual review waits for every long answer and emits explicit grades', async () => {
    const emitted = []
    const { host, app } = mount(Review, () => ({ attempt: { id: 2, question_snapshot: [{ id: 4, question_type: 'long_text', question: 'Explain' }], answers: { 4: 'Response' }, user: { name: 'Student' } }, onGrade: v => emitted.push(v) }))
    try {
        const button = () => walk(host).find(el => el.type === 'button')
        assert.equal(button().props.disabled, true)
        walk(host).find(el => el.type === 'input').props['onUpdate:modelValue'](true); await nextTick()
        assert.equal(button().props.disabled, false)
        button().props.onClick(); assert.deepEqual(emitted[0].manual_grades, { 4: true })
    } finally { app.unmount() }
})

test('image-only questions and options are valid; empty content is rejected', () => {
    const q = helpers.newAssessmentQuestion()
    q.image_url = 'https://example.test/q.png'
    q.options.forEach(o => { o.image_url = 'https://example.test/o.png' })
    q.correct_options = [q.options[0].id]
    assert.equal(helpers.assessmentEditorError([q], true), null)
    q.options[0].image_url = null
    assert.equal(helpers.assessmentEditorError([q], true), 'text_or_image')
    q.options[0].text = 'Option'
    q.image_url = null
    assert.equal(helpers.assessmentEditorError([q], true), 'text_or_image')
})
