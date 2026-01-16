import { createRouter, createWebHashHistory } from 'vue-router'
import routes from 'virtual:generated-pages'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

// 不需要认证的路由
const publicRoutes = ['/login', '/']

// 自动检测需要认证的路由前缀
function getAuthRequiredRoutes(): string[] {
  const authRoutePrefixes = new Set<string>()

  function traverseRoutes(routeList: typeof routes, basePath = '') {
    for (const route of routeList) {
      const fullPath = basePath + (route.path === '/' ? '' : route.path)

      // 排除公共路由
      if (!publicRoutes.includes(fullPath)) {
        // 如果路由有 meta.requiresAuth === false，则跳过
        if (route.meta?.requiresAuth !== false) {
          // 提取路径前缀
          const pathPrefix = fullPath.split('/')[1]
          if (pathPrefix) {
            authRoutePrefixes.add(`/${pathPrefix}`)
          }
        }
      }

      // 递归处理子路由
      if (route.children) {
        traverseRoutes(route.children, fullPath)
      }
    }
  }

  traverseRoutes(routes)
  return Array.from(authRoutePrefixes)
}

const authRequiredRoutes = getAuthRequiredRoutes()

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (!authStore.initialized) {
    authStore.initialize()
  }

  const requiresAuth = authRequiredRoutes.some(route => to.path.startsWith(route))

  if (to.path === '/login' || to.path === '/') {
    if (authStore.isAuthenticated || authStore.hasToken) {
      next('/console')
      return
    }
    next()
    return
  }

  if (requiresAuth) {
    if (authStore.hasToken || authStore.isAuthenticated) {
      if (authStore.isAuthenticated && !authStore.checking) {
        next()
        return
      }

      authStore.checkLogin().then((isValid) => {
        if (!isValid && authRequiredRoutes.some(route => to.path.startsWith(route))) {
          router.push('/login')
        }
      }).catch(() => {
        if (authRequiredRoutes.some(route => to.path.startsWith(route))) {
          router.push('/login')
        }
      })
      next()
      return
    }

    next('/login')
    return
  }

  next()
})

export default router
