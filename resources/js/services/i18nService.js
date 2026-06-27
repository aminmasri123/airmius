import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { watch } from 'vue'

export const rtlLocales = ['ar']

export const localeDirection = (locale) => (rtlLocales.includes(locale) ? 'rtl' : 'ltr')

export function useLanguage() {
    const { locale } = useI18n()
    const page = usePage()

    const languages = [
        { code: 'de', label: 'Deutsch', native: 'Deutsch' },
        { code: 'en', label: 'English', native: 'English' },
        { code: 'fr', label: 'French', native: 'Français' },
        { code: 'ar', label: 'Arabic', native: 'العربية' },
    ]

    watch(() => page.props.locale, (newLocale) => {
        if (newLocale) {
            locale.value = newLocale
            localStorage.setItem('lang', newLocale)
            document.documentElement.lang = newLocale
            document.documentElement.dir = localeDirection(newLocale)
        }
    }, { immediate: true })

    const changeLang = (lang) => {
        return router.post(route('user.language.update'), {
            language: lang
        }, {
            preserveScroll: true,
        })
    }

    return {
        locale,
        languages,
        changeLang
    }
}

