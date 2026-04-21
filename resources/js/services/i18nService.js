import { useI18n } from 'vue-i18n'

export function useLanguage() {
    const { locale } = useI18n()

    const languages = [
        { code: 'de', label: 'Deutsch 🇩🇪' },
        { code: 'en', label: 'English 🇬🇧' },
        { code: 'fr', label: 'Français 🇫🇷' },

    ]

    const changeLang = (lang) => {
        locale.value = lang
        localStorage.setItem('lang', lang)
    }

    return {
        locale,
        languages,
        changeLang
    }
}
