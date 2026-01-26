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

export interface ThemeOptionsData {
  schema: Record<string, ThemeOptionSchema>
  values: Record<string, any>
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
}

export interface BasicSettings {
  title: string
  description: string
  keywords: string
  allow_register: boolean
  api_prefix: string
  api_enabled: boolean
  access_log_enabled: boolean
  upload_allowed_types: {
    image: string
    media: string
    document: string
    other: string
  }
}

export const AdminApi = {
  getThemeOptions: (api: ApiClient) => {
    return api.admin.get<ThemeOptionsData>('/settings/theme-options')
  },

  updateThemeOptions: (api: ApiClient, data: Record<string, any>) => {
    return api.admin.post<ThemeOptionsData>('/settings/theme-options', data)
  },

  getBasicSettings: (api: ApiClient) => {
    return api.admin.get<BasicSettings>('/settings/basic')
  },

  updateBasicSettings: (api: ApiClient, data: BasicSettings) => {
    return api.admin.post<BasicSettings>('/settings/basic', data)
  },

  getStatistics: (api: ApiClient) => {
    return api.admin.get<StatisticsData>('/statistics')
  },
}

