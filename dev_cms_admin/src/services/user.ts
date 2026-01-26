import type { useApiAdmin } from '@/hooks/useApiAdmin'
import type { UserInfo } from './auth'

export type { UserInfo } from './auth'

type ApiAdminClient = ReturnType<typeof useApiAdmin>

export const UserApi = {
  getInfo: (api: ApiAdminClient) => {
    return api.admin.get<UserInfo>('/user/info')
  }
}

