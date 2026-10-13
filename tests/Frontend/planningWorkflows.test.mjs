import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { runInNewContext } from 'node:vm'
import { computed, createSSRApp, ref } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { parse, compileScript } from '@vue/compiler-sfc'
import * as helpers from '../../resources/js/Components/Planning/planningState.js'

const root = new URL('../../', import.meta.url)
const sources = Object.fromEntries(await Promise.all(['Index', 'FamilyCalendar'].map(async (name) => [name, await readFile(new URL(`resources/js/Pages/Auth/Dashboard/Planning/${name}.vue`, root), 'utf8')])))
const vueUrl = import.meta.resolve('vue')
globalThis.route = (name) => `/dashboard/${name}`

async function render(name, seed) {
    const { descriptor } = parse(sources[name].replace('</script>', `${seed.replaceAll('</script', '<\\/script')}\n</script>`))
    let script = compileScript(descriptor, { id: name, inlineTemplate: true }).content
    script = script.replace(/from ["']vue["']/g, `from '${vueUrl}'`)
        .replace(/^import AppLayout .*$/m, 'const AppLayout = {}')
        .replace(/^import PlanningFields .*$/m, 'const PlanningFields = { render: () => null }')
        .replace(/^import GameLineupBoard .*$/m, 'const GameLineupBoard = { render: () => null }')
        .replace(/^import .* from '@inertiajs\/vue3'$/m, `import { h } from '${vueUrl}'; const Head = { render: () => null }; const Link = { props: ['href'], setup: (props, { slots }) => () => h('a', { href: props.href }, slots.default?.()) }`)
        .replace(/^import .* from 'vue-i18n'$/m, "const useI18n = () => ({ locale: ref('en') })")
        .replace("from '@/Components/Planning/planningState.js'", `from '${new URL('resources/js/Components/Planning/planningState.js', root).href}'`)
    const Component = (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default
    const app = createSSRApp(Component)
    app.config.globalProperties.route = globalThis.route
    return renderToString(app)
}

function state(name, axios, extra = '') {
    const { descriptor } = parse(sources[name])
    const exported = name === 'Index'
        ? 'clubs, clubId, teamId, tab, competitions, detail, seasons, seasonForm, selectedRoster, nominationRoster, busy, error, editor, load, openCompetition, editSeason, saveSeason, editCompetition, submitEditor'
        : 'calendar, month, person, entryForm, responseForm, busy, error, load, moveMonth, editEntry, saveEntry, editResponse, saveResponse'
    return runInNewContext(`${descriptor.scriptSetup.content.replace(/^import .*$/gm, '')}\n${extra}\n;({${exported}})`, {
        computed, ref, watch: () => {}, onMounted: () => {}, defineOptions: () => {}, AppLayout: {},
        useI18n: () => ({ locale: ref('en') }), URLSearchParams, Date, Map, ...helpers,
        window: { location: { search: '' }, axios, confirm: () => true },
    })
}

const club = { id: 1, name: 'Club', can_edit_competitions: false, can_delete_competitions: false,
    teams: [{ id: 2, name: 'Team', sport_year_period_id: 3 }], periods: [{ id: 3, name: '2026/27', starts_on: '2026-08-01', ends_on: '2027-07-31' }], members: [], season_planning_enabled: true }
const competition = { id: 7, name: 'Meet', status: 'open', can_edit: false, can_pay_fees: false, can_confirm: false, can_view_finance: false,
    classes: [], venues: [], roster_entries: [{ id: 8, display_name: 'Runner' }], registrations: [{ id: 9, status: 'pending_confirmation' }], tournament_matches: [], standings: [], lineups: [], games: [], eligible_roster_ids: [] }
const seedCompetition = (detail = competition, selectedClub = club) => `clubs.value = ${JSON.stringify([selectedClub])}; clubId.value = 1; detail.value = ${JSON.stringify(detail)};`
const family = { self: { id: 1, name: 'Parent' }, children: [{ id: 2, name: 'Child', club_id: 3 }],
    entries: [{ id: 4, title: '<script>private</script>', starts_at: '2026-10-13T10:00:00Z', ends_at: '2026-10-13T11:00:00Z', child_user_id: 2, club_id: 3 }],
    events: [{ id: 5, person_id: 2, person_name: 'Child', title: 'Training', starts_at: '2026-10-13T12:00:00Z', ends_at: '2026-10-13T13:00:00Z', can_respond: true }] }
const response = (data) => ({ data: { data } })

test('competition renders read-only detail without edit, roster, nomination or finance controls', async () => {
    const html = await render('Index', seedCompetition())
    assert.match(html, /Read Only/)
    assert.match(html, /Runner/)
    assert.doesNotMatch(html, /<form|type="checkbox"|Fee Paid|Check Eligibility|Confirm Registration|Cent|aria-label="Edit"/)
})

test('competition uses detail capabilities even when old workspace flags allow editing', async () => {
    const html = await render('Index', seedCompetition(competition, { ...club, can_edit_competitions: true }))
    assert.doesNotMatch(html, /<form|type="checkbox"|Check Eligibility/)
    const finance = await render('Index', seedCompetition({ ...competition, can_pay_fees: true, can_view_finance: true,
        registrations: [{ id: 9, status: 'pending_confirmation', start_fee_cents: 500 }] }))
    assert.match(finance, /Fee Paid/)
    assert.match(finance, /500 Cent/)
    assert.doesNotMatch(finance, /Check Eligibility|Confirm Registration|<form/)
})

test('read-only season renders sport year, phases and linked events without an editor', async () => {
    const plan = { id: 1, name: 'Season', sport_year_period_id: 3, status: 'published', goals: 'Improve', phases: [{ name: 'Prepare', starts_on: '2026-08-01', ends_on: '2026-09-01', goal: 'Technique' }], events: [{ id: 10, title: 'Race', start_time: '2026-10-13T10:00:00Z' }] }
    const html = await render('Index', `clubs.value = ${JSON.stringify([club])}; clubId.value = 1; teamId.value = 2; tab.value = 'season'; seasons.value = ${JSON.stringify({ can_edit: false, plans: [plan], events: [] })}`)
    assert.match(html, /Read Only/); assert.match(html, /2026\/27/); assert.match(html, /Prepare/); assert.match(html, /Race/)
    assert.doesNotMatch(html, /<form|aria-label="Edit:|aria-label="Delete:/)
})

test('season editing copies reactive phases, sends revision and reloads saved server state', async () => {
    const calls = [], plan = { id: 4, revision: 2, name: 'Season', sport_year_period_id: 3, status: 'draft', phases: [{ name: 'Prepare', starts_on: '2026-08-01', ends_on: '2026-09-01' }], events: [{ id: 10 }] }
    const s = state('Index', async (request) => {
        calls.push(request)
        return response(request.method === 'put' ? { id: 4 } : { can_edit: true, plans: [{ ...plan, revision: 3, name: 'Saved Season' }], events: [] })
    })
    s.clubs.value = [club]; s.clubId.value = 1; s.teamId.value = 2; s.tab.value = 'season'
    s.seasons.value = { can_edit: true, plans: [plan], events: [{ id: 10, sport_year_period_id: 3 }] }
    s.editSeason(s.seasons.value.plans[0]); s.seasonForm.value.phases[0].name = 'Changed'
    assert.equal(s.seasons.value.plans[0].phases[0].name, 'Prepare')
    await s.saveSeason()
    assert.equal(calls[0].method, 'put'); assert.equal(calls[0].url, '/api/v1/teams/2/season-plans/4')
    assert.equal(calls[0].data.revision, 2); assert.deepEqual(Array.from(calls[0].data.event_ids), [10])
    assert.equal(s.seasons.value.plans[0].name, 'Saved Season'); assert.equal(s.seasonForm.value, null)
})

test('season conflict preserves unsaved input and displays the backend error', async () => {
    const s = state('Index', async () => { throw { response: { status: 409, data: { message: 'Reload before saving.' } } } })
    s.clubs.value = [club]; s.clubId.value = 1; s.teamId.value = 2; s.tab.value = 'season'
    s.seasonForm.value = { id: 4, revision: 2, name: 'Unsaved', sport_year_period_id: 3, phases: [], event_ids: [] }
    await s.saveSeason()
    assert.equal(s.error.value, 'Reload before saving.'); assert.equal(s.seasonForm.value.name, 'Unsaved'); assert.equal(s.busy.value, false)
})

test('late club response cannot repopulate another club or retain old capability state', async () => {
    const pending = []
    const s = state('Index', (request) => new Promise((resolve) => pending.push({ request, resolve })))
    s.clubId.value = 1; s.seasons.value.can_edit = true; s.detail.value = competition
    const first = s.load(); s.clubId.value = 2; const second = s.load()
    assert.equal(s.detail.value, null); assert.equal(s.seasons.value.can_edit, false)
    pending[1].resolve(response([{ id: 20, name: 'Second Club' }])); await second
    pending[0].resolve(response([{ id: 10, name: 'First Club' }])); await first
    assert.equal(s.competitions.value[0].id, 20)
})

test('private family edit preserves approved child and club and reloads after save', async () => {
    const calls = [], s = state('FamilyCalendar', async (request) => { calls.push(request); return response(request.method === 'get' ? family : { id: 4 }) })
    s.calendar.value = family; s.editEntry(family.entries[0]); s.entryForm.value.title = 'Changed'
    await s.saveEntry()
    assert.equal(calls[0].method, 'put'); assert.equal(calls[0].url, '/api/v1/family-calendar/entries/4')
    assert.equal(calls[0].data.child_user_id, 2); assert.equal(calls[0].data.club_id, 3)
    assert.equal(calls[0].data.starts_at, '2026-10-13T10:00:00.000Z')
    assert.equal(s.entryForm.value, null); assert.equal(s.calendar.value.entries.length, 1)
})

test('family RSVP sends the child identity and explicit accepted versions, then reloads', async () => {
    const calls = [], s = state('FamilyCalendar', async (request) => { calls.push(request); return response(request.method === 'get' ? family : {}) })
    s.calendar.value = family; s.editResponse({ ...family.events[0], participation_consent_version: 'v2', camp_privacy_notice_version: 'v3' })
    s.responseForm.value.consent_accepted = true; s.responseForm.value.camp_privacy_notice_accepted = true
    await s.saveResponse()
    assert.equal(calls[0].url, '/api/v1/family-calendar/events/5/people/2/response')
    assert.equal(calls[0].data.consent_accepted, true); assert.equal(calls[0].data.consent_version, 'v2')
    assert.equal(calls[0].data.camp_privacy_notice_version, 'v3'); assert.equal(calls[0].data.requirements_accepted, false)
    assert.equal(calls[1].method, 'get'); assert.equal(s.responseForm.value, null)
})

test('family errors retain validation input and remove private state on revoked access', async () => {
    let status = 422
    const s = state('FamilyCalendar', async () => { throw { response: { status, data: { errors: { title: ['Rejected by server'] } } } } })
    s.calendar.value = family; s.editEntry(family.entries[0]); await s.saveEntry()
    assert.equal(s.error.value, 'Rejected by server'); assert.equal(s.entryForm.value.id, 4)
    status = 403; await s.saveEntry()
    assert.equal(s.calendar.value.entries.length, 0); assert.equal(s.entryForm.value, null)
})

test('family calendar escapes private titles and respects backend RSVP controls', async () => {
    const html = await render('FamilyCalendar', `calendar.value = ${JSON.stringify({ ...family, events: [{ ...family.events[0], can_respond: false }] })}`)
    assert.match(html, /&lt;script&gt;private&lt;\/script&gt;/); assert.match(html, /Child/); assert.match(html, /Responses Closed/)
    assert.doesNotMatch(html, /<script|Respond to Event/)
    const camp = await render('FamilyCalendar', `calendar.value = ${JSON.stringify(family)}; editResponse(${JSON.stringify({ ...family.events[0], requires_camp_details: true, participation_requirements: ['License required'], participation_consent_required: true, participation_consent_version: 'v2', camp_privacy_notice_version: 'v3', camp_travel_consent_required: true })});`)
    assert.match(camp, /License required/); assert.match(camp, /Participation Consent v2/); assert.match(camp, /Privacy Notice Accepted v3/); assert.match(camp, /Travel Consent/)
})

test('datetime edits roundtrip server instants and month navigation crosses years', () => {
    const original = process.env.TZ
    process.env.TZ = 'Europe/Berlin'
    assert.equal(helpers.localDateTime('2026-10-13T10:30:00Z'), '2026-10-13T12:30')
    assert.equal(helpers.apiDateTime('2026-10-13T12:30'), '2026-10-13T10:30:00.000Z')
    const s = state('FamilyCalendar', async () => response(family)); s.month.value = '2026-12'; s.moveMonth(1)
    assert.equal(s.month.value, '2027-01'); s.moveMonth(-1); assert.equal(s.month.value, '2026-12')
    if (original === undefined) delete process.env.TZ; else process.env.TZ = original
})
