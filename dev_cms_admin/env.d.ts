/// <reference types="vite/client" />

declare function useColorMode(): {
  value: 'light' | 'dark' | 'system'
  preference: 'light' | 'dark' | 'system'
}

declare module 'virtual:generated-pages' {
  import type { RouteRecordRaw } from 'vue-router'
  const routes: readonly RouteRecordRaw[]
  export default routes
}
