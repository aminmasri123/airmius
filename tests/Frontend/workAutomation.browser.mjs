import assert from 'node:assert/strict'
import { mkdir, readFile } from 'node:fs/promises'

// Run explicitly against the isolated T27 QA application, never production.
const base = process.env.AIRMIUS_QA_URL || 'http://127.0.0.1:43210'
const evidence = process.env.AIRMIUS_QA_EVIDENCE || '/tmp/airmius-t27-evidence'
const fixtures = JSON.parse(await readFile(process.env.AIRMIUS_QA_FIXTURES || '/tmp/airmius-t27-qa/fixtures.json', 'utf8'))
const { chromium } = await import(process.env.AIRMIUS_PLAYWRIGHT_MODULE || 'playwright')
const browser = await chromium.launch({ executablePath: process.env.AIRMIUS_CHROMIUM, headless: true, args: ['--no-sandbox'] })
await mkdir(evidence, { recursive: true })
const clubId = fixtures.club_id
const errors = []
const get = (page, path) => page.evaluate(async (path) => {
    const response = await window.axios.get(path, { validateStatus: () => true })
    return { status: response.status, body: response.data }
}, path)
let page
try {
    page = await browser.newPage({ viewport: { width: 1280, height: 900 } })
    page.on('pageerror', (error) => errors.push(error.message))
    await page.goto(`${base}/__t27/as/owner`, { waitUntil: 'domcontentloaded' })
    const navLink = page.locator('a[href*="club-work-automation"]').first()
    if (await navLink.isVisible().catch(() => false)) {
        await navLink.click()
    } else {
        await page.goto(`${base}/club-work-automation?club_id=${clubId}`, { waitUntil: 'domcontentloaded' })
    }
    const main = page.locator('main').last()
    await main.getByRole('heading', { name: 'Arbeitsautomation', exact: true }).waitFor()
    await main.getByText(/Berechtigte Empfänger: [1-9]/).waitFor()
    const preview = await get(page, `/api/v1/clubs/${clubId}/work-automation-recipients`)
    assert.equal(preview.status, 200)
    assert.ok(preview.body.data.count > 0)
    const name = `QA actual reminder ${Date.now()}`
    await main.getByLabel('Titel', { exact: true }).fill(name)
    await main.getByLabel('Nachricht', { exact: true }).fill('Real stored notification; replay must not duplicate it.')
    const requests = []
    let lostResponse = true
    await page.route('**/work-automation-jobs', async (route) => {
        if (route.request().method() !== 'POST') return route.continue()
        requests.push(route.request().postDataJSON())
        const response = await route.fetch()
        assert.ok([200, 202].includes(response.status()), await response.text())
        if (lostResponse) { lostResponse = false; return route.abort('failed') }
        return route.fulfill({ response })
    })
    await main.getByRole('button', { name: 'Einreihen', exact: true }).click()
    await main.getByRole('button', { name: 'Dieselbe Anfrage senden', exact: true }).waitFor()
    await page.reload({ waitUntil: 'domcontentloaded' })
    await main.getByRole('button', { name: 'Dieselbe Anfrage senden', exact: true }).click()
    const dialog = page.getByRole('dialog')
    await dialog.waitFor()
    assert.deepEqual(requests[0], requests[1])
    await dialog.getByText(/Gespeicherte Benachrichtigungen: [1-9]/).waitFor()
    const saved = (await get(page, `/api/v1/clubs/${clubId}/work-automation-jobs`)).body.data.filter((job) => job.idempotency_key === requests[0].idempotency_key)
    assert.equal(saved.length, 1)
    assert.equal(saved[0].status, 'completed')
    assert.equal(saved[0].attempts, 1)
    assert.equal(saved[0].result.stored_notifications, preview.body.data.count)
    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 900 })
        assert.equal(await dialog.evaluate((node) => node.scrollWidth <= node.clientWidth), true)
        await page.screenshot({ path: `${evidence}/automation-detail-${width}.png` })
    }
    await dialog.getByRole('button', { name: 'Schließen', exact: true }).click()
    await page.setViewportSize({ width: 1280, height: 900 })
    const failedRow = main.locator('li').filter({ has: page.getByRole('button', { name: new RegExp(`#${fixtures.failed_job_id}$`) }) })
    const beforeRetry = await get(page, `/api/v1/clubs/${clubId}/work-automation-jobs/${fixtures.failed_job_id}`)
    if (beforeRetry.body.data.status === 'failed') {
        page.once('dialog', (confirmation) => confirmation.accept())
        await failedRow.getByRole('button', { name: 'Wiederholen', exact: true }).click()
        await failedRow.getByText('Abgeschlossen', { exact: true }).waitFor()
        const retry = await get(page, `/api/v1/clubs/${clubId}/work-automation-jobs/${fixtures.failed_job_id}`)
        assert.equal(retry.body.data.attempts, beforeRetry.body.data.attempts + 1)
        assert.ok(retry.body.data.history.some((event) => event.event.endsWith('.retry_queued')))
    } else assert.ok(beforeRetry.body.data.history.some((event) => event.event.endsWith('.retry_queued')))
    const scheduledTitle = `QA scheduled reminder ${Date.now()}`
    await main.getByLabel('Titel', { exact: true }).fill(scheduledTitle)
    await main.getByLabel('Nachricht', { exact: true }).fill('Scheduled real reminder')
    await main.locator('input[type=datetime-local]').fill('2027-01-02T10:00')
    await main.getByRole('button', { name: 'Einreihen', exact: true }).click()
    await dialog.waitFor()
    await dialog.getByText(/Geplant/).first().waitFor()
    await dialog.getByRole('button', { name: 'Schließen', exact: true }).click()
    await page.reload({ waitUntil: 'domcontentloaded' })
    await main.locator('select').last().selectOption('scheduled')
    await main.getByRole('button', { name: new RegExp(scheduledTitle) }).waitFor()
    const scheduledJob = (await get(page, `/api/v1/clubs/${clubId}/work-automation-jobs?status=scheduled`)).body.data.find((job) => job.title === scheduledTitle)
    assert.equal(scheduledJob.status, 'scheduled')
    assert.equal(scheduledJob.attempts, 0)
    console.log(`SCHEDULED_JOB_ID=${scheduledJob.id}`)
    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 900 })
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true)
        await page.screenshot({ path: `${evidence}/automation-list-${width}.png`, fullPage: true })
    }
    for (const role of ['member', 'guardian', 'foreign', 'chair', 'treasurer']) {
        const context = await browser.newContext()
        try {
            const rolePage = await context.newPage()
            await rolePage.goto(`${base}/__t27/as/${role}`, { waitUntil: 'domcontentloaded' })
            await rolePage.waitForFunction(() => !!window.axios)
            const response = await get(rolePage, `/api/v1/clubs/${clubId}/work-automation-jobs`)
            assert.equal(response.status, ['chair', 'treasurer'].includes(role) ? 200 : 403, role)
            const responsePage = await rolePage.goto(`${base}/club-work-automation?club_id=${clubId}`, { waitUntil: 'domcontentloaded' })
            if (['member', 'guardian'].includes(role)) assert.equal(responsePage.status(), 403, role)
            else {
                await rolePage.getByRole('heading', { name: 'Arbeitsautomation', exact: true }).waitFor()
                if (role === 'foreign') {
                    const foreignList = await get(rolePage, `/api/v1/clubs/${fixtures.foreign_club_id}/work-automation-jobs`)
                    assert.equal(foreignList.status, 200)
                    assert.ok(foreignList.body.data.every((job) => job.club_id === fixtures.foreign_club_id))
                    assert.equal(await rolePage.getByRole('button', { name: new RegExp(scheduledTitle) }).count(), 0)
                }
            }
        } finally { await context.close() }
    }
    assert.deepEqual(errors, [])
    console.log('PASS: real menu/counts, create/lost-response/reload/key replay, delivery, retry audit, scheduled persistence/filter, permissions/tenants, desktop/mobile layout')
} catch (error) {
    await page?.screenshot({ path: `${evidence}/automation-failure.png`, fullPage: true }).catch(() => {})
    throw error
} finally { await browser.close() }
