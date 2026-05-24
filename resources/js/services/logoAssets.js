export const LOGO_MARK = '/img/logo/Logo-Airmius.png'
export const LOGO_WORDMARK_LIGHT = '/img/logo/Logo-Airmius-Quervormat.png'
export const LOGO_WORDMARK_DARK = '/img/logo/LOGO-Dark-Airmius-Quervormat.png'
export const LOGO_FULL_LIGHT = '/img/logo/Logo-Airmius-mit-Schrift.png'
export const LOGO_FULL_DARK = '/img/logo/Logo-Dark-Airmius-mit-Schrift.png'

export const logoWordmark = (isDark = false) => (isDark ? LOGO_WORDMARK_DARK : LOGO_WORDMARK_LIGHT)
export const logoFull = (isDark = false) => (isDark ? LOGO_FULL_DARK : LOGO_FULL_LIGHT)

export const applyLogoFallback = (event, fallback = LOGO_WORDMARK_LIGHT) => {
    const image = event?.target

    if (!image || image.dataset.logoFallbackApplied) {
        return
    }

    image.dataset.logoFallbackApplied = '1'
    image.src = fallback
}
