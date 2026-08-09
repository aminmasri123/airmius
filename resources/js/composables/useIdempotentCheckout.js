import axios from 'axios'

export const createCheckoutRequestId = (scope = 'checkout') => {
    const reference = window.crypto?.randomUUID
        ? window.crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`

    return `${String(scope).replace(/[^A-Za-z0-9_.:-]/g, '-')}:${reference}`
}

export const postIdempotentCheckout = (url, payload, requestId) => axios.post(url, payload, {
    headers: {
        Accept: 'application/json',
        'X-Checkout-Mode': 'json',
        'Idempotency-Key': requestId,
    },
})

export const checkoutRedirectUrl = (response) => response?.data?.redirect_url
    || response?.data?.payment_action?.url
    || response?.data?.data?.payment_action?.url
    || response?.data?.data?.checkout_url
    || null

export const applyCheckoutValidationErrors = (form, error) => {
    const errors = error?.response?.data?.errors || error?.response?.data?.error?.fields || {}

    form?.clearErrors?.()

    Object.entries(errors).forEach(([field, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : messages

        if (message) {
            form?.setError?.(field, message)
        }
    })

    return Object.values(errors)
        .flatMap((messages) => Array.isArray(messages) ? messages : [messages])
        .find(Boolean)
        || error?.response?.data?.message
        || error?.response?.data?.error?.message
        || null
}

export const hasKnownCheckoutResponse = (error) => Boolean(error?.response?.status)

export const checkoutFallback = (locale, key) => {
    const language = String(locale || 'de').split('-')[0]
    const copy = {
        de: {
            missing_redirect: 'Der Zahlungsanbieter hat keinen Weiterleitungslink geliefert. Bitte versuche es erneut.',
            start_failed: 'Checkout konnte nicht gestartet werden. Bitte versuche es erneut.',
            validation_failed: 'Bitte prüfe die markierten Felder.',
        },
        en: {
            missing_redirect: 'The payment provider did not return a redirect link. Please try again.',
            start_failed: 'Checkout could not be started. Please try again.',
            validation_failed: 'Please check the highlighted fields.',
        },
        fr: {
            missing_redirect: 'Le prestataire de paiement n’a fourni aucun lien de redirection. Veuillez réessayer.',
            start_failed: 'Le paiement n’a pas pu démarrer. Veuillez réessayer.',
            validation_failed: 'Veuillez vérifier les champs indiqués.',
        },
        ar: {
            missing_redirect: 'لم يرسل مزود الدفع رابط إعادة التوجيه. يرجى المحاولة مرة أخرى.',
            start_failed: 'تعذر بدء الدفع. يرجى المحاولة مرة أخرى.',
            validation_failed: 'يرجى مراجعة الحقول المحددة.',
        },
    }

    return copy[language]?.[key] || copy.de[key] || key
}
