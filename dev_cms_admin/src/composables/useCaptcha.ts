import { ref } from 'vue'
import { useApi } from './useApi'

interface ConfigResponse {
  captcha: boolean
}

interface CaptchaResponse {
  image: string
}

export const useCaptcha = () => {
  const { get } = useApi()
  const image = ref('')
  const enabled = ref(false)

  const check = async () => {
    try {
      const res = await get<ConfigResponse>('/get-config')
      enabled.value = res.data?.captcha ?? false
      if (enabled.value) {
        await refresh()
      }
    } catch {
      enabled.value = false
    }
  }

  const refresh = async () => {
    try {
      const res = await get<CaptchaResponse>('/auth/captcha')
      if (res.data?.image) {
        image.value = res.data.image
      }
    } catch {
      // 静默失败
    }
  }

  return { image, enabled, check, refresh }
}
