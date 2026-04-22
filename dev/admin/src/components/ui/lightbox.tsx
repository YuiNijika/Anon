import * as React from 'react'
import { X, ChevronLeft, ChevronRight } from 'lucide-react'
import {
  Dialog,
  DialogContent,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

interface LightboxProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  src: string
  alt?: string
  /** 多图时传入 */
  sources?: string[]
  currentIndex?: number
  onIndexChange?: (index: number) => void
}

export function Lightbox({
  open,
  onOpenChange,
  src,
  alt = '',
  sources = [],
  currentIndex = 0,
  onIndexChange,
}: LightboxProps) {
  const hasMultiple = sources.length > 1
  const canPrev = hasMultiple && currentIndex > 0
  const canNext = hasMultiple && currentIndex < sources.length - 1
  const displaySrc = hasMultiple ? sources[currentIndex] ?? src : src

  const handlePrev = (e: React.MouseEvent) => {
    e.stopPropagation()
    if (canPrev && onIndexChange) onIndexChange(currentIndex - 1)
  }

  const handleNext = (e: React.MouseEvent) => {
    e.stopPropagation()
    if (canNext && onIndexChange) onIndexChange(currentIndex + 1)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-w-[95vw] max-h-[95vh] w-auto border-0 bg-black/95 p-0 overflow-hidden [&>button]:hidden"
        onPointerDownOutside={(e) => e.target === e.currentTarget && onOpenChange(false)}
      >
        <div className="relative flex h-[85vh] w-full items-center justify-center p-4">
          <Button
            variant="ghost"
            size="icon"
            className="absolute right-2 top-2 z-10 h-10 w-10 rounded-full bg-black/50 text-white hover:bg-black/70 hover:text-white"
            onClick={() => onOpenChange(false)}
          >
            <X className="h-5 w-5" />
          </Button>
          {hasMultiple && canPrev && (
            <Button
              variant="ghost"
              size="icon"
              className="absolute left-2 top-1/2 z-10 h-12 w-12 -translate-y-1/2 rounded-full bg-black/50 text-white hover:bg-black/70 hover:text-white"
              onClick={handlePrev}
            >
              <ChevronLeft className="h-8 w-8" />
            </Button>
          )}
          <img
            src={displaySrc}
            alt={alt}
            className={cn('max-h-full max-w-full object-contain')}
            onClick={(e) => e.stopPropagation()}
          />
          {hasMultiple && canNext && (
            <Button
              variant="ghost"
              size="icon"
              className="absolute right-2 top-1/2 z-10 h-12 w-12 -translate-y-1/2 rounded-full bg-black/50 text-white hover:bg-black/70 hover:text-white"
              onClick={handleNext}
            >
              <ChevronRight className="h-8 w-8" />
            </Button>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
