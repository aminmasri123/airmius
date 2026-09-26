import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = process.cwd()
const web = fs.readFileSync(path.join(root, 'resources/js/Components/ClubMemberships/ClubSepaBatches.vue'), 'utf8')
const mobile = fs.readFileSync(path.join(root, 'mobile/airmius_mobile/lib/screens/club_sepa_batches_screen.dart'), 'utf8')
const translations = JSON.parse(fs.readFileSync(path.join(root, 'resources/js/i18n/sepaBatchLocalization.json'), 'utf8'))

test('web and mobile distinguish provider feedback from transport acceptance', () => {
  for (const source of [web, mobile]) {
    assert.match(source, /provider_status/)
    assert.match(source, /provider_/)
    assert.match(source, /bounce_type/)
  }
  assert.match(web, /mail_\$\{notice\.status\}/)
  assert.match(web, /provider_\$\{notice\.provider_status\}/)
})

test('provider feedback labels exist in all supported languages', () => {
  for (const language of ['de', 'en', 'fr', 'ar']) {
    for (const key of ['provider_pending', 'provider_delivered', 'provider_bounced']) {
      assert.equal(typeof translations[language][key], 'string')
      assert.ok(translations[language][key].trim().length > 0)
    }
  }
})
