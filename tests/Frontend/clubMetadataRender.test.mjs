import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const root = new URL('../../', import.meta.url)
const profile = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue', root), 'utf8')
const component = await readFile(new URL('resources/js/Components/Clubs/ClubMetadataSection.vue', root), 'utf8')
const editor = await readFile(new URL('resources/js/Components/Clubs/ClubMetadataSubjectEditor.vue', root), 'utf8')
const memberships = await readFile(new URL('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue', root), 'utf8')
const teams = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Teams/Index.vue', root), 'utf8')
const events = await readFile(new URL('resources/js/Pages/Auth/Dashboard/Events/Show.vue', root), 'utf8')
const inventory = await readFile(new URL('resources/js/Pages/Auth/Dashboard/ClubInventory/Index.vue', root), 'utf8')
const messages = JSON.parse(await readFile(new URL('resources/js/i18n/clubMetadataLocalization.json', root), 'utf8'))
const valueMessages = JSON.parse(await readFile(new URL('resources/js/i18n/clubMetadataValuesLocalization.json', root), 'utf8'))

test('club managers can configure fields categories and number range defaults', () => {
    assert.match(profile, /ClubMetadataSection/)
    assert.match(profile, /<ClubMetadataSection v-if="viewer\.can_view_metadata"/)
    assert.match(component, /saveField/)
    assert.match(component, /saveCategory/)
    assert.match(component, /saveRange/)
    assert.match(component, /toggleDefault/)
    assert.match(component, /custom-fields/)
    assert.match(component, /number-ranges/)
    assert.match(component, /const canEdit = computed/)
    assert.match(component, /const canDelete = computed/)
    assert.match(component, /v-if="canEdit"/)
    assert.match(component, /v-if="canDelete"/)
})

test('one typed subject editor is wired to members teams events and inventory', () => {
    assert.match(memberships, /subject-type="member"/)
    assert.match(memberships, /subject-type="external_member"/)
    assert.match(teams, /subject-type="team"/)
    assert.match(events, /subject-type="event"/)
    assert.match(inventory, /subject-type="inventory_item"/)
    assert.match(editor, /field\.field_type === 'textarea'/)
    assert.match(editor, /field\.field_type === 'select'/)
    assert.match(editor, /field\.field_type === 'boolean'/)
    assert.match(editor, /field\.is_sensitive/)
    assert.match(editor, /field\.is_required/)
    assert.match(editor, /field\.is_active/)
    assert.match(editor, /category\.is_active/)
})

test('metadata management exposes labelled tabs feedback and labelled controls', () => {
    assert.match(component, /role="tablist"/)
    assert.match(component, /role="tabpanel"/)
    assert.match(component, /role="alert"/)
    assert.match(component, /aria-selected/)
    assert.match(component, /<label/)
    assert.match(component, /:disabled="saving"/)
})

test('metadata copy has complete DE EN FR and AR parity', () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        assert.deepEqual(Object.keys(messages[locale]).sort(), Object.keys(messages.de).sort())
        assert.deepEqual(Object.keys(valueMessages[locale]).sort(), Object.keys(valueMessages.de).sort())
        for (const key of ['title', 'fields', 'categories', 'ranges', 'sensitive', 'setDefault']) {
            assert.equal(typeof messages[locale][key], 'string', `${locale}: ${key}`)
            assert.ok(messages[locale][key].length > 0, `${locale}: ${key}`)
        }
    }
})
