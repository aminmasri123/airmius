import fs from 'node:fs'

// ISO code source: Debian iso-codes. Localized names: Node's ICU/CLDR data.
const source = process.argv[2] || '/usr/share/iso-codes/json/iso_3166-1.json'
const codes = JSON.parse(fs.readFileSync(source, 'utf8'))['3166-1'].map(row => row.alpha_2)
if (codes.length !== 249 || new Set(codes).size !== 249) throw new Error('Unexpected ISO country catalogue')
codes.push('XK') // Commonly used Kosovo code, explicitly additional to ISO 3166-1.
const locales = ['de', 'en', 'fr', 'ar']
const names = Object.fromEntries(locales.map(locale => [locale, new Intl.DisplayNames(locale, { type: 'region' })]))
const rows = codes.sort().map(code => ({ code, names: Object.fromEntries(locales.map(locale => [locale, names[locale].of(code)])) }))
for (const path of ['resources/data/countries.json', 'mobile/airmius_mobile/assets/data/countries.json']) {
    fs.mkdirSync(path.slice(0, path.lastIndexOf('/')), { recursive: true })
    fs.writeFileSync(path, JSON.stringify(rows, null, 2) + '\n')
}
