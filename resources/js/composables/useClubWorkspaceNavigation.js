import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { usePermissions } from '@/composables/usePermissions'

export const useClubWorkspaceNavigation = () => {
    const page = usePage()
    const { can } = usePermissions()
    const hasClubMembership = computed(() => Boolean(page.props.workspaceContext?.clubs?.length || page.props.auth?.user?.clubs?.length || page.props.auth?.user?.club_count))

    const items = computed(() => [
        can('club-cockpit.view')
            ? {
                key: 'cockpit',
                label: 'Vereins-Cockpit',
                href: route('auth.club-cockpit.index'),
                icon: 'las la-tachometer-alt',
                activePaths: ['/club-cockpit'],
            }
            : null,
        can('club-cockpit.view') || hasClubMembership.value
            ? {
                key: 'todos',
                label: 'To-dos',
                href: `${route('auth.club-cockpit.index')}?panel=tasks`,
                icon: 'las la-tasks',
                activePaths: ['/club-cockpit'],
            }
            : null,
        can('club-cockpit.view')
            ? {
                key: 'calendar',
                label: 'Kalender',
                href: `${route('auth.club-cockpit.index')}?panel=calendar`,
                icon: 'las la-calendar-alt',
                activePaths: ['/club-cockpit'],
            }
            : null,
        can('team.index')
            ? {
                key: 'structure',
                label: 'Vereine & Teams',
                href: route('auth.teams.index'),
                icon: 'las la-sitemap',
                activePaths: ['/teams', '/clubs'],
            }
            : null,
        can('club-memberships.view')
            ? {
                key: 'memberships',
                label: 'Mitglieder & Finanzen',
                href: route('auth.club-memberships.index'),
                icon: 'las la-id-card',
                activePaths: ['/club-memberships'],
            }
            : null,
        can('club-memberships.view')
            ? {
                key: 'inventory',
                label: 'Inventar & Ausleihe',
                href: route('auth.club-inventory.index'),
                icon: 'las la-boxes',
                activePaths: ['/club-inventory'],
            }
            : null,
    ].filter(Boolean))

    const hasItems = computed(() => items.value.length > 0)

    return { items, hasItems }
}
