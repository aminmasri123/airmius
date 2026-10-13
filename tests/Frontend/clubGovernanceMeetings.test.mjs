import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc'
import { computed, ref, watch, nextTick } from 'vue'

const filename = new URL('../../resources/js/Components/Clubs/ClubGovernanceMeetings.vue', import.meta.url)
const { descriptor, errors } = parse(await readFile(filename, 'utf8'))
assert.deepEqual(errors, [])
const compiled = compileScript(descriptor, { id: 'meetings-test' })
const template = compileTemplate({ source: descriptor.template.content, filename: filename.pathname,
    id: 'meetings-test', compilerOptions: { bindingMetadata: compiled.bindings } })
assert.deepEqual(template.errors, [])
const source = compiled.content.replace(/^import .*\n/gm, '').replace('export default', 'return')

function mount({ manager = false, voteError = false, saveError = false } = {}) {
    const requests = []
    const control = { denied: false }
    let voted = false
    let closed = false
    let decisionTitle = 'Budget'
    let title = 'Vorstand Oktober'
    let notes = 'Protokoll'
    const detail = () => ({ id: 4, title, notes, type: 'board', status: 'invited', can_manage: manager,
        participant_scope: 'body', governance_body: { id: 2, name: 'Vorstand' },
        current_recipient_id: 99, viewer_voting_eligible: true,
        agenda_items: [{ title: 'Finanzen' }], materials: [{ title: 'Unterlage' }], decision_templates: [],
        decisions: [{ id: 6, status: closed ? 'closed' : 'open', title: decisionTitle, type: 'motion', voting_mode: 'secret',
            viewer_has_voted: voted, viewer_can_vote: !voted && !closed, options: ['yes', 'no', 'abstain'],
            result: { outcome: closed ? 'accepted' : 'rejected' } }], recipients: [], versions: [],
    })
    const governance = { bodies: [{ id: 2, type: 'board', name: 'Vorstand', assignments: [
        { user_id: 9 }, { club_external_member_id: 8 }, { user_id: 10, ends_on: '2000-01-01' },
    ] }], member_options: [{ kind: 'user', id: 9, name: 'Alex' }, { kind: 'external', id: 8, name: 'Extern' },
        { kind: 'user', id: 10, name: 'Ehemalig' }, { kind: 'user', id: 11, name: 'Mitglied' }] }
    const api = async (request) => {
        requests.push(JSON.parse(JSON.stringify(request)))
        if (control.denied) throw { response: { status: 403, data: { message: 'Zugriff verweigert.' } } }
        if (request.url.endsWith('/decisions')) {
            decisionTitle = request.data.title
            return { data: { data: detail().decisions[0] } }
        }
        if (request.url.endsWith('/close')) {
            closed = true
            return { data: { data: detail().decisions[0] } }
        }
        if (request.url.endsWith('/vote')) {
            voted = true
            if (voteError) throw { response: { data: { message: 'Stimme bereits gespeichert.' } } }
            return { data: { data: { id: 5 } } }
        }
        if (request.method === 'put' || request.method === 'post' && request.url.endsWith('/meetings')) {
            if (saveError) throw { response: { data: { errors: { title: ['Titel konnte nicht gespeichert werden.'] } } } }
            title = request.data.title
            notes = request.data.notes
            return { data: { data: detail() } }
        }
        if (request.url.endsWith('/governance')) return { data: { data: governance } }
        if (request.url.endsWith('/meetings')) return { data: { data: [detail()], meta: { can_manage: manager } } }
        return { data: { data: detail() } }
    }
    const stops = []
    const component = new Function('computed', 'ref', 'watch', 'onMounted', 'AppLoadingState', 'confirmDialog', 'window', source)(
        computed, ref, (...args) => { const stop = watch(...args); stops.push(stop); return stop }, () => {}, {}, async () => true, { axios: api })
    const state = component.setup({ clubId: 7 }, { expose() {} })
    return { state, requests, control, stop: () => stops.forEach((stop) => stop()) }
}

test('invited member has only own one-time vote and reloads persisted secret ballot', async () => {
    const { state, requests, stop } = mount()
    await state.loadList()
    assert.equal(state.canCreate.value, false)
    await state.openMeeting(4)
    const decision = state.meeting.value.decisions[0]
    assert.equal(state.canManage.value, false)
    assert.equal(state.canVote(decision), true)
    await state.vote(decision, 'yes')
    assert.deepEqual(requests.find((request) => request.url.endsWith('/vote')).data, { recipient_id: 99, choice: 'yes' })
    assert.equal(state.meeting.value.decisions[0].viewer_has_voted, true)
    assert.equal(state.canVote(state.meeting.value.decisions[0]), false)
    await state.vote(state.meeting.value.decisions[0], 'no')
    assert.equal(requests.filter((request) => request.url.endsWith('/vote')).length, 1)
    assert.equal(state.notice.value, 'Stimme gespeichert.')
    stop()
})

