import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const root = new URL('../../', import.meta.url)
const profile = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue', root), 'utf8')
const messages = JSON.parse(await readFile(new URL('resources/js/i18n/clubContactMasterDataLocalization.json', root), 'utf8'))

test('club profile provides managed contacts and explicit public controls', () => {
    assert.match(profile, /v-model="clubForm\.contact_email"/)
    assert.match(profile, /v-model="clubForm\.contact_details_public"/)
    assert.match(profile, /v-model="person\.is_public"/)
    assert.match(profile, /clubForm\.contact_persons\.length >= 20/)
    assert.match(profile, /rel="noopener noreferrer"/)
})

test('contact feature copy has complete DE EN FR and AR parity', () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[locale]).sort(), Object.keys(messages.de).sort())
        for (const key of ['title', 'publicHint', 'persons', 'personPublic', 'publicTitle']) {
            assert.equal(typeof messages[locale][key], 'string', `${locale}: ${key}`)
            assert.ok(messages[locale][key].length > 0, `${locale}: ${key}`)
        }
    }
})
