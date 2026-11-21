const copy = {
    de: {
        check: 'Bitte prüfe die markierten Felder.', saving: 'Wird übermittelt …',
        success: 'Änderung erfolgreich übermittelt.', accepted: 'Anfrage angenommen. Die Verarbeitung läuft.',
        failed: 'Die Änderung konnte nicht gespeichert werden. Deine Eingaben bleiben erhalten.',
        network: 'Keine Verbindung zum Server. Deine Eingaben bleiben erhalten. Bitte versuche es erneut.',
        session: 'Deine Sitzung ist abgelaufen. Bitte melde dich erneut an. Kopiere vorher wichtige Eingaben.',
        forbidden: 'Du hast für diese Aktion keine Berechtigung.',
        missing: 'Der angeforderte Eintrag ist nicht mehr verfügbar.',
        limit: 'Zu viele Anfragen. Bitte warte einen Moment und versuche es erneut.',
        required: 'Bitte fülle dieses Feld aus.', invalid: 'Bitte prüfe die Angabe in diesem Feld.',
        email: 'Bitte gib eine gültige E-Mail-Adresse ein.', number: 'Bitte gib eine gültige Zahl ein.',
        short: 'Diese Angabe ist zu kurz.', long: 'Diese Angabe ist zu lang.',
        min: 'Die Zahl ist zu klein.', max: 'Die Zahl ist zu groß.', close: 'Meldung schließen',
    },
    en: {
        check: 'Please check the highlighted fields.', saving: 'Submitting …',
        success: 'Change submitted successfully.', accepted: 'Request accepted. Processing is in progress.',
        failed: 'The change could not be saved. Your entries have been preserved.',
        network: 'Unable to connect to the server. Your entries have been preserved. Please try again.',
        session: 'Your session has expired. Please sign in again. Copy any important entries first.',
        forbidden: 'You do not have permission for this action.', missing: 'The requested entry is no longer available.',
        limit: 'Too many requests. Please wait a moment and try again.',
        required: 'Please fill in this field.', invalid: 'Please check this field.', email: 'Please enter a valid email address.',
        number: 'Please enter a valid number.', short: 'This entry is too short.', long: 'This entry is too long.',
        min: 'The number is too small.', max: 'The number is too large.', close: 'Dismiss message',
    },
    fr: {
        check: 'Veuillez vérifier les champs signalés.', saving: 'Envoi en cours …',
        success: 'Modification envoyée avec succès.', accepted: 'Demande acceptée. Traitement en cours.',
        failed: 'Enregistrement impossible. Vos saisies sont conservées.',
        network: 'Connexion au serveur impossible. Vos saisies sont conservées. Veuillez réessayer.',
        session: 'Votre session a expiré. Reconnectez-vous. Copiez d’abord les saisies importantes.',
        forbidden: 'Vous n’avez pas l’autorisation nécessaire.', missing: 'Cette entrée n’est plus disponible.',
        limit: 'Trop de demandes. Patientez un moment avant de réessayer.',
        required: 'Veuillez remplir ce champ.', invalid: 'Veuillez vérifier ce champ.', email: 'Saisissez une adresse e-mail valide.',
        number: 'Saisissez un nombre valide.', short: 'Cette saisie est trop courte.', long: 'Cette saisie est trop longue.',
        min: 'Le nombre est trop petit.', max: 'Le nombre est trop grand.', close: 'Fermer le message',
    },
    ar: {
        check: 'يرجى مراجعة الحقول المحددة.', saving: 'جارٍ الإرسال …',
        success: 'تم إرسال التغيير بنجاح.', accepted: 'تم قبول الطلب. المعالجة جارية.',
        failed: 'تعذر حفظ التغيير. تم الاحتفاظ بمدخلاتك.',
        network: 'تعذر الاتصال بالخادم. تم الاحتفاظ بمدخلاتك. يرجى المحاولة مجددًا.',
        session: 'انتهت جلستك. سجّل الدخول مجددًا وانسخ المدخلات المهمة أولاً.',
        forbidden: 'ليست لديك صلاحية لهذا الإجراء.', missing: 'لم يعد هذا السجل متاحًا.',
        limit: 'طلبات كثيرة جدًا. انتظر قليلاً ثم حاول مجددًا.',
        required: 'يرجى تعبئة هذا الحقل.', invalid: 'يرجى مراجعة هذا الحقل.', email: 'أدخل بريدًا إلكترونيًا صالحًا.',
        number: 'أدخل رقمًا صالحًا.', short: 'هذا الإدخال قصير جدًا.', long: 'هذا الإدخال طويل جدًا.',
        min: 'الرقم صغير جدًا.', max: 'الرقم كبير جدًا.', close: 'إغلاق الرسالة',
    },
}
export const formFeedbackText = (key, locale = globalThis.document?.documentElement?.lang) =>
    (copy[String(locale || 'de').split(/[-_]/)[0]] || copy.de)[key] || copy.de[key]

export function safeFieldMessage(value, locale) {
    const text = String(Array.isArray(value) ? value[0] || '' : value || '').trim()
    // Never render HTML, exception dumps, SQL or server paths as field errors.
    if (!text || text.length > 500 || /<[^>]+>|SQLSTATE|Stack trace|Exception|\/var\/|[A-Z]:\\|vendor\//i.test(text)) return formFeedbackText('invalid', locale)
    return text
}
export function formFailureText(status, locale) {
    const key = !status ? 'network' : [401, 419].includes(status) ? 'session'
        : status === 403 ? 'forbidden' : status === 404 ? 'missing' : status === 429 ? 'limit'
            : status === 422 ? 'check' : 'failed'
    return formFeedbackText(key, locale)
}
