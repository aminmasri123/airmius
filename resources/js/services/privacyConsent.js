const truthyConsent = (value) => value === true || value === 1 || value === '1'

const consentValue = (pageProps = {}, key) => {
    const userValue = pageProps?.auth?.user?.[key]

    if (userValue !== undefined && userValue !== null) {
        return userValue
    }

    return pageProps?.privacyConsent?.[key] ?? false
}

export const hasAdsMeasurementConsent = (pageProps = {}) => (
    truthyConsent(consentValue(pageProps, 'ads_measurement_consent'))
)

export const hasAdsPersonalizationConsent = (pageProps = {}) => (
    truthyConsent(consentValue(pageProps, 'ads_personalization_consent'))
)

export const canTrackMarketingEvent = (pageProps = {}) => hasAdsMeasurementConsent(pageProps)
