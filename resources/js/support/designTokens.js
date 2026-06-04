import designTokens from '@/../design-tokens.json'

export { designTokens }

export const themeTokens = (theme = 'dark') => designTokens.themes[theme] || designTokens.themes.dark
