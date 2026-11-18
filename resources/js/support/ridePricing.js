import copy from '../../data/ride-pricing-copy.json'

export const ridePricingText = (locale, key) => (copy[String(locale).split('-')[0]] || copy.en)[key] || key
export const ridePriceLabel = (pricing, locale = 'en') => {
    if (pricing?.mode === 'free') return ridePricingText(locale, 'free')
    if (pricing?.mode !== 'contribution') return ridePricingText(locale, 'unspecified')
    const amount = new Intl.NumberFormat(locale, { style: 'currency', currency: pricing.currency, currencyDisplay: 'code', minimumFractionDigits: pricing.digits, maximumFractionDigits: pricing.digits }).format(Number(pricing.amount))
    return `${amount} ${ridePricingText(locale, 'per_person')}`
}
