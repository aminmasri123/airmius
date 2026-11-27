// The public catalog also contains cross-plan requirements. Keep them separate
// from the plan's own features; never present those requirements as inclusions.
export function splitPlanDetails(plan) {
    if (!plan) return { included: [], notes: [] }
    const features = plan.features || []
    const count = plan.included_feature_count
    if (Number.isInteger(count) && count >= 0 && count <= features.length) {
        return { included: features.slice(0, count), notes: features.slice(count) }
    }
    return {
        included: features.filter(feature => String(feature).length <= 180),
        notes: features.filter(feature => String(feature).length > 180),
    }
}

const topics = [
    ['finance', /beitrag|rechnung|sepa|bank|datev|zahlung|abrechnung/iu],
    ['privacy', /eltern|erziehungs|familie|einwilligung|bildrecht|fotozustimmung|minderjähr/iu],
    ['files', /datei|dateien|ordner|dokument|download|pdf|ablage/iu],
    ['partners', /sponsor|werb|anzeige|kampagne|marketplace|produkt|verkäufer|käufer|kurs|camp/iu],
    ['training', /training|trainer|sport|übung|saison|belastung|route|gps|ernährung|\bki\b/iu],
    ['organization', /verein|team|mitglied|einladung|eingeladen|standort|inventar|aufnahme|rolle/iu],
]

export function groupUsageNotes(notes) {
    const groups = new Map([...topics.map(([key]) => [key, []]), ['general', []]])
    for (const note of notes) {
        // Classify the opening, so long notes mentioning several areas stay intact.
        const opening = String(note).slice(0, 180)
        const topic = topics.find(([, pattern]) => pattern.test(opening))?.[0] || 'general'
        groups.get(topic).push(note)
    }
    return [...groups].filter(([, items]) => items.length).map(([key, items]) => ({ key, items }))
}
