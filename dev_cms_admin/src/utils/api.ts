/**
 * 获取 API Base URL
 * 开发环境：使用 /anon-dev-server（通过 Vite 代理）
 * 生产环境：使用环境变量 VITE_API_BASE_URL，如果未配置则使用空字符串（相对路径）
 */
export function getApiBaseUrl(): string {
  if (import.meta.env.DEV) {
    return '/anon-dev-server'
  }
  // 生产环境：如果配置了 VITE_API_BASE_URL，使用它；否则使用空字符串（相对路径）
  return import.meta.env.VITE_API_BASE_URL || ''
}

