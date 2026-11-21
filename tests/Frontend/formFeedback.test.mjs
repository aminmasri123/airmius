import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile, readdir } from 'node:fs/promises'
import { parse, compileTemplate } from '@vue/compiler-sfc'
import { parse as parseTemplate } from '@vue/compiler-dom'
import { instrumentFormFeedback } from '../../resources/js/support/formFeedbackCompiler.js'
import { formFeedbackText, formFailureText, safeFieldMessage } from '../../resources/js/services/formFeedbackMessages.js'

test('feedback supports all application languages and hides technical server output', () => {
    for (const language of ['de-DE', 'en-US', 'fr', 'ar']) {
        assert.ok(formFeedbackText('network', language))
        for (const status of [0, 401, 403, 404, 419, 422, 429, 500]) assert.ok(formFailureText(status, language))
        for (const value of ['<html>Error</html>', 'SQLSTATE[23000]', 'Exception in /var/www/vendor/a.php']) {
            assert.equal(safeFieldMessage(value, language), formFeedbackText('invalid', language))
        }
    }
    assert.equal(safeFieldMessage(['Name already exists'], 'en'), 'Name already exists')
})

test('compiler maps repeated rows, dynamic keys and per-record form getters', () => {
    const source = `<form><div v-for="(question, i) in form.questions"><input v-model="question.options[option.id].text"></div><input v-model="formFor(record).name"><input v-model="forms[record.id].email"></form>`
    const result = instrumentFormFeedback(source)
    assert.equal((result.match(/v-field-feedback=/g) || []).length, 3)
    assert.match(result, /questions.*i.*options.*option.id.*text/)
    assert.match(result, /formFor\(record\)/)
    assert.deepEqual(compileTemplate({ source: result, filename: 'Test.vue', id: 'test' }).errors, [])
    assert.equal(instrumentFormFeedback(result), result, 'instrumentation is idempotent')
})

test('every native model control in the web application gets field feedback and compiles', async () => {
    let pages = 0, forms = 0, fields = 0
    const visit = async directory => {
        for (const entry of await readdir(directory, { withFileTypes: true })) {
            const path = new URL(`${entry.name}${entry.isDirectory() ? '/' : ''}`, directory)
            if (entry.isDirectory()) { await visit(path); continue }
            if (!entry.name.endsWith('.vue')) continue
            const template = parse(await readFile(path, 'utf8')).descriptor.template?.content
            if (!template) continue
            pages++
            const result = instrumentFormFeedback(template)
            const walk = node => {
                if (node.tag === 'form') forms++
                if (['input', 'select', 'textarea'].includes(node.tag) && node.props.some(p => p.type === 7 && p.name === 'model')) {
                    fields++
                    assert.ok(node.props.some(p => p.type === 7 && p.name === 'field-feedback'), `${path}: ${node.loc.source}`)
                }
                for (const child of node.children || []) walk(child)
            }
            walk(parseTemplate(result))
            assert.deepEqual(compileTemplate({ source: result, filename: path.pathname, id: 'audit' }).errors, [], `${path}`)
        }
    }
    await visit(new URL('../../resources/js/', import.meta.url))
    assert.ok(forms > 300 && fields > 2900)
    console.log(`Covered ${pages} Vue templates, ${forms} forms and ${fields} native model fields`)
})
