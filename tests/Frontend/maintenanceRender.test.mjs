import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript } from '@vue/compiler-sfc'
import { createSSRApp } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createI18n } from 'vue-i18n'

const root = new URL('../../', import.meta.url)
const source = await readFile(new URL('resources/js/Pages/Maintenance.vue', root), 'utf8')
const { descriptor } = parse(source)
let script = compileScript(descriptor, { id: 'maintenance-regression', inlineTemplate: true }).content
script = script
    .replace("import { Head, Link, usePage } from '@inertiajs/vue3'", `import { h } from 'vue'
        const Head = { render: () => null }
        const Link = { props: ['href'], setup: (props, { slots }) => () => h('a', { href: props.href }, slots.default?.()) }
        const usePage = () => ({ props: { locale: 'de', auth: { user: null } } })`)
    .replace("import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'", "const AuthenticationCardLogo = { render: () => h('img', { alt: 'AIRMIUS Logo' }) }")
    .replace("import LanguageDropdown from '@/Components/LanguageDropdown.vue'", "const LanguageDropdown = { render: () => h('button', 'Language') }")
for (const dependency of ['vue', 'vue-i18n']) script = script.replaceAll(`from '${dependency}'`, `from '${import.meta.resolve(dependency)}'`).replaceAll(`from "${dependency}"`, `from '${import.meta.resolve(dependency)}'`)
const Maintenance = (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default

for (const locale of ['de', 'en', 'fr', 'ar']) {
    test(`maintenance page renders email, text and login without a translation crash in ${locale}`, async () => {
        const messages = JSON.parse(await readFile(new URL(`resources/js/lang/${locale}.json`, root), 'utf8'))
        const app = createSSRApp(Maintenance, { canLogin: true })
        app.config.globalProperties.route = name => `/${name}`
        app.use(createI18n({ legacy: false, locale, messages: { [locale]: messages } }))
        const html = await renderToString(app)
        assert.match(html, /contact@airmius\.com/)
        assert.match(html, /mailto:contact@airmius\.com/)
        assert.ok(html.includes(messages.maintenance.title))
        assert.ok(html.includes(messages.maintenance.contact_support))
        assert.match(html, /href="\/login"/)
        assert.doesNotMatch(html, /\{email\}/)
    })
}

test('administrator supplied titles and messages remain plain text', async () => {
    const messages = JSON.parse(await readFile(new URL('resources/js/lang/de.json', root), 'utf8'))
    const app = createSSRApp(Maintenance, { title: 'Airmius entsteht gerade', message: 'Fragen an test@example.com <script>alert(1)</script>', canLogin: true })
    app.config.globalProperties.route = name => `/${name}`
    app.use(createI18n({ legacy: false, locale: 'de', messages: { de: messages } }))
    const html = await renderToString(app)
    assert.match(html, /Airmius entsteht gerade/)
    assert.match(html, /test@example\.com/)
    assert.match(html, /&lt;script&gt;/)
    assert.doesNotMatch(html, /<script>alert/)
})
