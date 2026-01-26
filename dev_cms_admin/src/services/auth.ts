import type { useApiAdmin } from '@/hooks/useApiAdmin'

export interface LoginDTO {
  username?: string
  email?: string
  password?: string
  captcha?: string
  rememberMe?: boolean
  [key: string]: any
}

export interface LoginResponse {
  token?: string
  user?: UserInfo
}

export interface CheckLoginResponse {
  logged_in?: boolean
  loggedIn?: boolean
}

export interface TokenResponse {
  token?: string
}

export interface UserInfo {
  uid: number
  name: string
  email?: string
  [key: string]: any
}

type ApiAdminClient = ReturnType<typeof useApiAdmin>

export const AuthApi = {
  login: (api: ApiAdminClient, data: LoginDTO) => {
    return api.api.post<LoginResponse>('/auth/login', data)
  },

  register: (api: ApiAdminClient, data: LoginDTO) => {
    return api.api.post<LoginResponse>('/auth/register', data)
  },

  logout: (api: ApiAdminClient) => {
    return api.api.post('/auth/logout')
  },

  checkLogin: (api: ApiAdminClient) => {
    return api.api.get<CheckLoginResponse>('/auth/check-login')
  },

  getToken: (api: ApiAdminClient) => {
    return api.api.get<TokenResponse>('/auth/token')
  }
}

