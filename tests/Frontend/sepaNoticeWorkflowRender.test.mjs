import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createSSRApp } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createI18n } from 'vue-i18n'

const root = new URL('../../', import.meta.url)
const source = await readFile(new URL('resources/js/Components/ClubMemberships/ClubSepaBatches.vue', root), 'utf8')
const translations = JSON.parse(await readFile(new URL('resources/js/i18n/sepaBatchLocalization.json', root), 'utf8'))

async function render({ status = 'prepared', windowOpen = true, providerStatus = null, locale = 'de', prepared = true } = {}) {
  const batch = {
    id: 1, reference: 'SEPA-TEST', status: 'approved', items: [], total_cents: 8000,
    collection_date: '2026-10-10', notice_days: 14, notice_deadline_on: '2026-09-26', notice_window_open: windowOpen,
    mailConfirmed: true, notice: {},
    notices: prepared ? [{ id: 1, status, provider_status: providerStatus,
      content: { email: 'payer@example.test', subject: '<script>unsafe</script>', body: '80 EUR' } }] : [],
  }
  const fixture = source.replace('const open = ref(false)', 'const open = ref(true)')
    .replace('batches = ref([])', `batches = ref(${JSON.stringify([batch]).replaceAll('<', '\\u003c')})`)
    .replace('const canManage = ref(false)', 'const canManage = ref(true)')
    .replace('const noticesAvailable = ref(false)', 'const noticesAvailable = ref(true)')
  const { descriptor } = parse(fixture)
  let script = compileScript(descriptor, { id: 'notice-workflow', inlineTemplate: true }).content
  script = script.replace(/from ['"]vue['"]/g, `from '${import.meta.resolve('vue')}'`)
    .replace(/from ['"]vue-i18n['"]/g, `from '${import.meta.resolve('vue-i18n')}'`)
    .replace("import { usePage } from '@inertiajs/vue3'", 'const usePage = () => ({props: {auth: {user: {id: 1}}}})')
    .replace("import translations from '@/i18n/sepaBatchLocalization.json'", `const translations = ${JSON.stringify(translations)}`)
    .replace(/import (ClubSepa\w+) from '\.\/[^']+'/g, 'const $1 = {render: () => null}')
  const Component = (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default
  const app = createSSRApp(Component, { clubId: 1 })
  app.use(createI18n({ legacy: false, locale, messages: {} }))
  return renderToString(app)
}

test('notice deadline and expired warning render and disable both preparation and sending', async () => {
  for (const prepared of [false, true]) {
    const html = await render({ prepared, windowOpen: false })
    assert.ok(html.includes(translations.de.noticeDeadline))
    assert.ok(html.includes('2026-09-26'))
    assert.ok(html.includes(translations.de.noticeExpired))
    const label = translations.de[prepared ? 'sendMessages' : 'prepareMessages']
    assert.match(html, new RegExp(`<button[^>]*disabled[^>]*>${label}</button>`))
  }
})

test('sent, uncertain, in-flight and cancelled messages have no resend button', async () => {
  for (const status of ['sent', 'uncertain', 'sending', 'cancelled']) {
    const html = await render({ status })
    assert.ok(!html.includes(`>${translations.de.sendMessages}</button>`))
    assert.ok(html.includes(translations.de.retryNoticeHelp))
  }
  for (const status of ['prepared', 'queued', 'blocked']) {
    assert.ok((await render({ status })).includes(`>${translations.de.sendMessages}</button>`))
  }
})

test('failure attention is outside collapsed preview and message content is escaped', async () => {
  for (const state of [{ status: 'blocked' }, { status: 'uncertain' }, { status: 'sent', providerStatus: 'bounced' }]) {
    const html = await render(state)
    assert.ok(html.indexOf(translations.de.noticeAttention) < html.indexOf('<details'))
    assert.ok(html.includes('&lt;script&gt;unsafe&lt;/script&gt;'))
    assert.ok(!html.includes('<script>unsafe'))
  }
})

test('notice guidance renders in every supported language', async () => {
  for (const locale of ['de', 'en', 'fr', 'ar']) {
    const html = await render({ locale, windowOpen: false, status: 'uncertain' })
    for (const key of ['noticeDeadline', 'noticeExpired', 'retryNoticeHelp', 'noticeAttention']) {
      assert.ok(html.includes(translations[locale][key]))
    }
  }
})
