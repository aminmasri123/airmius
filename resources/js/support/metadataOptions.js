export function validateMetadataOptions(values) {
    if (!Array.isArray(values) || values.length === 0) return { list: 'optionEmpty', rows: [] }
    const normalized = values.map(value => String(value ?? '').trim().toLowerCase())
    return {
        list: values.length > 50 ? 'optionLimit' : null,
        rows: values.map((value, index) => {
            const trimmed = String(value ?? '').trim()
            if (!trimmed) return 'optionEmpty'
            if (trimmed.length > 160) return 'optionTooLong'
            if (normalized.filter(candidate => candidate === normalized[index]).length > 1) return 'optionDuplicate'
            return null
        }),
    }
}
