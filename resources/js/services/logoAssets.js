export const LOGO_MARK = '/img/logo/Airmius-Green-Test-Mark.png'
export const LOGO_WORDMARK_LIGHT = '/img/logo/Airmius-Green-Test-Logo-Light.png'
export const LOGO_WORDMARK_DARK = '/img/logo/Airmius-Green-Test-Logo-Dark.png'
export const LOGO_FULL_LIGHT = '/img/logo/Airmius-Green-Test-Logo-Light.png'
export const LOGO_FULL_DARK = '/img/logo/Airmius-Green-Test-Logo-Dark.png'

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
