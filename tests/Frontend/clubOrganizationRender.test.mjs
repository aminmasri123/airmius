import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const root = new URL('../../', import.meta.url)
const profile = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue', root), 'utf8')
const messages = JSON.parse(await readFile(new URL('resources/js/i18n/clubOrganizationLocalization.json', root), 'utf8'))

test('club profile manages organization units and preserves server visibility', () => {
    assert.match(profile, /loadOrganization/)
    assert.match(profile, /organization\.value\.can_manage/)
    assert.match(profile, /organization\.value\.can_edit_team_assignments/)
    assert.match(profile, /saveOrganizationItem\('departments'/)
    assert.match(profile, /saveOrganizationItem\('locations'/)
    assert.match(profile, /saveOrganizationItem\('training-groups'/)
    assert.match(profile, /saveTeamAssignment/)
    assert.match(profile, /selectTrainingGroup/)
})

test('organization feature copy has complete DE EN FR and AR parity', () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[locale]).sort(), Object.keys(messages.de).sort())
        for (const key of ['title', 'departments', 'locations', 'trainingGroups', 'assignmentSave']) {
            assert.equal(typeof messages[locale][key], 'string', `${locale}: ${key}`)
            assert.ok(messages[locale][key].length > 0, `${locale}: ${key}`)
        }
    }
})
