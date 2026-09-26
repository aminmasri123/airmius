import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = process.cwd()
const component = fs.readFileSync(path.join(root, 'resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue'), 'utf8')
const translations = JSON.parse(fs.readFileSync(path.join(root, 'resources/js/i18n/contributionPolicyLinkLocalization.json'), 'utf8'))

test('contribution rules expose and submit the formal model version', () => {
  assert.match(component, /contribution_policy_documents/)
  assert.match(component, /club_policy_document_id/)
  assert.match(component, /rule\.policy_document\.version_label/)
  assert.match(component, /errors\.club_policy_document_id/)
})

test('formal contribution model controls are translated in every supported language', () => {
  for (const language of ['de', 'en', 'fr', 'ar']) {
    assert.ok(translations[language])
    for (const key of ['model', 'noLink', 'linked']) {
      assert.equal(typeof translations[language][key], 'string')
      assert.ok(translations[language][key].trim().length > 0)
    }
  }
})
