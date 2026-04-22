import * as React from 'react'
import { cn } from '@/lib/utils'

const dateLikeTypes = ['date', 'time', 'datetime-local']

const Input = React.forwardRef<HTMLInputElement, React.ComponentProps<'input'>>(
  ({ className, type, placeholder, value, ...props }, ref) => {
    const isDateLike = type && dateLikeTypes.includes(type)
    const safePlaceholder = isDateLike ? undefined : placeholder
    const safeValue = isDateLike && (value === undefined || value === null) ? '' : value
    return (
      <input
        type={type ?? 'text'}
        className={cn(
          'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
          className
        )}
        ref={ref}
        placeholder={safePlaceholder}
        value={safeValue}
        {...props}
      />
    )
  }
)
Input.displayName = 'Input'

export { Input }
