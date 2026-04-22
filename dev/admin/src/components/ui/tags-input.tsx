import * as React from 'react'
import { X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

export interface TagsInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange'> {
  value?: string[]
  onChange?: (value: string[]) => void
  placeholder?: string
}

const TagsInput = React.forwardRef<HTMLInputElement, TagsInputProps>(
  ({ className, value = [], onChange, placeholder = '输入后按回车添加', ...props }, ref) => {
    const [inputValue, setInputValue] = React.useState('')
    const inputRef = React.useRef<HTMLInputElement>(null)

    // 合并 ref
    React.useImperativeHandle(ref, () => inputRef.current as HTMLInputElement)

    const addTag = (name: string) => {
      const n = name.trim()
      if (n && !value.includes(n)) {
        onChange?.([...value, n])
      }
      setInputValue('')
    }

    const removeTag = (name: string) => {
      onChange?.(value.filter((t) => t !== name))
    }

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault()
        addTag(inputValue)
      }
      props.onKeyDown?.(e)
    }

    return (
      <div
        className={cn(
          'flex min-h-10 w-full flex-wrap items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
          className
        )}
      >
        {value.map((tag) => (
          <Badge key={tag} variant="secondary" className="gap-0.5 pr-1">
            {tag}
            <button
              type="button"
              className="ml-0.5 rounded-full p-0.5 hover:bg-muted"
              onClick={() => removeTag(tag)}
              aria-label="移除"
            >
              <X className="h-3 w-3" />
            </button>
          </Badge>
        ))}
        <input
          ref={inputRef}
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder={value.length === 0 ? placeholder : ''}
          className="flex-1 min-w-[120px] border-0 bg-transparent p-0 outline-none placeholder:text-muted-foreground"
          {...props}
        />
      </div>
    )
  }
)

TagsInput.displayName = 'TagsInput'

export { TagsInput }
