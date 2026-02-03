import * as React from 'react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

export interface ToggleGroupOption {
  value: string
  label: string
}

export interface ToggleGroupProps {
  value?: string
  onValueChange?: (value: string) => void
  options: ToggleGroupOption[] | Record<string, string>
  className?: string
  size?: 'sm' | 'default' | 'lg'
}

const opts = (o: ToggleGroupOption[] | Record<string, string>): ToggleGroupOption[] =>
  Array.isArray(o) ? o : Object.entries(o).map(([value, label]) => ({ value, label }))

const ToggleGroup = React.forwardRef<HTMLDivElement, ToggleGroupProps>(
  ({ value, onValueChange, options, className, size = 'sm' }, ref) => {
    const list = opts(options)
    return (
      <div ref={ref} className={cn('flex flex-wrap gap-1', className)} role="group">
        {list.map(({ value: v, label }) => (
          <Button
            key={v}
            type="button"
            variant={value === v ? 'default' : 'outline'}
            size={size}
            onClick={() => onValueChange?.(v)}
          >
            {label}
          </Button>
        ))}
      </div>
    )
  }
)
ToggleGroup.displayName = 'ToggleGroup'

export { ToggleGroup }
