import type { useApiAdmin } from '@/hooks/useApiAdmin'

type ApiClient = ReturnType<typeof useApiAdmin>

export interface ThemeOptionSchema {
  type: 'text' | 'textarea' | 'select' | 'checkbox' | 'number' | 'color'
  label: string
  description?: string
  default?: any
  options?: Record<string, string>
  sanitize_callback?: (value: any) => any
  validate_callback?: (value: any) => boolean
}

/** 树形 schema：分组名 -> 选项名 -> 选项定义 */
export type ThemeOptionsSchemaTree = Record<string, Record<string, ThemeOptionSchema>>

export interface ThemeOptionsData {
  theme: string
  schema: ThemeOptionsSchemaTree
  values: Record<string, any>
}

export interface ThemeInfo {
  name: string
  displayName: string
  description: string
  author: string
  version: string
  url: string
  screenshot: string
}

export interface ThemeSettings {
  current: string
  themes: ThemeInfo[]
}

export interface NavbarItem {
  key: string
  label: string
  icon?: string
  children?: NavbarItem[]
}

export interface TrendData {
  date: string
  count: number
}

export interface DistributionData {
  type: string
  value: number
}

export interface StatisticsData {
  posts: number
  comments: number
  attachments: number
  categories: number
  tags: number
  users: number
  published_posts: number
  draft_posts: number
  pending_comments: number
  approved_comments: number
  attachments_size: number
  total_views: number
  views_trend?: TrendData[]
  posts_trend?: TrendData[]
  posts_status_distribution?: DistributionData[]
  comments_status_distribution?: DistributionData[]
}

export interface BasicSettings {
  title: string
  subtitle: string
  description: string
  keywords: string
  upload_allowed_types: {
    image: string
    media: string
    document: string
    other: string
  }
  routes?: Record<string, string>
}

export interface PageSettings {
  routes: Record<string, string>
}

export interface PermissionSettings {
  allow_register: boolean
  access_log_enabled: boolean
  api_prefix: string
  api_enabled: boolean
}

export interface Post {
  id: number
  title: string
  slug: string
  content: string
  type: 'post' | 'page'
  status: 'publish' | 'draft' | 'private'
  created_at: number
  updated_at: number
  author_id: number
  category_id?: number
  views?: number
}

export interface PostListResponse {
  list: Post[]
  total: number
  page: number
  page_size: number
}

export interface Category {
  id: number
  name: string
  slug: string
  description?: string
  parent_id?: number
  count?: number
}

export interface Tag {
  id: number
  name: string
  slug: string
  count?: number
}

export interface User {
  uid: number
  name: string
  email: string
  display_name?: string
  group: 'admin' | 'editor' | 'user'
  avatar?: string
  created_at: number
}

export interface UserListResponse {
  list: User[]
  total: number
  page: number
  page_size: number
}

export interface Attachment {
  id: number
  name: string
  url: string
  type: string
  size: number
  mime_type: string
  created_at: number
}

export interface AttachmentListResponse {
  list: Attachment[]
}

