import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { activateApplicationLocale } from './localeRuntime'

export const rtlLocales = ['ar']

export const localeDirection = (locale) => (rtlLocales.includes(locale) ? 'rtl' : 'ltr')

export function useLanguage() {
    const { locale } = useI18n()

    const languages = [
        { code: 'de', label: 'Deutsch', native: 'Deutsch' },
        { code: 'en', label: 'English', native: 'English' },
        { code: 'fr', label: 'French', native: 'Français' },
        { code: 'ar', label: 'Arabic', native: 'العربية' },
    ]

    const changeLang = (lang) => new Promise((resolve, reject) => {
        router.post(route('user.language.update'), {
            language: lang,
        }, {
            preserveScroll: true,
            onSuccess: async (page) => {
                try {
                    const activeLocale = await activateApplicationLocale(page.props.locale || lang)
                    if (activeLocale !== lang) {
                        throw new Error(`Locale activation fell back to ${activeLocale}`)
                    }
                    resolve(true)
                } catch (error) {
                    reject(error)
                }
            },
            onError: reject,
            onCancel: () => reject(new Error('Language change cancelled')),
        })
    })

    return {
        locale,
        languages,
        changeLang
    }
}
