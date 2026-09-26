import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'

const root = new URL('../../', import.meta.url)
const page = await readFile(new URL('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue', root), 'utf8')
const component = await readFile(new URL('resources/js/Components/ClubMemberships/ClubMembershipProspects.vue', root), 'utf8')
const messages = JSON.parse(await readFile(new URL('resources/js/i18n/clubMembershipProspectsLocalization.json', root), 'utf8'))

test('membership workspace exposes the prospect and trial workflow', () => {
    assert.match(page, /ClubMembershipProspects/)
    assert.match(page, /activeTab === 'prospects'/)
    assert.match(component, /membership-prospects\.store/)
    assert.match(component, /membership-prospects\.update/)
    assert.match(component, /membership-prospects\.archive/)
    assert.match(component, /type="datetime-local"/)
    assert.match(component, /trial_outcome/)
    assert.match(component, /club_membership_type_id/)
    assert.match(component, /team_id/)
})

test('prospect workflow ships complete translations for all supported locales', () => {
    const keys = Object.keys(messages.de)
    assert.ok(keys.length >= 20)
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[locale]), keys)
        assert.ok(Object.values(messages[locale]).every(value => typeof value === 'string' && value.trim().length > 0))
    }
})
