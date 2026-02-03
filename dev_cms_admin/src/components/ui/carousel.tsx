import * as React from 'react'
import { cn } from '@/lib/utils'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/button'

export interface CarouselProps extends React.HTMLAttributes<HTMLDivElement> {
  children: React.ReactNode
  /** 是否显示左右箭头 */
  arrows?: boolean
  /** 是否显示指示点 */
  dots?: boolean
}

const Carousel = React.forwardRef<HTMLDivElement, CarouselProps>(
  ({ children, className, arrows = true, dots = false, ...props }, ref) => {
    const scrollRef = React.useRef<HTMLDivElement>(null)
    const [canLeft, setCanLeft] = React.useState(false)
    const [canRight, setCanRight] = React.useState(true)

    const checkScroll = () => {
      const el = scrollRef.current
      if (!el) return
      setCanLeft(el.scrollLeft > 0)
      setCanRight(el.scrollLeft < el.scrollWidth - el.clientWidth - 2)
    }

    React.useEffect(() => {
      checkScroll()
      const el = scrollRef.current
      el?.addEventListener('scroll', checkScroll)
      window.addEventListener('resize', checkScroll)
      return () => {
        el?.removeEventListener('scroll', checkScroll)
        window.removeEventListener('resize', checkScroll)
      }
    }, [children])

    const scroll = (dir: 'left' | 'right') => {
      const el = scrollRef.current
      if (!el) return
      el.scrollBy({ left: dir === 'left' ? -el.clientWidth : el.clientWidth, behavior: 'smooth' })
    }

    return (
      <div ref={ref} className={cn('relative', className)} {...props}>
        {arrows && (
          <>
            <Button
              type="button"
              variant="outline"
              size="icon"
              className="absolute left-0 top-1/2 z-10 -translate-y-1/2 rounded-full"
              onClick={() => scroll('left')}
              disabled={!canLeft}
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              variant="outline"
              size="icon"
              className="absolute right-0 top-1/2 z-10 -translate-y-1/2 rounded-full"
              onClick={() => scroll('right')}
              disabled={!canRight}
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </>
        )}
        <div
          ref={scrollRef}
          className="flex gap-3 overflow-x-auto scroll-smooth py-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          {children}
        </div>
      </div>
    )
  }
)
Carousel.displayName = 'Carousel'

export { Carousel }
