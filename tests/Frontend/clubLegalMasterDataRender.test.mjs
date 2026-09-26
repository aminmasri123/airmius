import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const root = new URL('../../', import.meta.url)
const profile = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue', root), 'utf8')

test('manager profile exposes structured private registry, tax and federation controls', () => {
    assert.match(profile, /v-if="viewer\.can_manage"/)
    assert.match(profile, /v-model="clubForm\.registry_authority"/)
    assert.match(profile, /v-model="clubForm\.tax_number"/)
    assert.match(profile, /v-model="clubForm\.tax_status"/)
    assert.match(profile, /clubForm\.federation_affiliations\.push/)
    assert.match(profile, /clubForm\.federation_affiliations\.length >= 20/)
})

test('new legal master data labels exist in every feature locale', async () => {
    const messages = JSON.parse(await readFile(new URL('resources/js/i18n/clubLegalMasterDataLocalization.json', root), 'utf8'))
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[locale]).sort(), Object.keys(messages.de).sort())
        for (const key of ['title', 'hint', 'registryAuthority', 'affiliations', 'taxStatus']) {
            assert.equal(typeof messages[locale][key], 'string', `${locale}: ${key}`)
            assert.ok(messages[locale][key].length > 0, `${locale}: ${key}`)
        }
    }
})
