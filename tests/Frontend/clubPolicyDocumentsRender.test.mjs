import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const root = new URL('../../', import.meta.url)
const profile = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue', root), 'utf8')
const component = await readFile(new URL('resources/js/Components/Clubs/ClubPolicyDocumentsSection.vue', root), 'utf8')
const messages = JSON.parse(await readFile(new URL('resources/js/i18n/clubPolicyDocumentsLocalization.json', root), 'utf8'))

test('club profile embeds versioned policy document management', () => {
    assert.match(profile, /ClubPolicyDocumentsSection/)
    assert.match(component, /loadDocuments/)
    assert.match(component, /saveDocument/)
    assert.match(component, /deleteDocument/)
    assert.match(component, /uploadFile/)
    assert.match(component, /scope', 'club'/)
    assert.match(component, /state\.value\.can_edit/)
    assert.match(component, /state\.value\.can_delete/)
    assert.match(component, /state\.value\.can_download/)
    assert.match(component, /document\.file\.download_url/)
    assert.match(component, /\['statutes', 'regulation', 'contribution_model'\]/)
    assert.doesNotMatch(component, /localStorage|offline|retry\s*:/i)
})

test('policy document feature copy has complete DE EN FR and AR parity', () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[locale]).sort(), Object.keys(messages.de).sort())
        for (const key of ['title', 'statutes', 'regulation', 'contribution_model', 'validFrom', 'public', 'download']) {
            assert.equal(typeof messages[locale][key], 'string', `${locale}: ${key}`)
            assert.ok(messages[locale][key].length > 0, `${locale}: ${key}`)
        }
    }
})