export const AdminApi = {
  /** 获取主题设置项，schema 来自主题 setup 文件，values 来自数据库 */
  getThemeOptions: (api: ApiClient, params?: { theme?: string }) => {
    return api.admin.get<ThemeOptionsData>('/settings/theme-options', params)
  },

  /** 保存主题设置项，需传 theme 与 values */
  updateThemeOptions: (api: ApiClient, data: { theme: string; values: Record<string, any> }) => {
    return api.admin.post<ThemeOptionsData>('/settings/theme-options', data)
  },

  getBasicSettings: (api: ApiClient) => {
    return api.admin.get<BasicSettings>('/settings/basic')
  },

  updateBasicSettings: (api: ApiClient, data: BasicSettings) => {
    return api.admin.post<BasicSettings>('/settings/basic', data)
  },

  getPermissionSettings: (api: ApiClient) => {
    return api.admin.get<PermissionSettings>('/settings/permission')
  },

  updatePermissionSettings: (api: ApiClient, data: PermissionSettings) => {
    return api.admin.post<PermissionSettings>('/settings/permission', data)
  },

  getPageSettings: (api: ApiClient) => {
    return api.admin.get<PageSettings>('/settings/page')
  },

  updatePageSettings: (api: ApiClient, data: PageSettings) => {
    return api.admin.post<PageSettings>('/settings/page', data)
  },

  getStatistics: (api: ApiClient) => {
    return api.admin.get<StatisticsData>('/statistics')
  },

  getViewsTrend: (api: ApiClient, days: number) => {
    return api.admin.get<Array<{ date: string; count: number }>>('/statistics/views-trend', { days })
  },

  getAccessLogs: (
    api: ApiClient,
    params?: {
      page?: number
      page_size?: number
      ip?: string
      path?: string
      type?: string
      user_agent?: string
      status_code?: number
      start_date?: string
      end_date?: string
    }
  ) => {
    return api.admin.get<AccessLogListResponse>('/statistics/access-logs', params)
  },

  getAccessStats: (
    api: ApiClient,
    params?: {
      start_date?: string
      end_date?: string
      ip?: string
      path?: string
      type?: string
    }
  ) => {
    return api.admin.get<AccessStats>('/statistics/access-stats', params)
  },

  getNavbar: (api: ApiClient) => {
    return api.admin.get<{ header: NavbarItem[]; sidebar: NavbarItem[] }>('/navbar')
  },

  getThemeSettings: (api: ApiClient) => {
    return api.admin.get<ThemeSettings>('/settings/theme')
  },

  updateThemeSettings: (api: ApiClient, theme: string) => {
    return api.admin.post<{ theme: string }>('/settings/theme', { theme })
  },

  // 文章管理
  getPosts: (api: ApiClient, params?: { page?: number; page_size?: number; type?: string; status?: string; search?: string }) => {
    return api.admin.get<PostListResponse>('/posts', params)
  },

  getPost: (api: ApiClient, id: number) => {
    return api.admin.get<Post>(`/posts/${id}`)
  },

  createPost: (api: ApiClient, data: Partial<Post>) => {
    return api.admin.post<Post>('/posts', data)
  },

  updatePost: (api: ApiClient, data: Partial<Post> & { id: number }) => {
    return api.admin.put<Post>('/posts', data)
  },

  deletePost: (api: ApiClient, id: number) => {
    return api.admin.delete<{ id: number }>('/posts', { id })
  },

  // 分类管理
  getCategories: (api: ApiClient) => {
    return api.admin.get<Category[]>('/metas/categories')
  },

  createCategory: (api: ApiClient, data: Partial<Category>) => {
    return api.admin.post<Category>('/metas/categories', data)
  },

  updateCategory: (api: ApiClient, data: Partial<Category> & { id: number }) => {
    return api.admin.put<Category>('/metas/categories', data)
  },

  deleteCategory: (api: ApiClient, id: number) => {
    return api.admin.delete<{ id: number }>('/metas/categories', { id })
  },

  // 标签管理
  getTags: (api: ApiClient) => {
    return api.admin.get<Tag[]>('/metas/tags')
  },

  createTag: (api: ApiClient, data: Partial<Tag>) => {
    return api.admin.post<Tag>('/metas/tags', data)
  },

  updateTag: (api: ApiClient, data: Partial<Tag> & { id: number }) => {
    return api.admin.put<Tag>('/metas/tags', data)
  },

  deleteTag: (api: ApiClient, id: number) => {
    return api.admin.delete<{ id: number }>('/metas/tags', { id })
  },

  // 用户管理
  getUsers: (api: ApiClient, params?: { page?: number; page_size?: number }) => {
    return api.admin.get<UserListResponse>('/users', params)
  },

  createUser: (api: ApiClient, data: Partial<User> & { password: string }) => {
    return api.admin.post<User>('/users', data)
  },

  updateUser: (api: ApiClient, data: Partial<User> & { uid: number }) => {
    return api.admin.put<User>('/users', data)
  },

  deleteUser: (api: ApiClient, uid: number) => {
    return api.admin.delete<{ uid: number }>('/users', { uid })
  },

  // 附件管理
  getAttachments: (api: ApiClient, params?: { sort?: 'new' | 'old' }) => {
    return api.admin.get<AttachmentListResponse>('/attachments', params)
  },

  uploadAttachment: (api: ApiClient, file: File) => {
    const formData = new FormData()
    formData.append('file', file)
    return api.admin.post<Attachment>('/attachments/upload', formData)
  },

  deleteAttachment: (api: ApiClient, id: number) => {
    return api.admin.delete<{ id: number }>('/attachments', { id })
  },

  // 插件管理
  getPlugins: (api: ApiClient) => {
    return api.admin.get<PluginListResponse>('/plugins')
  },

  uploadPlugin: (api: ApiClient, file: File) => {
    const formData = new FormData()
    formData.append('file', file)
    return api.admin.post<{ slug: string }>('/plugins', formData)
  },

  activatePlugin: (api: ApiClient, slug: string) => {
    return api.admin.put<{ slug: string }>('/plugins', { slug, action: 'activate' })
  },

  deactivatePlugin: (api: ApiClient, slug: string) => {
    return api.admin.put<{ slug: string }>('/plugins', { slug, action: 'deactivate' })
  },

  deletePlugin: (api: ApiClient, slug: string) => {
    return api.admin.delete<{ slug: string }>('/plugins', { slug })
  },

  /** 获取插件设置项，schema 来自插件 getSettingsSchema，values 来自 options 表 plugin:slug */
  getPluginOptions: (api: ApiClient, params: { slug: string }) => {
    return api.admin.get<PluginOptionsData>('/plugins/options', params)
  },

  /** 保存插件设置项 */
  updatePluginOptions: (api: ApiClient, data: { slug: string; values: Record<string, any> }) => {
    return api.admin.post<PluginOptionsData>('/plugins/options', data)
  },

  // 主题管理
  uploadTheme: (api: ApiClient, file: File) => {
    const formData = new FormData()
    formData.append('file', file)
    return api.admin.post<{ name: string }>('/themes', formData)
  },

  deleteTheme: (api: ApiClient, name: string) => {
    return api.admin.delete<{ name: string }>('/themes', { name })
  },
}

export interface Plugin {
  slug: string
  dir: string
  name: string
  description: string
  version: string
  author: string
  url: string
  mode: 'api' | 'cms' | 'auto'
  active: boolean
}

export interface PluginListResponse {
  list: Plugin[]
}

/** 插件设置 schema 单字段定义，与主题一致 */
export interface PluginOptionSchema {
  type: 'text' | 'textarea' | 'select' | 'checkbox' | 'number' | 'color'
  label: string
  description?: string
  default?: any
  options?: Record<string, string>
}

export interface PluginOptionsData {
  slug: string
  schema: Record<string, PluginOptionSchema>
  values: Record<string, any>
}

export interface AccessLog {
  id: number
  url: string
  path: string
  method: string
  type: string
  ip: string
  user_agent: string | null
  referer: string | null
  status_code: number
  response_time: number | null
  created_at: string
}

export interface AccessLogListResponse {
  list: AccessLog[]
  total: number
  page: number
  page_size: number
}

export interface AccessStats {
  total: number
  unique_ips: number
  top_pages: Array<{
    path: string
    count: number
  }>
}

