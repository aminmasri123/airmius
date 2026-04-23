// 👉 Diese Imports haben gefehlt oder waren unvollständig:
import { useI18n } from 'vue-i18n'
import { router, usePage } from '@inertiajs/vue3'
import { watch } from 'vue'

export function useLanguage() {
    // Jetzt sind diese Funktionen definiert:
    const { locale } = useI18n()
    const page = usePage()

    const languages = [
        { code: 'de', label: 'Deutsch 🇩🇪' },
        { code: 'en', label: 'English 🇬🇧' },
        { code: 'fr', label: 'Français 🇫🇷' },
    ]

    // Watcher: Synchronisiert die Anzeige sofort mit dem Server-Wert
    watch(() => page.props.locale, (newLocale) => {
        if (newLocale) {
            locale.value = newLocale
            localStorage.setItem('lang', newLocale)
        }
    }, { immediate: true })

    const changeLang = (lang) => {
        router.post(route('user.language.update'), {
            language: lang
        }, {
            preserveScroll: true,
            onSuccess: () => {
                // Keine manuelle Zuweisung nötig, der Watcher oben regelt das!
            }
        })
    }

    return {
        locale,
        languages,
        changeLang
    }
}
