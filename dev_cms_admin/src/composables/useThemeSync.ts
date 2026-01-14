import { watch } from 'vue'
import { useColorMode } from './useColorMode'

/**
 * 主题同步 composable
 * 将 colorMode 的变化同步到 HTML 元素的 class 上
 */
export function useThemeSync() {
  const colorMode = useColorMode()

  // 同步 preference 变化
  watch(
    () => colorMode.preference.value,
    (preference) => {
      const html = document.documentElement
      if (preference === 'dark' || (preference === 'system' && html.classList.contains('dark'))) {
        html.classList.add('dark')
        html.classList.remove('light')
      } else {
        html.classList.add('light')
        html.classList.remove('dark')
      }
    },
    { immediate: true }
  )

  // 同步实际主题值变化
  watch(
    () => colorMode.value.value,
    () => {
      const html = document.documentElement
      const isDark = html.classList.contains('dark')
      if (isDark) {
        html.classList.remove('light')
      } else {
        html.classList.remove('dark')
        html.classList.add('light')
      }
    },
    { immediate: true }
  )
}

