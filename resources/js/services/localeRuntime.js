let localeActivator = null

export const registerLocaleActivator = (activator) => {
    localeActivator = typeof activator === 'function' ? activator : null
}

export const activateApplicationLocale = (locale) => {
    if (!localeActivator) {
        return Promise.resolve(locale)
    }

    return localeActivator(locale)
}
