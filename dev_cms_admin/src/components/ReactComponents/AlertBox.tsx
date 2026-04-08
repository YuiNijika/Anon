import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { AlertCircle, CheckCircle2, AlertTriangle, XCircle, X } from 'lucide-react'
import { useState } from 'react'

interface AlertBoxProps {
  type?: 'info' | 'success' | 'warning' | 'error'
  title?: string
  closable?: boolean
  children?: React.ReactNode
}

const alertConfig = {
  info: {
    icon: AlertCircle,
    className: 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950',
    titleClass: 'text-blue-900 dark:text-blue-100',
    descClass: 'text-blue-700 dark:text-blue-300',
  },
  success: {
    icon: CheckCircle2,
    className: 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950',
    titleClass: 'text-green-900 dark:text-green-100',
    descClass: 'text-green-700 dark:text-green-300',
  },
  warning: {
    icon: AlertTriangle,
    className: 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950',
    titleClass: 'text-yellow-900 dark:text-yellow-100',
    descClass: 'text-yellow-700 dark:text-yellow-300',
  },
  error: {
    icon: XCircle,
    className: 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950',
    titleClass: 'text-red-900 dark:text-red-100',
    descClass: 'text-red-700 dark:text-red-300',
  },
}

export function AlertBox({ 
  type = 'info', 
  title = '',
  closable = true,
  children
}: AlertBoxProps) {
  const [visible, setVisible] = useState(true)
  const config = alertConfig[type]
  const Icon = config.icon

  if (!visible) return null

  return (
    <Alert className={`relative ${config.className}`}>
      {closable && (
        <Button
          variant="ghost"
          size="sm"
          className="absolute right-2 top-2 h-6 w-6 p-0 hover:bg-black/10"
          onClick={() => setVisible(false)}
        >
          <X className="h-4 w-4" />
        </Button>
      )}
      
      <Icon className="h-4 w-4" />
      {title && <AlertTitle className={config.titleClass}>{title}</AlertTitle>}
      <AlertDescription className={config.descClass}>
        {children || `这是一条${type === 'info' ? '信息' : type === 'success' ? '成功' : type === 'warning' ? '警告' : '错误'}提示`}
      </AlertDescription>
    </Alert>
  )
}
