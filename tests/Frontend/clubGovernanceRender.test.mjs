import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const root = new URL('../../', import.meta.url)
const profile = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue', root), 'utf8')
const component = await readFile(new URL('resources/js/Components/Clubs/ClubGovernanceSection.vue', root), 'utf8')
const messages = JSON.parse(await readFile(new URL('resources/js/i18n/clubGovernanceLocalization.json', root), 'utf8'))

test('club profile embeds governance with body and responsibility management', () => {
    assert.match(profile, /ClubGovernanceSection/)
    assert.match(component, /loadGovernance/)
    assert.match(component, /saveBody/)
    assert.match(component, /saveAssignment/)
    assert.match(component, /deleteBody/)
    assert.match(component, /deleteAssignment/)
    assert.match(component, /governance\.value\.can_edit/)
    assert.match(component, /governance\.value\.can_delete/)
})

test('governance feature copy has complete DE EN FR and AR parity', () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[locale]).sort(), Object.keys(messages.de).sort())
        for (const key of ['title', 'board', 'committee', 'working_group', 'addAssignment', 'responsibilities']) {
            assert.equal(typeof messages[locale][key], 'string', `${locale}: ${key}`)
            assert.ok(messages[locale][key].length > 0, `${locale}: ${key}`)
        }
    }
})
