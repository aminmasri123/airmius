export const localDateTime = (value) => {
    if (!value) return ''
    const date = new Date(value)
    if (!Number.isFinite(date.getTime())) return ''
    const pad = (part) => String(part).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export const apiDateTime = (value) => value ? new Date(value).toISOString() : null

export const planningError = (error) => Object.values(error.response?.data?.errors || {}).flat().join(' ')
    || error.response?.data?.message || error.message

export const seasonDraft = (plan) => ({
    id: plan.id, revision: plan.revision, name: plan.name, sport_year_period_id: plan.sport_year_period_id,
    goals: plan.goals || '', status: plan.status,
    phases: plan.phases.map((phase) => ({ ...phase })),
    event_ids: plan.events.map((event) => event.id),
})
