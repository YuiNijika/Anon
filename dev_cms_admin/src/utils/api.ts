/**
 * 获取 API Base URL
 * 开发环境：使用 /anon-dev-server（通过 Vite 代理）
 * 生产环境：使用环境变量 VITE_API_BASE_URL，如果未配置或为空则使用 /（根路径）
 */
export function getApiBaseUrl(): string {
  if (import.meta.env.DEV) {
    return '/anon-dev-server'
  }
  // 生产环境：如果配置了 VITE_API_BASE_URL 且不为空，使用它；否则使用 /（根路径）
  const baseUrl = import.meta.env.VITE_API_BASE_URL
  return baseUrl && baseUrl.trim() !== '' ? baseUrl : '/'
}

/**
 * 将后端返回的路径（如 /anon/static/...）转换为可访问的完整 URL
 * - 已是 http(s) 绝对地址：原样返回
 * - 以 / 开头的相对路径：自动拼接 getApiBaseUrl()
 * - 其他情况：原样返回
 */
export function buildPublicUrl(url: string): string {
  if (!url) return url
  if (/^https?:\/\//i.test(url)) return url
  if (url.startsWith('/')) {
    return `${getApiBaseUrl()}${url}`
  }
  return url
}

