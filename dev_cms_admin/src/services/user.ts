import type { useApiAdmin } from '@/hooks/useApiAdmin'
import type { UserInfo } from './auth'

export type { UserInfo } from './auth'

type ApiAdminClient = ReturnType<typeof useApiAdmin>

export const UserApi = {
  getInfo: (api: ApiAdminClient) => {
    // 使用普通 API 的 /user/info 接口
    return api.api.get<UserInfo>('/user/info')
  }
}

