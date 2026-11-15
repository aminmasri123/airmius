import { useI18n } from 'vue-i18n'

export const exerciseLanguages = { de: 'Deutsch', en: 'English', fr: 'Français', ar: 'العربية' }
const messages = {
    de: {
        library: 'Übungsbibliothek', personal: 'Persönlich', muscles: 'Muskelgruppen', empty: 'Noch keine Übungen vorhanden.', plan: 'Trainingsplan', addToPlan: 'Zum Trainingsplan hinzufügen',
        create: 'Übung anlegen', edit: 'Übung bearbeiten', original: 'Originalsprache', language: 'Inhaltssprache',
        hint: 'Übersetzungen sind optional. Leere Felder verwenden den Originaltext.',
        title: 'Name der Übung', description: 'Beschreibung', instructions: 'Schritt-für-Schritt-Anleitung', notes: 'Wichtige Hinweise', todos: 'Aufgaben · eine pro Zeile',
        images: 'Erklärbilder', imageHint: 'Bis zu 12 Bilder · JPG, PNG oder WebP · maximal 5 MB pro Bild. Die Reihenfolge gilt für alle Sprachen.',
        addImages: 'Bilder hinzufügen', caption: 'Bildunterschrift', alt: 'Bildbeschreibung für Screenreader', up: 'Nach oben', down: 'Nach unten', remove: 'Entfernen',
        save: 'Übung speichern', cancel: 'Abbrechen', leave: 'Ungespeicherte Änderungen verwerfen?',
        fallback: 'Einige Inhalte werden in der Originalsprache angezeigt:', limit: 'Bitte höchstens 12 Bilder mit jeweils maximal 5 MB auswählen.',
        legacyImage: 'Bisheriges Bild', replaceImage: 'Bisheriges Bild ersetzen', progress: 'Wird hochgeladen',
    },
    en: {
        library: 'Exercise library', personal: 'Personal', muscles: 'Muscle groups', empty: 'No exercises yet.', plan: 'Training plan', addToPlan: 'Add to training plan',
        create: 'Create exercise', edit: 'Edit exercise', original: 'Original language', language: 'Content language',
        hint: 'Translations are optional. Empty fields use the original text.',
        title: 'Exercise name', description: 'Description', instructions: 'Step-by-step instructions', notes: 'Important notes', todos: 'Tasks · one per line',
        images: 'Instruction images', imageHint: 'Up to 12 images · JPG, PNG or WebP · maximum 5 MB each. The order applies to all languages.',
        addImages: 'Add images', caption: 'Caption', alt: 'Image description for screen readers', up: 'Move up', down: 'Move down', remove: 'Remove',
        save: 'Save exercise', cancel: 'Cancel', leave: 'Discard unsaved changes?',
        fallback: 'Some content is displayed in its original language:', limit: 'Please select at most 12 images, each no larger than 5 MB.',
        legacyImage: 'Existing image', replaceImage: 'Replace existing image', progress: 'Uploading',
    },
    fr: {
        library: 'Bibliothèque d’exercices', personal: 'Personnel', muscles: 'Groupes musculaires', empty: 'Aucun exercice pour le moment.', plan: 'Plan d’entraînement', addToPlan: 'Ajouter au plan d’entraînement',
        create: 'Créer un exercice', edit: 'Modifier l’exercice', original: 'Langue originale', language: 'Langue du contenu',
        hint: 'Les traductions sont facultatives. Les champs vides utilisent le texte original.',
        title: 'Nom de l’exercice', description: 'Description', instructions: 'Instructions étape par étape', notes: 'Consignes importantes', todos: 'Tâches · une par ligne',
        images: 'Images explicatives', imageHint: '12 images maximum · JPG, PNG ou WebP · 5 Mo maximum par image. L’ordre est commun à toutes les langues.',
        addImages: 'Ajouter des images', caption: 'Légende', alt: 'Description pour les lecteurs d’écran', up: 'Monter', down: 'Descendre', remove: 'Supprimer',
        save: 'Enregistrer l’exercice', cancel: 'Annuler', leave: 'Abandonner les modifications non enregistrées ?',
        fallback: 'Certains contenus sont affichés dans leur langue originale :', limit: 'Sélectionnez au maximum 12 images de 5 Mo chacune.',
        legacyImage: 'Image existante', replaceImage: 'Remplacer l’image existante', progress: 'Envoi en cours',
    },
    ar: {
        library: 'مكتبة التمارين', personal: 'شخصي', muscles: 'المجموعات العضلية', empty: 'لا توجد تمارين بعد.', plan: 'خطة التدريب', addToPlan: 'إضافة إلى خطة التدريب',
        create: 'إنشاء تمرين', edit: 'تعديل التمرين', original: 'اللغة الأصلية', language: 'لغة المحتوى',
        hint: 'الترجمات اختيارية. تستخدم الحقول الفارغة النص الأصلي.',
        title: 'اسم التمرين', description: 'الوصف', instructions: 'التعليمات خطوة بخطوة', notes: 'ملاحظات مهمة', todos: 'المهام · مهمة في كل سطر',
        images: 'صور توضيحية', imageHint: 'حتى 12 صورة · JPG أو PNG أو WebP · بحد أقصى 5 ميغابايت للصورة. الترتيب مشترك بين جميع اللغات.',
        addImages: 'إضافة صور', caption: 'شرح الصورة', alt: 'وصف الصورة لقارئات الشاشة', up: 'نقل للأعلى', down: 'نقل للأسفل', remove: 'إزالة',
        save: 'حفظ التمرين', cancel: 'إلغاء', leave: 'هل تريد تجاهل التغييرات غير المحفوظة؟',
        fallback: 'يُعرض بعض المحتوى بلغته الأصلية:', limit: 'اختر 12 صورة كحد أقصى، ولا يتجاوز حجم كل صورة 5 ميغابايت.',
        legacyImage: 'الصورة الحالية', replaceImage: 'استبدال الصورة الحالية', progress: 'جارٍ الرفع',
    },
}

export function useExerciseCopy() {
    const { locale } = useI18n()
    return (key) => (messages[String(locale.value).split(/[-_]/)[0]] || messages.en)[key] || key
}

// Resolve fields individually, so a partial translation never hides the original instructions.
export function localizedExercise(item, locale) {
    item = { ...item, ...(item.original_content || {}) }
    const language = String(locale).split(/[-_]/)[0]
    const translated = language === item.original_locale ? {} : item.translations?.[language] || {}
    const result = { ...item, uses_original_fallback: false }
    for (const field of ['title', 'description', 'instructions', 'notes', 'todos']) {
        const value = translated[field]
        const hasTranslation = typeof value === 'string' && value.trim() !== ''
        result[field] = hasTranslation ? (field === 'todos' ? value.split(/\r?\n/) : value) : item[field]
        if (item.original_locale && language !== item.original_locale && !hasTranslation && item[field]?.length) result.uses_original_fallback = true
    }
    result.explanation_images = (item.explanation_images || []).map((image) => {
        const value = { ...image }
        for (const field of ['caption', 'alt']) {
            const translation = image.texts?.[language]?.[field]
            const original = image.texts ? image.texts[item.original_locale]?.[field] || '' : image[field] || ''
            value[field] = translation?.trim() ? translation : original
            if (language !== item.original_locale && !translation?.trim() && original) result.uses_original_fallback = true
        }
        return value
    })
    return result
}
