import { ref } from 'vue'

const isDark = ref(false)

const setTheme = (theme) => {
    document.documentElement.classList.remove('theme-air', 'theme-dark', 'theme-womanly')
    document.documentElement.classList.add(`theme-${theme}`)

    isDark.value = theme === 'dark'
    localStorage.setItem('theme', theme)
}

const initTheme = (initialTheme) => {
    setTheme(initialTheme)
}

export function useTheme() {
    return {
        isDark,
        setTheme,
        initTheme,
    }
}
