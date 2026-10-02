import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { runInNewContext } from 'node:vm'
import { computed, ref } from 'vue'
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc'

const source = await readFile(new URL('../../resources/js/Pages/Auth/Dashboard/ClubCockpit/Index.vue', import.meta.url), 'utf8')
const { descriptor } = parse(source)

function calendar(locale = 'de') {
    // Execute the actual SFC state with external services stubbed; no network calls.
    return runInNewContext(`${descriptor.scriptSetup.content.replace(/^import .*$/gm, '')}
        ;({ clubCalendarView, clubCalendarCursor, clubTasks, clubTaskMeta,
            calendarBuckets, calendarTitle, moveClubCalendar, weekDays, formatDate })`, {
        Date, Intl, computed, ref, watch: () => {}, AppLayout: {},
        defineOptions: () => {}, defineProps: () => ({ clubs: [] }),
        usePage: () => ({ props: { locale }, url: '/dashboard/club-cockpit?panel=calendar' }),
        useI18n: () => ({ t: value => value }), usePermissions: () => ({ can: () => true }),
    })
}

test('calendar SFC compiles and exposes day/week/month/year controls', () => {
    const script = compileScript(descriptor, { id: 'club-calendar' })
    const template = compileTemplate({ source: descriptor.template.content, filename: 'Index.vue', id: 'club-calendar', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    assert.match(descriptor.template.content, /\['day', 'week', 'month', 'year'\]/)
})

test('all views format successfully in every supported locale', () => {
    for (const locale of ['de', 'en', 'fr', 'ar']) {
        const c = calendar(locale)
        c.clubCalendarCursor.value = new Date(2028, 1, 29)
        for (const [view, count] of [['day', 1], ['week', 7], ['month', 29], ['year', 12]]) {
            c.clubCalendarView.value = view
            assert.equal(c.calendarBuckets.value.length, count)
            assert.ok(c.calendarTitle.value)
            assert.ok(c.calendarBuckets.value.every(bucket => bucket.label))
        }
    }
})

test('month and year navigation clamp month ends and leap days in both directions', () => {
    const c = calendar()
    for (const [view, start, delta, expected] of [
        ['month', [2027, 0, 31], 1, [2027, 1, 28]],
        ['month', [2028, 0, 31], 1, [2028, 1, 29]],
        ['month', [2028, 2, 31], -1, [2028, 1, 29]],
        ['month', [2027, 11, 31], 1, [2028, 0, 31]],
        ['month', [2028, 0, 31], -1, [2027, 11, 31]],
        ['year', [2028, 1, 29], 1, [2029, 1, 28]],
        ['year', [2028, 1, 29], -1, [2027, 1, 28]],
        ['day', [2026, 11, 31], 1, [2027, 0, 1]],
        ['week', [2026, 11, 31], 1, [2027, 0, 7]],
    ]) {
        c.clubCalendarView.value = view
        c.clubCalendarCursor.value = new Date(...start)
        c.moveClubCalendar(delta)
        const date = c.clubCalendarCursor.value
        assert.deepEqual([date.getFullYear(), date.getMonth(), date.getDate()], expected)
    }
})

test('weeks remain Monday through Sunday across year and DST boundaries', () => {
    const c = calendar()
    for (const date of [new Date(2027, 0, 1), new Date(2026, 2, 29), new Date(2026, 9, 25)]) {
        const days = c.weekDays(date)
        assert.equal(days[0].getDay(), 1)
        assert.equal(days[6].getDay(), 0)
        assert.equal(new Set(days.map(day => day.toDateString())).size, 7)
        assert.ok(days.every(day => day.getHours() === 0))
    }
})

test('month and year buckets group tasks and events without losing the selected date', () => {
    const c = calendar()
    const cursor = new Date(2028, 1, 29)
    c.clubCalendarCursor.value = cursor
    c.clubTasks.value = [
        { id: 1, title: 'Leap deadline', due_at: '2028-02-29' },
        { id: 2, title: 'Other month', due_at: '2028-03-01' },
        { id: 3, title: 'Invalid', due_at: 'invalid' },
    ]
    c.clubTaskMeta.value = { calendar_events: [
        { id: 4, title: 'Leap event', start_time: '2028-02-29T12:00:00' },
        { id: 5, title: 'Other year', start_time: '2027-02-28T12:00:00' },
    ] }
    c.clubCalendarView.value = 'month'
    assert.equal(c.calendarBuckets.value.at(-1).items.length, 2)
    assert.equal(c.calendarBuckets.value.reduce((sum, bucket) => sum + bucket.items.length, 0), 2)
    c.clubCalendarView.value = 'year'
    assert.equal(c.calendarBuckets.value[1].items.length, 2)
    assert.equal(c.calendarBuckets.value[2].items.length, 1)
    c.clubCalendarView.value = 'day'
    assert.equal(c.calendarBuckets.value[0].items.length, 2)
    assert.equal(c.clubCalendarCursor.value.getTime(), cursor.getTime())
    assert.equal(c.formatDate(cursor), c.formatDate('2028-02-29'))
})
