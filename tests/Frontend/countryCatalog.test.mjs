import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc'

test('country catalogue is complete and matches the offline Flutter asset', async () => {
    const web = JSON.parse(await readFile('resources/data/countries.json', 'utf8'))
    const mobile = JSON.parse(await readFile('mobile/airmius_mobile/assets/data/countries.json', 'utf8'))
    assert.deepEqual(web, mobile)
    assert.equal(web.length, 250)
    assert.equal(new Set(web.map(row => row.code)).size, 250)
    assert.equal(web.find(row => row.code === 'DE').names.de, 'Deutschland')
    for (const row of web) for (const locale of ['de', 'en', 'fr', 'ar']) assert.ok(row.names[locale])
})

test('country picker compiles with keyboard navigation and server-backed additions', async () => {
    const source = await readFile('resources/js/Components/CountrySelect.vue', 'utf8')
    const { descriptor, errors } = parse(source)
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'country-select' })
    const result = compileTemplate({ source: descriptor.template.content, filename: 'CountrySelect.vue', id: 'country-select', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(result.errors, [])
    assert.match(source, /keydown\.down/)
    assert.match(source, /keydown\.enter/)
    assert.match(source, /v-if="canManage"/)
})
