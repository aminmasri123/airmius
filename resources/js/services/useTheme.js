import { ref } from 'vue'

const isDark = ref(false)
const themes = ['air', 'dark', 'womanly', 'champion', 'sprint', 'arena', 'pulse', 'trail', 'bazaar', 'vital']

const setTheme = (theme) => {
    const nextTheme = themes.includes(theme) ? theme : 'air'

    document.documentElement.classList.remove(...themes.map((theme) => `theme-${theme}`))
    document.documentElement.classList.add(`theme-${nextTheme}`)

    isDark.value = nextTheme === 'dark'
    localStorage.setItem('theme', nextTheme)
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
