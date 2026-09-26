import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const root = new URL('../../', import.meta.url)
const profile = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue', root), 'utf8')
const messages = JSON.parse(await readFile(new URL('resources/js/i18n/clubBrandingLocalization.json', root), 'utf8'))

test('club profile supports colors letterhead and bounded document templates', () => {
    assert.match(profile, /brand_primary_color/)
    assert.match(profile, /letterhead_settings\.show_logo/)
    assert.match(profile, /clubForm\.document_templates\.length >= 20/)
    assert.match(profile, /setDefaultDocumentTemplate/)
    assert.match(profile, /template\.is_default/)
})

test('branding feature copy has complete DE EN FR and AR parity', () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[locale]).sort(), Object.keys(messages.de).sort())
        for (const key of ['title', 'palette', 'letterhead', 'templates', 'default']) {
            assert.equal(typeof messages[locale][key], 'string', `${locale}: ${key}`)
            assert.ok(messages[locale][key].length > 0, `${locale}: ${key}`)
        }
    }
})
