import type { useApi } from '@/composables/useApi'

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

type ApiClient = ReturnType<typeof useApi>

export const AuthApi = {
  login: (api: ApiClient, data: LoginDTO) => {
    return api.post<LoginResponse>('/auth/login', data)
  },
  
  register: (api: ApiClient, data: LoginDTO) => {
    return api.post<LoginResponse>('/auth/register', data)
  },
  
  logout: (api: ApiClient) => {
    return api.post('/auth/logout')
  },
  
  checkLogin: (api: ApiClient) => {
    return api.get<CheckLoginResponse>('/auth/check-login')
  },
  
  getToken: (api: ApiClient) => {
    return api.get<TokenResponse>('/auth/token')
  }
}

