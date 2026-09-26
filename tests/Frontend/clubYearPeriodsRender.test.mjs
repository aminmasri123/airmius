import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const root = new URL('../../', import.meta.url)
const profile = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue', root), 'utf8')
const component = await readFile(new URL('resources/js/Components/Clubs/ClubYearPeriodsSection.vue', root), 'utf8')
const membership = await readFile(new URL('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue', root), 'utf8')
const eventShow = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Events/Show.vue', root), 'utf8')
const messages = JSON.parse(await readFile(new URL('resources/js/i18n/clubYearPeriodsLocalization.json', root), 'utf8'))

test('club profile embeds independent year period management for members', () => {
    assert.match(profile, /ClubYearPeriodsSection/)
    assert.match(profile, /viewer\.is_member \|\| viewer\.can_manage/)
    assert.match(component, /loadPeriods/)
    assert.match(component, /savePeriod/)
    assert.match(component, /deletePeriod/)
    assert.match(component, /\['business', 'contribution', 'sport'\]/)
    assert.match(component, /state\.value\.can_edit/)
    assert.match(component, /state\.value\.can_delete/)
    assert.match(component, /state\.can_view_reports/)
    assert.match(component, /loadReport/)
    assert.match(component, /period_id/)
    assert.match(component, /unassigned/)
})

test('finance detail tables show stored period references and explicit historic gaps', () => {
    assert.match(membership, /invoice\.business_year_period\?\.name/)
    assert.match(membership, /invoice\.contribution_year_period\?\.name/)
    assert.match(membership, /entry\.business_year_period\?\.name/)
    assert.match(membership, /transaction\.business_year_period\?\.name/)
    assert.match(membership, /historically_unassigned/)
    assert.match(eventShow, /event\.sport_year_period\?\.name/)
    assert.match(eventShow, /Historisch unzugeordnet/)
})

test('year period feature copy has complete DE EN FR and AR parity', () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[locale]).sort(), Object.keys(messages.de).sort())
        for (const key of ['title', 'business', 'contribution', 'sport', 'current', 'report', 'unassignedSeparate']) {
            assert.equal(typeof messages[locale][key], 'string', `${locale}: ${key}`)
            assert.ok(messages[locale][key].length > 0, `${locale}: ${key}`)
        }
    }
})
