type NotifyType = 'success' | 'error' | 'info' | 'warning'

interface NotifyOptions {
  title: string
  type?: NotifyType
}

function mapColor(type: NotifyType): string {
  switch (type) {
    case 'success':
      return 'green'
    case 'error':
      return 'red'
    case 'warning':
      return 'yellow'
    case 'info':
    default:
      return 'blue'
  }
}

/**
 * Nuxt UI toast 统一封装，便于全局复用（API、表单等）。
 * 依赖：`App.vue` 内已渲染 `<UNotifications />`
 */
export function useNotify() {
  const toast = useToast()

  const notify = (options: NotifyOptions) => {
    const type = options.type ?? 'info'
    toast.add({
      title: options.title,
      color: mapColor(type),
    })
  }

  return {
    notify,
    success: (title: string) => notify({ title, type: 'success' }),
    error: (title: string) => notify({ title, type: 'error' }),
    info: (title: string) => notify({ title, type: 'info' }),
    warning: (title: string) => notify({ title, type: 'warning' }),
  }
}


