import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'

export const useClubWorkspaceNavigation = () => {
    const { can } = usePermissions()

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
                label: 'Mitglieder & Beiträge',
                href: route('auth.club-memberships.index'),
                icon: 'las la-id-card',
                activePaths: ['/club-memberships'],
            }
            : null,
    ].filter(Boolean))

    const hasItems = computed(() => items.value.length > 0)

    return { items, hasItems }
}
