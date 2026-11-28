// Parse whole cents from the entered digits; binary floating point must not
// reject valid fees such as 4.10 EUR or silently round a third decimal place.
export function parseFeeCents(value) {
    const match = /^(\d{1,6})(?:[.,](\d{1,2}))?$/.exec(String(value ?? '').trim())
    if (!match) return null
    return Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0'))
}
