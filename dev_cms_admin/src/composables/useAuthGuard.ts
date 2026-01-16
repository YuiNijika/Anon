import { ref, onMounted } from 'vue'
import { useAuth } from './useAuth'

interface AuthGuardOptions {
  authenticatedRoute?: string
  unauthenticatedRoute?: string
}

/**
 * 认证守卫
 * 初始化认证状态，不再阻塞页面渲染
 */
export function useAuthGuard(options: AuthGuardOptions = {}) {
  const auth = useAuth() as any
  const mounted = ref(false)

  onMounted(() => {
    if (!auth.initialized) {
      auth.initialize()
    }

    mounted.value = true

    if (auth.hasToken || auth.isAuthenticated) {
      auth.checkLogin().catch(() => {
        // 静默处理
      })
    }
  })

  return { mounted }
}

