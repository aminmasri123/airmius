import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { runInNewContext } from 'node:vm'
import { computed, ref, reactive } from 'vue'
import { parse } from '@vue/compiler-sfc'
import { matchesNavigation } from '../../resources/js/support/navigationMatch.js'

const root = new URL('../../', import.meta.url)
const subnav = parse(await readFile(new URL('resources/js/Components/Guest/Subnav.vue', root), 'utf8')).descriptor.scriptSetup.content
const shell = await readFile(new URL('resources/js/composables/useAirmiusShellNavigation.js', root), 'utf8')

test('club overview, tasks and calendar have exactly one active tool, including filtered deep links', () => {
    const page = reactive({ url: '/club-cockpit', props: { auth: { user: {} } } })
    const navigation = runInNewContext(`${shell.replace(/^import .*$/gm, '').replace('export const', 'const')}; useAirmiusShellNavigation()`, {
        computed, matchesNavigation, usePage: () => page,
        usePermissions: () => ({ can: () => true, hasAny: () => false, hasAnyRole: roles => roles.includes('club_owner') }),
        route: name => name === 'auth.club-cockpit.index' ? '/club-cockpit' : `/${name}`,
    })
    for (const [url, expected] of [
        ['/club-cockpit?club_id=7', 'club-cockpit'],
        ['/club-cockpit?panel=tasks&task_scope=all', 'club-todos'],
        ['/club-cockpit?club_id=7&panel=calendar', 'club-calendar'],
    ]) {
        page.url = url
        const items = navigation.groups.value.find(group => group.key === 'organization').items
        assert.deepEqual(Array.from(items.filter(item => item.active), item => item.key), [expected])
        // The sidebar must agree with the group's open/closed state.
        for (const item of items) {
            assert.equal(matchesNavigation(url, [item.href, ...item.activePaths], item.activeQuery), item.active)
        }
    }
})

test('section matching preserves nested routes and ignores unrelated query filters', () => {
    assert.equal(matchesNavigation('/teams/42?search=test', ['https://airmius.com/teams']), true)
    assert.equal(matchesNavigation('/teams-extra', ['/teams']), false)
    assert.equal(matchesNavigation('/teams', ['/']), false)
    assert.equal(matchesNavigation('/', ['/']), true)
    assert.equal(matchesNavigation('/club-cockpit?panel=calendar', ['/club-cockpit'], { panel: null }), false)
})

for (const authenticated of [false, true]) {
    test(`mobile navigation has four primary destinations and retains every other destination (authenticated: ${authenticated})`, () => {
        const nav = runInNewContext(`${subnav.replace(/^import .*$/gm, '')}; ({items, mobilePrimaryItems, mobileMoreItems})`, {
            computed, ref, watch: () => {}, defineProps: () => {},
            usePage: () => ({ props: { auth: { user: authenticated ? {} : null } } }),
            route: () => ({ has: () => false }),
        })
        assert.equal(nav.mobilePrimaryItems.value.length, 4)
        const visible = Array.from(nav.mobilePrimaryItems.value, item => item[1])
        assert.deepEqual(visible, [authenticated ? 'guest.subnav.feed' : 'guest.subnav.top_content', 'guest.subnav.clubs', 'Events', 'guest.subnav.shop'])
        const all = [...nav.mobilePrimaryItems.value, ...nav.mobileMoreItems.value].map(item => item[2])
        assert.equal(new Set(all).size, nav.items.value.length)
        assert.equal(all.length, nav.items.value.length)
    })
}
