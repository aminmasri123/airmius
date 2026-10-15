#!/usr/bin/env node

import { mkdir, readFile, writeFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const checkOnly = process.argv.includes('--check')
const origin = new URL(process.argv.slice(2).find((arg) => arg !== '--check') || 'http://127.0.0.1:8087')
if (!['https:', 'http:'].includes(origin.protocol)
  || (origin.protocol === 'http:' && !['127.0.0.1', 'localhost'].includes(origin.hostname))
  || origin.username || origin.password || origin.search || origin.hash) {
  throw new Error('Use an HTTPS origin or a loopback HTTP origin without credentials or query parameters.')
}

const destination = resolve(dirname(fileURLToPath(import.meta.url)), '../mobile/airmius_mobile/assets/legal/deletion')
const guides = { account: ['/konto-loeschen', 8], club: ['/verein-loeschen', 7] }
const output = []

for (const [kind, [path, minimumSections]] of Object.entries(guides)) {
  for (const locale of ['de', 'en', 'fr', 'ar']) {
    const url = new URL(path, origin)
    url.searchParams.set('format', 'json')
    url.searchParams.set('locale', locale)
    const response = await fetch(url, { redirect: 'error', signal: AbortSignal.timeout(15000) })
    if (!response.ok) throw new Error(`${url}: HTTP ${response.status}`)
    const data = await response.json()
    const valid = data.contentLocale === locale
      && data.translationComplete === true
      && typeof data.title === 'string'
      && data.title.trim() !== ''
      && Array.isArray(data.sections)
      && data.sections.length >= minimumSections
      && data.sections.every((section) => typeof section.title === 'string'
        && section.title.trim() !== ''
        && Array.isArray(section.body)
        && section.body.length > 0
        && section.body.every((line) => typeof line === 'string' && line.trim() !== ''))
    if (!valid) throw new Error(`${url}: incomplete deletion guide`)

    output.push([`${kind}_${locale}.json`, {
      title: data.title,
      sections: data.sections,
      note: data.note,
      contentLocale: data.contentLocale,
      translationComplete: data.translationComplete,
    }])
  }
}

if (!checkOnly) await mkdir(destination, { recursive: true })
for (const [name, data] of output) {
  const path = resolve(destination, name)
  const expected = `${JSON.stringify(data, null, 2)}\n`
  if (checkOnly) {
    const actual = await readFile(path, 'utf8')
    if (actual !== expected) throw new Error(`${name}: bundled copy differs from the current public guide`)
  } else {
    await writeFile(path, expected)
  }
  process.stdout.write(`${name}: ${data.sections.length} sections${checkOnly ? ' verified' : ''}\n`)
}
