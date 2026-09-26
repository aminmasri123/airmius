import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const profile = fs.readFileSync(
    new URL('../../resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue', import.meta.url),
    'utf8',
)
const translations = JSON.parse(fs.readFileSync(
    new URL('../../resources/js/i18n/clubMembershipChangeLocalization.json', import.meta.url),
    'utf8',
))

test('club profile exposes the reviewed membership type change flow', () => {
    assert.match(profile, /auth\.club-membership-change-requests\.store/)
    assert.match(profile, /viewer\.has_pending_membership_change_request/)
    assert.match(profile, /membershipChangeTypes/)
    assert.match(profile, /membershipChangeForm\.club_membership_type_id/)
    assert.match(profile, /membershipChangeForm\.club_department_id/)
    assert.match(profile, /viewer\.club_department_id/)
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        for (const key of ['title', 'hint', 'current', 'currentDepartment', 'unknownDepartment', 'target', 'department', 'submit', 'pending']) {
            assert.equal(typeof translations[locale]?.[key], 'string')
            assert.ok(translations[locale][key].trim().length > 0)
        }
    }
})