test('uncertain vote error reloads persisted state without automatic resend', async () => {
    const { state, requests, stop } = mount({ voteError: true })
    await state.openMeeting(4)
    await state.vote(state.meeting.value.decisions[0], 'yes')
    assert.equal(requests.filter((request) => request.url.endsWith('/meetings/4')).length, 2)
    assert.equal(state.error.value, 'Stimme bereits gespeichert.')
    assert.equal(state.canVote(state.meeting.value.decisions[0]), false)
    stop()
})

test('body creation selects current assignments and persists explicit recipient voting rights', async () => {
    const { state, requests, stop } = mount({ manager: true })
    await state.loadList()
    await state.editMeeting(true)
    await nextTick()
    state.form.value.title = 'Vorstand November'
    state.form.value.scheduled_at = '2026-11-05T18:30'
    await nextTick()
    assert.equal(state.form.value.participant_scope, 'body')
    assert.deepEqual(state.recipientOptions.value.map(state.personKey), ['user:9', 'external:8'])
    state.selectBodyMembers()
    state.form.value.voting_rights['external:8'] = false
    await state.saveMeeting()
    const save = requests.find((request) => request.method === 'post' && request.url.endsWith('/meetings'))
    assert.deepEqual(save.data.recipients, [
        { user_id: 9, delivery_status: 'pending', attendance_eligible: true, voting_eligible: true },
        { club_external_member_id: 8, delivery_status: 'pending', attendance_eligible: true, voting_eligible: false },
    ])
    assert.equal(save.data.eligibility_as_of, '2026-11-05')
    assert.equal(state.meeting.value.title, 'Vorstand November')
    assert.equal(requests.at(-1).method, 'get')
    assert.equal(state.form.value, null)
    stop()
})

test('failed edit retains protocol input and does not perform a misleading reload', async () => {
    const { state, requests, stop } = mount({ manager: true, saveError: true })
    await state.openMeeting(4)
    await state.editMeeting()
    state.form.value.notes = 'Diskussion und Ergebnis'
    await state.saveMeeting()
    assert.equal(state.form.value.notes, 'Diskussion und Ergebnis')
    assert.equal(state.error.value, 'Titel konnte nicht gespeichert werden.')
    assert.equal(requests.filter((request) => request.method === 'get' && request.url.endsWith('/meetings/4')).length, 1)
    const payload = requests.find((request) => request.method === 'put').data
    assert.equal(Object.hasOwn(payload, 'recipients'), false)
    assert.deepEqual(payload.materials, [{ title: 'Unterlage' }])
    stop()
})

test('cancelled meetings and viewer eligibility forbid voting and decision creation', async () => {
    const { state, requests, stop } = mount({ manager: true })
    await state.openMeeting(4)
    const decision = state.meeting.value.decisions[0]
    decision.viewer_can_vote = false
    assert.equal(state.canVote(decision), false)
    decision.viewer_can_vote = true
    state.meeting.value.status = 'cancelled'
    assert.equal(state.canVote(decision), false)
    state.newDecision()
    assert.equal(state.decisionForm.value, null)
    await state.vote(decision, 'yes')
    assert.equal(requests.length, 1)
    assert.equal(state.creator({ created_by: 'Alex' }), 'Alex')
    stop()
})

test('revoked access clears both cached list and meeting and revokes create actions', async () => {
    const { state, control, stop } = mount({ manager: true })
    await state.loadList()
    assert.equal(state.meetings.value.length, 1)
    control.denied = true
    await state.openMeeting(4)
    assert.equal(state.meetings.value.length, 0)
    assert.equal(state.meeting.value, null)
    assert.equal(state.canCreate.value, false)
    assert.equal(state.error.value, 'Zugriff verweigert.')
    control.denied = false
    await state.loadList()
    control.denied = true
    await state.loadList()
    assert.equal(state.meetings.value.length, 0)
    assert.equal(state.canCreate.value, false)
    stop()
})

test('decision creation and close reload the persisted server result', async () => {
    const { state, requests, stop } = mount({ manager: true })
    await state.openMeeting(4)
    state.newDecision()
    state.decisionForm.value.title = 'Neue Trainingszeiten'
    await state.saveDecision()
    assert.equal(state.meeting.value.decisions[0].title, 'Neue Trainingszeiten')
    assert.deepEqual(requests.find((request) => request.url.endsWith('/decisions')).data.options, ['yes', 'no', 'abstain'])
    await state.closeDecision(state.meeting.value.decisions[0])
    assert.equal(state.meeting.value.decisions[0].status, 'closed')
    assert.equal(state.meeting.value.decisions[0].result.outcome, 'accepted')
    assert.equal(state.canVote(state.meeting.value.decisions[0]), false)
    assert.equal(state.notice.value, 'Abstimmung abgeschlossen.')
    stop()
})
