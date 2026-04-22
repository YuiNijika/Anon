import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * 从异常中提取可展示的错误信息，优先使用后端返回的 message
 * @param err 捕获的异常（API 返回非 200 时 useApiAdmin 会 throw new Error(data.message)）
 * @param defaultMessage 无有效 message 时的默认文案
 */
export function getErrorMessage(err: unknown, defaultMessage: string): string {
  if (err instanceof Error && err.message) return err.message
  if (
    typeof err === 'object' &&
    err !== null &&
    'message' in err &&
    typeof (err as { message?: unknown }).message === 'string'
  ) {
    return (err as { message: string }).message
  }
  return defaultMessage
}
