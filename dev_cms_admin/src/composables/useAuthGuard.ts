import { ref, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from './useAuth'

interface AuthGuardOptions {
  authenticatedRoute?: string
  unauthenticatedRoute?: string
}

/**
 * 认证守卫
 * 检查登录状态并自动跳转到相应路由
 * @returns mounted
 */
export function useAuthGuard(options: AuthGuardOptions = {}) {
  const router = useRouter()
  const auth = useAuth() as any
  const mounted = ref(false)

  const {
    authenticatedRoute = '/console',
    unauthenticatedRoute = '/login',
  } = options

  onMounted(async () => {
    await auth.checkLogin()
    // 等待响应式更新完成
    await nextTick()
    mounted.value = true

    // 确保在 nextTick 后再检查认证状态
    await nextTick()
    if (auth.isAuthenticated) {
      router.push(authenticatedRoute)
    } else {
      router.push(unauthenticatedRoute)
    }
  })

  return { mounted }
}

