import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const permissionDeniedMessage = 'Du hast dafür keine Berechtigung.'
const defaultBackendPermissionMessages = new Set([
    'Forbidden',
    'This action is forbidden.',
    'This action is unauthorized.',
])

export function useGlobalFeedback(page) {
    const { t, locale, messages } = useI18n()
    const tx = (key, fallback, values = {}) => {
        const auto = messages.value?.[locale.value]?.auto?.[key]
        if (auto) return auto
        const translated = t(key, values)
        return translated === key ? fallback : translated
    }
    const feedbackMessages = ref([])

    let feedbackId = 0
    let stopInertiaSuccess = null
    let stopInertiaError = null
    let stopInertiaInvalid = null
    let stopInertiaException = null

    const feedbackTimers = new Map()
    const recentFeedback = new Map()
    const shownFlashIds = new Set()

    const removeFeedback = (id) => {
        feedbackMessages.value = feedbackMessages.value.filter((message) => message.id !== id)

        if (feedbackTimers.has(id)) {
            window.clearTimeout(feedbackTimers.get(id))
            feedbackTimers.delete(id)
        }
    }

    const addFeedback = (type, message, flashId = null) => {
        const text = String(message || '').trim()

        if (!text) return

        if (flashId) {
            const flashKey = `${type}:${flashId}`

            if (shownFlashIds.has(flashKey)) return

            shownFlashIds.add(flashKey)
        }

        const signature = `${type}:${text}`
        const now = Date.now()
        const recentUntil = recentFeedback.get(signature) || 0

        if (!flashId && recentUntil > now) return

        if (!flashId) {
            recentFeedback.set(signature, now + 12000)
        }

        recentFeedback.forEach((until, key) => {
            if (until <= now) {
                recentFeedback.delete(key)
            }
        })

        const existing = feedbackMessages.value.find((item) => item.type === type && item.message === text)

        if (existing) {
            feedbackMessages.value = [
                existing,
                ...feedbackMessages.value.filter((item) => item.id !== existing.id),
            ]

            if (feedbackTimers.has(existing.id)) {
                window.clearTimeout(feedbackTimers.get(existing.id))
            }

            feedbackTimers.set(existing.id, window.setTimeout(() => removeFeedback(existing.id), type === 'error' ? 7000 : 4500))
            return
        }

        const id = ++feedbackId
        feedbackMessages.value.unshift({
            id,
            type,
            message: text,
        })

        if (feedbackMessages.value.length > 4) {
            feedbackMessages.value.slice(4).forEach((item) => removeFeedback(item.id))
        }

        feedbackTimers.set(id, window.setTimeout(() => removeFeedback(id), type === 'error' ? 7000 : 4500))
    }

    const normalizeFeedbackMessage = (message) => {
        const text = String(message || '').trim()

        if (defaultBackendPermissionMessages.has(text)) {
            return tx('global_feedback.permission_denied', permissionDeniedMessage)
        }

        return text
    }

    const firstErrorMessage = (errors) => {
        const values = Object.values(errors || {}).flat()
        const first = values.map(normalizeFeedbackMessage).find((value) => value)

        return first || tx('global_feedback.generic', 'Aktion konnte nicht abgeschlossen werden. Bitte prüfe deine Eingaben.')
    }

    const showFlashFeedback = (flash = {}) => {
        if (flash.success) {
            addFeedback('success', flash.success, flash.id)
        }

        if (flash.error) {
            addFeedback('error', flash.error, flash.id)
        }

        if (flash.message) {
            addFeedback('info', flash.message, flash.id)
        }
    }

    const httpErrorMessage = (status) => {
        if (status === 401) return tx('global_feedback.session_expired', 'Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.')
        if (status === 403) return tx('global_feedback.permission_denied', permissionDeniedMessage)
        if (status === 404) return tx('global_feedback.not_found', 'Der angeforderte Inhalt wurde nicht gefunden.')
        if (status === 419) return tx('global_feedback.session_reload', 'Die Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.')
        if (status === 422) return tx('global_feedback.validation', 'Bitte prüfe die Eingaben.')
        if (status >= 500) return tx('global_feedback.server', 'Serverfehler. Bitte versuche es gleich erneut.')

        return tx('global_feedback.action_failed', 'Aktion konnte nicht abgeschlossen werden.')
    }

    const installGlobalFeedback = () => {
        showFlashFeedback(page.props.flash || {})

        stopInertiaSuccess = router.on('success', (event) => {
            showFlashFeedback(event.detail.page.props.flash || {})
        })

        stopInertiaError = router.on('error', (event) => {
            addFeedback('error', firstErrorMessage(event.detail.errors || {}))
        })

        stopInertiaInvalid = router.on('invalid', (event) => {
            event.preventDefault()
            addFeedback('error', httpErrorMessage(event.detail.response?.status))
        })

        stopInertiaException = router.on('exception', (event) => {
            event.preventDefault()
            addFeedback('error', tx('global_feedback.unexpected', 'Unerwarteter Fehler. Bitte versuche es erneut.'))
        })
    }

    const uninstallGlobalFeedback = () => {
        stopInertiaSuccess?.()
        stopInertiaError?.()
        stopInertiaInvalid?.()
        stopInertiaException?.()

        feedbackTimers.forEach((timer) => window.clearTimeout(timer))
        feedbackTimers.clear()
        recentFeedback.clear()
        shownFlashIds.clear()
    }

    return {
        addFeedback,
        feedbackMessages,
        installGlobalFeedback,
        removeFeedback,
        uninstallGlobalFeedback,
    }
}
