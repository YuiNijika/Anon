import * as React from 'react'
import { cn } from '@/lib/utils'
import { FileQuestion, Inbox, CheckCircle, Info } from 'lucide-react'

const icons = {
  empty: Inbox,
  success: CheckCircle,
  info: Info,
  error: FileQuestion,
}

export interface ResultProps extends React.HTMLAttributes<HTMLDivElement> {
  status?: 'empty' | 'success' | 'info' | 'error'
  icon?: React.ReactNode
  title?: string
  description?: string
  extra?: React.ReactNode
}

const isElementType = (x: unknown): x is React.ElementType =>
  typeof x === 'function' || (typeof x === 'object' && x !== null && '$$typeof' in x)

const Result = React.forwardRef<HTMLDivElement, ResultProps>(
  ({ status = 'empty', icon, title, description, extra, className, children, ...props }, ref) => {
    const Icon = icon ?? icons[status]
    return (
      <div
        ref={ref}
        className={cn(
          'flex flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center',
          className
        )}
        {...props}
      >
        {isElementType(Icon) ? (
          <Icon className="mb-3 h-12 w-12 text-muted-foreground" />
        ) : (
          <>{Icon as React.ReactNode}</>
        )}
        {title && <p className="font-medium">{title}</p>}
        {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
        {extra && <div className="mt-4">{extra}</div>}
        {children}
      </div>
    )
  }
)
Result.displayName = 'Result'

export { Result }
