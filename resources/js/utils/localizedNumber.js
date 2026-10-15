export function normalizeLocalizedDecimal(value) {
    const text = String(value ?? '')
        .trim()
        .replace(/[\s\u00a0\u202f]/g, '')

    if (!/^[+-]?\d+(?:[.,]\d+)?$/.test(text)) {
        return null
    }

    const normalized = text.replace(',', '.')
    const number = Number(normalized)

    return Number.isFinite(number) ? number : null
}

export function decimalToMinorUnits(value, fractionDigits = 2) {
    const number = normalizeLocalizedDecimal(value)

    if (number === null || !Number.isInteger(fractionDigits) || fractionDigits < 0 || fractionDigits > 6) {
        return null
    }

    return Math.round(number * (10 ** fractionDigits))
}
