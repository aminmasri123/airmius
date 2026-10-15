const truthyConsent = (value) => value === true || value === 1 || value === '1'

export const COOKIE_CONSENT_STORAGE_KEY = 'airmius_cookie_consent_v1'
export const COOKIE_CONSENT_COOKIE_NAME = 'airmius_cookie_consent_v1'
export const COOKIE_CONSENT_EVENT = 'airmius:cookie-consent-changed'
export const OPEN_COOKIE_PREFERENCES_EVENT = 'airmius:open-cookie-preferences'

const normalizedBrowserConsent = (value) => {
    if (!value || value.version !== 1) {
        return null
    }

    return {
        version: 1,
        necessary: true,
        analytics: truthyConsent(value.analytics),
        marketing: truthyConsent(value.marketing),
        updatedAt: typeof value.updatedAt === 'string' ? value.updatedAt : null,
    }
}

const readConsentCookie = () => {
    if (typeof document === 'undefined') {
        return null
    }

    const prefix = `${COOKIE_CONSENT_COOKIE_NAME}=`
    const entry = document.cookie.split('; ').find((cookie) => cookie.startsWith(prefix))

    if (!entry) {
        return null
    }

    try {
        return JSON.parse(decodeURIComponent(entry.slice(prefix.length)))
    } catch (error) {
        return null
    }
}

export const readBrowserCookieConsent = () => {
    if (typeof window === 'undefined') {
        return null
    }

    try {
        const stored = window.localStorage.getItem(COOKIE_CONSENT_STORAGE_KEY)
        const parsed = stored ? JSON.parse(stored) : readConsentCookie()

        return normalizedBrowserConsent(parsed)
    } catch (error) {
        return normalizedBrowserConsent(readConsentCookie())
    }
}

export const saveBrowserCookieConsent = (preferences = {}) => {
    const consent = normalizedBrowserConsent({
        version: 1,
        necessary: true,
        analytics: preferences.analytics,
        marketing: preferences.marketing,
        updatedAt: new Date().toISOString(),
    })

    if (!consent || typeof window === 'undefined') {
        return consent
    }

    const serialized = JSON.stringify(consent)
    window.localStorage.setItem(COOKIE_CONSENT_STORAGE_KEY, serialized)

    const secure = window.location.protocol === 'https:' ? '; Secure' : ''
    document.cookie = `${COOKIE_CONSENT_COOKIE_NAME}=${encodeURIComponent(serialized)}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`
    window.dispatchEvent(new CustomEvent(COOKIE_CONSENT_EVENT, { detail: consent }))

    return consent
}

export const openCookiePreferences = () => {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent(OPEN_COOKIE_PREFERENCES_EVENT))
    }
}

const consentValue = (pageProps = {}, key) => {
    const userValue = pageProps?.auth?.user?.[key]

    if (userValue !== undefined && userValue !== null) {
        return userValue
    }

    if (pageProps?.privacyConsent?.source === 'user_settings') {
        return pageProps?.privacyConsent?.[key] ?? false
    }

    const browserConsent = readBrowserCookieConsent()

    if (key === 'ads_personalization_consent') {
        return browserConsent?.marketing ?? false
    }

    if (key === 'ads_measurement_consent' || key === 'product_analytics_consent') {
        return browserConsent?.analytics ?? false
    }

    return false
}

export const hasAdsMeasurementConsent = (pageProps = {}) => (
    truthyConsent(consentValue(pageProps, 'ads_measurement_consent'))
)

export const hasAdsPersonalizationConsent = (pageProps = {}) => (
    truthyConsent(consentValue(pageProps, 'ads_personalization_consent'))
)

export const canTrackMarketingEvent = (pageProps = {}) => hasAdsMeasurementConsent(pageProps)
