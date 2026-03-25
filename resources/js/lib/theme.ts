import { ref } from 'vue'

export type Theme = 'light' | 'dark'

const STORAGE_KEY = 'pos-theme'
const currentTheme = ref<Theme>('light')

function applyTheme(theme: Theme) {
    const root = document.documentElement
    if (theme === 'dark') {
        root.classList.add('theme-dark')
    } else {
        root.classList.remove('theme-dark')
    }
    currentTheme.value = theme
}

export function initTheme() {
    if (typeof window === 'undefined') return

    const saved = window.localStorage.getItem(STORAGE_KEY) as Theme | null
    if (saved === 'dark' || saved === 'light') {
        applyTheme(saved)
        return
    }

    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches
    applyTheme(prefersDark ? 'dark' : 'light')
}

export function useTheme() {
    const setTheme = (theme: Theme) => {
        window.localStorage.setItem(STORAGE_KEY, theme)
        applyTheme(theme)
    }

    const toggleTheme = () => {
        setTheme(currentTheme.value === 'dark' ? 'light' : 'dark')
    }

    return {
        theme: currentTheme,
        setTheme,
        toggleTheme,
    }
}

