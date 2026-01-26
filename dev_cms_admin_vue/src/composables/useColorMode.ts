import { ref, computed, watch, onMounted } from 'vue'

type ColorMode = 'light' | 'dark' | 'system'

export function useColorMode() {
  const preference = ref<ColorMode>('dark')
  const value = computed<ColorMode>(() => {
    if (preference.value === 'system') {
      // 检测系统主题
      if (typeof window !== 'undefined') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
      }
      return 'light'
    }
    return preference.value
  })

  // 从 localStorage 读取用户偏好
  onMounted(() => {
    if (typeof window !== 'undefined') {
      const saved = localStorage.getItem('color-mode') as ColorMode | null
      if (saved && ['light', 'dark', 'system'].includes(saved)) {
        preference.value = saved
      }
    }
  })

  // 监听系统主题变化
  if (typeof window !== 'undefined') {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    const handleChange = () => {
      if (preference.value === 'system') {
        // 触发更新
        const html = document.documentElement
        updateHtmlClass(html)
      }
    }
    mediaQuery.addEventListener('change', handleChange)
  }

  // 更新 HTML 类
  const updateHtmlClass = (html: HTMLElement) => {
    const isDark = value.value === 'dark'
    if (isDark) {
      html.classList.add('dark')
      html.classList.remove('light')
    } else {
      html.classList.add('light')
      html.classList.remove('dark')
    }
  }

  // 监听 preference 变化，保存到 localStorage
  watch(preference, (newPreference) => {
    if (typeof window !== 'undefined') {
      localStorage.setItem('color-mode', newPreference)
      updateHtmlClass(document.documentElement)
    }
  }, { immediate: true })

  // 监听 value 变化，更新 HTML 类
  watch(value, () => {
    if (typeof window !== 'undefined') {
      updateHtmlClass(document.documentElement)
    }
  }, { immediate: true })

  return {
    preference,
    value,
  }
}

