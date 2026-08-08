import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { usePermissions } from '@/composables/usePermissions'

const PLATFORM_ROLES = ['super_admin', 'admin', 'system_admin', 'platform_engineer', 'security_admin']
const CLUB_ROLES = ['club_owner', 'club_admin', 'club_manager', 'academy_manager', 'financial_controller', 'media_manager']
const SPONSOR_ROLES = ['sponsor', 'sponsor_manager']

export const useAirmiusShellNavigation = () => {
    const page = usePage()
    const { can, hasAny, hasAnyRole } = usePermissions()

    const path = computed(() => String(page.url || '/').split('?')[0])
    const enabledModules = computed(() => page.props.auth?.user?.navigation_modules?.enabled || [])
    const moduleEnabled = (module) => enabledModules.value.includes(module)
    const isPlatform = computed(() => hasAnyRole(PLATFORM_ROLES))
    const isCoach = computed(() => !isPlatform.value && (moduleEnabled('coach') || can('trainer-cockpit.view')))
    const isClub = computed(() => !isPlatform.value && (moduleEnabled('club') || hasAnyRole(CLUB_ROLES) || can('club-cockpit.view')))
    const isSponsor = computed(() => !isPlatform.value && (hasAnyRole(SPONSOR_ROLES) || can('sponsor.workspace.view')))
    const isAthlete = computed(() => !isPlatform.value && moduleEnabled('athlete'))
    const workspaceCount = computed(() => [isCoach.value, isClub.value, isSponsor.value].filter(Boolean).length)

    const matches = (paths = []) => paths.some((candidate) => path.value === candidate || path.value.startsWith(`${candidate}/`))
    const item = (key, labelKey, icon, href, activePaths = [], badge = null) => ({
        key,
        label: labelKey,
        icon,
        href,
        activePaths,
        badge,
        active: matches(activePaths),
    })

    const roleHome = computed(() => {
        if (isPlatform.value) return route('auth.dashboard')
        if (workspaceCount.value > 1) return route('auth.workspaces.index')
        if (isClub.value) return route('auth.club-cockpit.index')
        if (isCoach.value) return route('auth.trainer-cockpit.index')
        if (isSponsor.value) return route('auth.sponsor-workspace.index')

        return route('auth.feed.index')
    })

    const planHome = computed(() => {
        if (isAthlete.value || isCoach.value) return route('auth.training.index')

        return route('auth.events.index')
    })

    const organizationHome = computed(() => {
        if (isPlatform.value && can('users.view')) return route('members.index')
        if (isClub.value) return route('auth.club-cockpit.index')
        if (isSponsor.value) return route('auth.sponsor-workspace.index')

        return route('auth.teams.index')
    })

    const workspace = computed(() => {
        const currentClub = page.props.workspaceContext?.current

        if (currentClub?.type === 'club') return { label: currentClub.name, icon: 'las la-building', tone: 'club', translated: false }
        if (isPlatform.value) return { label: 'shell.workspace.platform', icon: 'las la-shield-alt', tone: 'platform', translated: true }
        if (workspaceCount.value > 1) return { label: 'shell.workspace.multiple', icon: 'las la-layer-group', tone: 'multiple', translated: true }
        if (isClub.value) return { label: 'shell.workspace.club', icon: 'las la-building', tone: 'club', translated: true }
        if (isCoach.value) return { label: 'shell.workspace.coach', icon: 'las la-chalkboard-teacher', tone: 'coach', translated: true }
        if (isSponsor.value) return { label: 'shell.workspace.sponsor', icon: 'las la-handshake', tone: 'sponsor', translated: true }

        return { label: 'shell.workspace.athlete', icon: 'las la-running', tone: 'athlete', translated: true }
    })

    const primarySpaces = computed(() => [
        item('today', 'shell.spaces.today', 'las la-sun', roleHome.value, ['/dashboard', '/workspaces', '/club-cockpit', '/trainer-cockpit', '/sponsor-cockpit']),
        item('plan', 'shell.spaces.plan', 'las la-calendar-alt', planHome.value, ['/training', '/events', '/nutrition', '/sport-map']),
        item('community', 'shell.spaces.community', 'las la-comments', route('auth.feed.index'), ['/feed', '/conversations', '/friends', '/sport-matching', '/rides']),
        item('organization', 'shell.spaces.organization', 'las la-sitemap', organizationHome.value, ['/teams', '/clubs', '/club-memberships', '/files', '/admin']),
        item('discover', 'shell.spaces.discover', 'las la-compass', route('guest.marketplace'), ['/marketplace', '/learning', '/blog', '/outfit-subscriptions', '/abos']),
    ])

    const unreadNotifications = computed(() => page.props.notificationCenter?.unread_count || 0)
    const unreadChats = computed(() => page.props.unreadChatsCount || 0)
    const pendingFriends = computed(() => page.props.friendCenter?.pending_received_count || 0)

    const groups = computed(() => {
        const result = [
            {
                key: 'today',
                label: 'shell.spaces.today',
                icon: 'las la-sun',
                items: [
                    item('home', 'shell.items.home', 'las la-home', roleHome.value, ['/dashboard', '/workspaces', '/club-cockpit', '/trainer-cockpit', '/sponsor-cockpit']),
                    can('notifications.view') ? item('notifications', 'shell.items.notifications', 'las la-bell', route('auth.notifications.index'), ['/notifications'], unreadNotifications.value || null) : null,
                    can('chat.view') ? item('messages', 'shell.items.messages', 'las la-comments', route('auth.conversations.index'), ['/conversations'], unreadChats.value || null) : null,
                ].filter(Boolean),
            },
            {
                key: 'plan',
                label: 'shell.spaces.plan',
                icon: 'las la-calendar-alt',
                items: [
                    (isAthlete.value || isCoach.value) ? item('training', 'shell.items.training', 'las la-dumbbell', route('auth.training.index'), ['/training']) : null,
                    can('event.index') ? item('events', 'shell.items.events', 'las la-calendar-check', route('auth.events.index'), ['/events']) : null,
                    isAthlete.value ? item('nutrition', 'shell.items.nutrition', 'las la-apple-alt', route('auth.nutrition.index'), ['/nutrition']) : null,
                    isAthlete.value ? item('routes', 'shell.items.routes', 'las la-route', route('auth.sport-map.index'), ['/sport-map']) : null,
                    item('courses', 'shell.items.courses', 'las la-graduation-cap', route('auth.learning.my-courses.index'), ['/learning/my-courses']),
                ].filter(Boolean),
            },
            {
                key: 'community',
                label: 'shell.spaces.community',
                icon: 'las la-comments',
                items: [
                    item('feed', 'shell.items.feed', 'las la-newspaper', route('auth.feed.index'), ['/feed']),
                    can('chat.view') ? item('messages', 'shell.items.messages', 'las la-comments', route('auth.conversations.index'), ['/conversations'], unreadChats.value || null) : null,
                    can('team.index') ? item('teams', 'shell.items.teams', 'las la-users', route('auth.teams.index'), ['/teams', '/clubs']) : null,
                    isAthlete.value && can('friends.view') ? item('friends', 'shell.items.friends', 'las la-user-friends', route('auth.friends.index'), ['/friends'], pendingFriends.value || null) : null,
                    isAthlete.value ? item('matching', 'shell.items.matching', 'las la-random', route('auth.sport-matching.index'), ['/sport-matching']) : null,
                    isAthlete.value && can('rides.view') ? item('rides', 'shell.items.rides', 'las la-car', route('auth.rides.index'), ['/rides']) : null,
                ].filter(Boolean),
            },
            {
                key: 'organization',
                label: 'shell.spaces.organization',
                icon: 'las la-sitemap',
                items: [
                    isClub.value && can('club-cockpit.view') ? item('club-cockpit', 'shell.items.club_cockpit', 'las la-tachometer-alt', route('auth.club-cockpit.index'), ['/club-cockpit']) : null,
                    (isCoach.value || isClub.value) && can('team.index') ? item('teams', 'shell.items.teams', 'las la-users', route('auth.teams.index'), ['/teams', '/clubs']) : null,
                    isClub.value && can('club-memberships.view') ? item('members', 'shell.items.members', 'las la-id-card', route('auth.club-memberships.index'), ['/club-memberships']) : null,
                    can('file.index') ? item('files', 'shell.items.files', 'las la-folder-open', route('auth.files.index'), ['/files']) : null,
                    isSponsor.value ? item('sponsor', 'shell.items.sponsor', 'las la-handshake', route('auth.sponsor-workspace.index'), ['/sponsor-cockpit']) : null,
                ].filter(Boolean),
            },
            {
                key: 'discover',
                label: 'shell.spaces.discover',
                icon: 'las la-compass',
                items: [
                    item('marketplace', 'shell.items.marketplace', 'las la-shopping-bag', route('guest.marketplace'), ['/marketplace']),
                    item('courses', 'shell.items.courses', 'las la-graduation-cap', route('auth.learning.my-courses.index'), ['/learning']),
                    item('blog', 'shell.items.blog', 'las la-pen-nib', route('guest.blog.index'), ['/blog']),
                    isAthlete.value ? item('outfit', 'shell.items.outfit', 'las la-tshirt', route('auth.outfit-subscriptions.index'), ['/outfit-subscriptions']) : null,
                    item('plans', 'shell.items.plans', 'las la-rocket', route('guest.pricing'), ['/abos']),
                ].filter(Boolean),
            },
        ]

        const canOperate = hasAny([
            'users.view', 'roles.manage', 'blog.view', 'blog.manage', 'payments.view',
            'invoices.view', 'subscriptions.view', 'outfit-subscriptions.manage',
            'sponsors.view', 'admin.moderation.view', 'admin.settings.view', 'system.manage',
        ])

        if (canOperate) {
            result.push({
                key: 'operations',
                label: 'shell.spaces.operations',
                icon: 'las la-shield-alt',
                items: [
                    can('users.view') ? item('users', 'shell.items.users', 'las la-users-cog', route('members.index'), ['/admin/users', '/admin/members']) : null,
                    can('roles.manage') ? item('roles', 'shell.items.roles', 'las la-user-shield', route('roles-permissions.index'), ['/admin/roles']) : null,
                    can('admin.moderation.view') ? item('moderation', 'shell.items.moderation', 'las la-user-check', route('admin.moderation.index'), ['/admin/moderation']) : null,
                    can('subscriptions.view') ? item('commerce', 'shell.items.commerce', 'las la-chart-line', route('admin.commerce.index'), ['/admin/commerce', '/admin/subscriptions', '/admin/payments', '/admin/invoices']) : null,
                    can('blog.view') ? item('content', 'shell.items.content', 'las la-pen-nib', route('blogs.index'), ['/admin/blogs']) : null,
                    can('sponsors.view') ? item('sponsors', 'shell.items.sponsors', 'las la-handshake', route('sponsors.index'), ['/admin/sponsors']) : null,
                    can('admin.settings.view') ? item('settings', 'shell.items.system', 'las la-cog', route('admin.settings.index'), ['/admin/settings']) : null,
                ].filter(Boolean),
            })
        }

        return result.filter((group) => group.items.length)
    })

    return {
        groups,
        primarySpaces,
        roleHome,
        workspace,
        isPlatform,
        isCoach,
        isClub,
        isSponsor,
        isAthlete,
    }
}
