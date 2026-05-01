import { usePage } from '@inertiajs/vue3'

export const usePermissions = () => {
    const page = usePage()

    const can = (permission) => Boolean(page.props.auth?.user?.can?.[permission])
    const hasRole = (role) => page.props.auth?.user?.roles?.includes(role) || false
    const hasAny = (permissions) => permissions.some((permission) => can(permission))

    return { can, hasRole, hasAny }
}
