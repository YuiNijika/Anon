import type { useApiAdmin } from '@/hooks/useApiAdmin'

type ApiClient = ReturnType<typeof useApiAdmin>

/** Alert 上的操作按钮，可触发对话框（仿 CSF） */
export interface ThemeOptionAlertAction {
  label: string
  dialog?: boolean
  dialogTitle?: string
  dialogMessage?: string
}

/** 树节点，用于 tree_select */
export interface ThemeOptionTreeNode {
  value: string
  label: string
  children?: ThemeOptionTreeNode[]
}

/** 描述列表项，用于 description_list */
export interface ThemeOptionDescItem {
  label: string
  value: string
}

/** 表格列，用于 table */
export interface ThemeOptionTableColumn {
  key: string
  title: string
}

export interface ThemeOptionSchema {
  type:
  | 'text'
  | 'boolean'
  | 'textarea'
  | 'select'
  | 'checkbox'
  | 'number'
  | 'color'
  | 'date'
  | 'time'
  | 'datetime'
  | 'radio'
  | 'button_group'
  | 'slider'
  | 'badge'
  | 'divider'
  | 'alert'
  | 'notice'
  | 'alert_dialog'
  | 'content'
  | 'heading'
  | 'accordion'
  | 'result'
  | 'card'
  | 'tree_select'
  | 'transfer'
  | 'upload'
  | 'description_list'
  | 'virtual_select'
  | 'table'
  | 'tooltip'
  | 'tag'
  | 'autocomplete'
  | 'text_list'
  label: string
  description?: string
  default?: unknown
  options?: Record<string, string>
  /** slider: min, max, step */
  min?: number
  max?: number
  step?: number
  /** badge/alert/result 等展示型 */
  variant?: string
  text?: string
  message?: string
  title?: string
  status?: 'empty' | 'success' | 'info' | 'error'
  /** alert 上的操作按钮，可嵌套 AlertDialog */
  actions?: ThemeOptionAlertAction[]
  /** notice 可关闭 */
  dismissible?: boolean
  /** alert_dialog: 按钮文案、对话框标题/描述/确认按钮文案 */
  buttonText?: string
  dialogTitle?: string
  dialogDescription?: string
  dialogConfirmText?: string
  /** heading 级别 2|3|4 */
  level?: 2 | 3 | 4
  /** tree_select: 树形数据 */
  treeData?: ThemeOptionTreeNode[]
  /** transfer: 左侧选项列表，value 为选中 key 数组 */
  transferOptions?: Record<string, string>
  /** upload: 接受类型如 image/*，是否多选 */
  uploadAccept?: string
  uploadMultiple?: boolean
  /** description_list: 描述项列表 */
  descItems?: ThemeOptionDescItem[]
  /** table: 列定义与行数据 */
  tableColumns?: ThemeOptionTableColumn[]
  tableRows?: Record<string, unknown>[]
  /** tooltip: 悬停提示内容 */
  tooltipContent?: string
  /** tag: 标签文案数组，或单个 text */
  tags?: string[]
  /** text_list: 每行输入框的 placeholder */
  listPlaceholder?: string
  sanitize_callback?: (value: unknown) => unknown
  validate_callback?: (value: unknown) => boolean
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

export interface Comment {
  id: number
  post_id: number
  post_title?: string
  parent_id: number | null
  reply_to_name?: string | null
  uid: number | null
  type: 'user' | 'guest'
  name: string | null
  email: string | null
  avatar?: string | null
  url: string | null
  ip: string
  user_agent?: string | null
  ua_browser?: string
  ua_os?: string
  is_reply?: boolean
  content: string
  status: 'pending' | 'approved' | 'spam' | 'trash'
  created_at: number
}

export interface CommentListParams {
  page?: number
  page_size?: number
  status?: string
  post_id?: number
  type?: 'user' | 'guest'
  keyword?: string
  is_reply?: 0 | 1 | 2
  date_from?: string
  date_to?: string
}

export interface CommentListResponse {
  list: Comment[]
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
  original_name?: string
  filename?: string
  file_size?: number
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

  // 评论管理
  getComments: (api: ApiClient, params?: CommentListParams) => {
    return api.admin.get<CommentListResponse>('/comments', params)
  },

  updateCommentStatus: (api: ApiClient, id: number, status: 'approved' | 'pending' | 'spam' | 'trash') => {
    return api.admin.put<unknown>('/comments', { id, status })
  },
  updateComment: (api: ApiClient, id: number, payload: { content?: string; status?: 'approved' | 'pending' | 'spam' | 'trash' }) => {
    return api.admin.put<unknown>('/comments', { id, ...payload })
  },
  deleteComment: (api: ApiClient, id: number) => {
    return api.admin.delete<{ id: number }>('/comments', { id })
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

  uploadPlugin: (api: ApiClient, file: File, overwrite?: boolean) => {
    const formData = new FormData()
    formData.append('file', file)
    if (overwrite) formData.append('overwrite', '1')
    return api.admin.post<{ slug: string; needConfirm?: boolean; name?: string; existingVersion?: string; newVersion?: string; upgrade?: boolean }>('/plugins', formData)
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

  /** 获取插件自定义页面配置 */
  getPluginPage: (api: ApiClient, params: { slug: string; page: string }) => {
    return api.admin.get<any>('/plugins/page', params)
  },

  /** 插件自定义页面操作 - 执行 Action */
  pluginPageAction: (api: ApiClient, data: { slug: string; page: string; action: string; data?: any }) => {
    return api.admin.post<any>('/plugins/page/action', data)
  },

  // 主题管理
  uploadTheme: (api: ApiClient, file: File, overwrite?: boolean) => {
    const formData = new FormData()
    formData.append('file', file)
    if (overwrite) formData.append('overwrite', '1')
    return api.admin.post<{ name: string; needConfirm?: boolean; existingVersion?: string; newVersion?: string; upgrade?: boolean }>('/themes', formData)
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
  pages?: { slug: string; title: string }[]
}

export interface PluginListResponse {
  list: Plugin[]
}

export interface PageComponent {
  id?: string
  type: string
  props?: Record<string, any>
  children?: PageComponent[]
  // 组件行为绑定，例如 onClick: { action: 'submit', target: '/api/...' }
  events?: Record<string, any>
}

/** 插件设置 schema 单字段定义，与主题 ThemeOptionSchema 一致 */
export type PluginOptionSchema = ThemeOptionSchema

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

