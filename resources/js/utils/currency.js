export const centsToMajor = (cents) => {
    if (cents === null || cents === undefined || cents === '') {
        return ''
    }

    return (Number(cents || 0) / 100).toFixed(2).replace('.', ',')
}

export const majorToCents = (value) => {
    if (value === null || value === undefined || value === '') {
        return 0
    }

    const normalized = String(value)
        .trim()
        .replace(/\s/g, '')
        .replace(/[^\d,.-]/g, '')

    if (!normalized) {
        return 0
    }

    const lastComma = normalized.lastIndexOf(',')
    const lastDot = normalized.lastIndexOf('.')
    const decimalSeparator = lastComma > lastDot ? ',' : '.'
    const integerPart = normalized.slice(0, Math.max(lastComma, lastDot)).replace(/[^\d-]/g, '')
    const decimalPart = normalized.slice(Math.max(lastComma, lastDot) + 1).replace(/\D/g, '')
    const numberValue = Math.max(0, Number(`${integerPart || '0'}.${decimalPart.padEnd(2, '0').slice(0, 2)}`))

    if (lastComma === -1 && lastDot === -1) {
        return Math.round(Math.max(0, Number(normalized.replace(/[^\d-]/g, '') || 0)) * 100)
    }

    return Math.round(numberValue * 100)
}

export const moneyInputAttrs = {
    type: 'text',
    inputmode: 'decimal',
    autocomplete: 'off',
}

export const transformMoneyFields = (data, fields) => ({
    ...data,
    ...Object.fromEntries(fields.map((field) => [field, majorToCents(data[field])])),
})
