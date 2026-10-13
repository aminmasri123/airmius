export const pendingKey = (userId, clubId) => `airmius.work-automation.v1:${userId}:${clubId}`

export const createRequest = (form, key = crypto.randomUUID()) => ({
    kind: form.kind,
    idempotency_key: key,
    recipient_roles: [...form.recipient_roles],
    payload: { title: form.title.trim(), body: form.body.trim() },
    scheduled_at: form.scheduled_at ? new Date(form.scheduled_at).toISOString() : null,
})

export const readPending = (storage, key) => {
    try {
        const request = JSON.parse(storage.getItem(key) || 'null')
        return typeof request?.idempotency_key === 'string' && request.payload ? request : null
    } catch { return null }
}

export const uncertainResponse = (error) => !error.response || error.response.status >= 500
