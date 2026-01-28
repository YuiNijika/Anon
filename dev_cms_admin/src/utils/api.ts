/**
 * 获取 API Base URL
 * 开发环境：使用 /anon-dev-server（通过 Vite 代理）
 * 生产环境：使用环境变量 VITE_API_BASE_URL，如果未配置或为空则使用 /（根路径）
 * 如果 baseUrl 缺少协议，自动根据当前页面协议添加
 */
export function getApiBaseUrl(): string {
  if (import.meta.env.DEV) {
    return '/anon-dev-server'
  }
  // 生产环境：如果配置了 VITE_API_BASE_URL 且不为空，使用它；否则使用 /（根路径）
  let baseUrl = import.meta.env.VITE_API_BASE_URL
  if (!baseUrl || baseUrl.trim() === '') {
    return '/'
  }

  baseUrl = baseUrl.trim()

  // 如果已经是相对路径（以 / 开头），直接返回
  if (baseUrl.startsWith('/')) {
    return baseUrl
  }

  // 如果已经包含协议（http:// 或 https://），直接返回
  if (/^https?:\/\//i.test(baseUrl)) {
    return baseUrl
  }

  // 如果缺少协议，自动根据当前页面协议添加
  // 优先使用 https，如果当前页面是 http 则使用 http
  const protocol = typeof window !== 'undefined' && window.location.protocol === 'https:' ? 'https://' : 'http://'
  return `${protocol}${baseUrl}`
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

