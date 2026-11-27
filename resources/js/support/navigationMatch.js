// Only explicitly declared query keys select a navigation view. Search, paging
// and other filters must not change which section is marked as current.
export const matchesNavigation = (currentUrl, paths = [], activeQuery = {}) => {
    const base = 'http://navigation.local'
    const current = new URL(currentUrl || '/', base)
    const matchesPath = paths.filter(Boolean).some((candidate) => {
        const path = new URL(candidate, base).pathname.replace(/\/$/, '') || '/'
        return current.pathname === path || (path !== '/' && current.pathname.startsWith(`${path}/`))
    })

    return matchesPath && Object.entries(activeQuery).every(([key, value]) =>
        (current.searchParams.get(key) || null) === value,
    )
}
