export const LOGO_MARK = '/img/logo/Airmius-Mark.png'
export const LOGO_WORDMARK_LIGHT = '/img/logo/Airmius-Logo-Light.png'
export const LOGO_WORDMARK_DARK = '/img/logo/Airmius-Logo-Dark.png'
export const LOGO_FULL_LIGHT = '/img/logo/Airmius-Logo-Light.png'
export const LOGO_FULL_DARK = '/img/logo/Airmius-Logo-Dark.png'
export const LOGO_VERTICAL_LIGHT = '/img/logo/Airmius-Logo-Vertical-Light.png'
export const LOGO_VERTICAL_DARK = '/img/logo/Airmius-Logo-Vertical-Dark.png'

export const logoWordmark = (isDark = false) => (isDark ? LOGO_WORDMARK_DARK : LOGO_WORDMARK_LIGHT)
export const logoFull = (isDark = false) => (isDark ? LOGO_FULL_DARK : LOGO_FULL_LIGHT)
export const logoVertical = (isDark = false) => (isDark ? LOGO_VERTICAL_DARK : LOGO_VERTICAL_LIGHT)

export const applyLogoFallback = (event, fallback = LOGO_WORDMARK_LIGHT) => {
    const image = event?.target

    if (!image || image.dataset.logoFallbackApplied) {
        return
    }

    image.dataset.logoFallbackApplied = '1'
    image.src = fallback
}
