import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Lightbox } from '@/components/ui/lightbox'
import { useState, useRef } from 'react'
import { getApiBaseUrl } from '@/utils/api'

const nullSvgUrl = `${getApiBaseUrl()}/anon/static/img/null`

export interface ItemCardData {
  name: string
  displayName?: string
  description?: string
  version?: string
  author?: string
  screenshot?: string
  url?: {
    github?: string
  }
}

interface ItemCardProps {
  item: ItemCardData
  onDetailClick?: (item: ItemCardData) => void
  onActionClick?: (item: ItemCardData) => void
  detailButtonText?: string
  actionButtonText?: string
  actionButtonVariant?: 'default' | 'destructive' | 'outline' | 'secondary'
  showScreenshot?: boolean
  loading?: boolean
  actionDisabled?: boolean
  badgeText?: string
  renderCustomActions?: (item: ItemCardData) => React.ReactNode
}

export function ItemCard({
  item,
  onDetailClick,
  onActionClick,
  detailButtonText = '查看详情',
  actionButtonText,
  actionButtonVariant = 'default',
  showScreenshot = true,
  loading = false,
  actionDisabled = false,
  badgeText,
  renderCustomActions,
}: ItemCardProps) {
  const [lightboxOpen, setLightboxOpen] = useState(false)
  const [lightboxSrc, setLightboxSrc] = useState('')
  const hasReportedNullError = useRef(false)

  const getScreenshotUrl = () => {
    const screenshot = item.screenshot?.trim()
    if (!screenshot) return nullSvgUrl
    if (screenshot.startsWith('http://') || screenshot.startsWith('https://')) return screenshot
    const baseUrl = getApiBaseUrl()
    if (screenshot.startsWith('/')) return `${baseUrl}${screenshot}`
    return `${baseUrl}/${screenshot}`
  }

  const displayName = item.displayName || item.name

  return (
    <>
      <Card className="overflow-hidden h-full flex flex-col">
        {showScreenshot && (
          <button
            type="button"
            className="relative aspect-[4/3] w-full overflow-hidden bg-muted cursor-zoom-in outline-none"
            onClick={() => {
              setLightboxSrc(getScreenshotUrl())
              setLightboxOpen(true)
            }}
          >
            <img
              draggable={false}
              alt={displayName}
              src={getScreenshotUrl()}
              className="h-full w-full object-cover"
              onError={(e) => {
                const target = e.target as HTMLImageElement
                if (target.src?.startsWith('data:')) return
                const failedUrl = target.src ?? ''
                target.src = nullSvgUrl
                if (failedUrl.includes('/anon/static/img/null') && !hasReportedNullError.current) {
                  hasReportedNullError.current = true
                }
              }}
            />
          </button>
        )}
        <CardHeader className="pb-2">
          <CardTitle className="flex items-center gap-2 text-base">
            {displayName}
            {badgeText && (
              <span className="rounded bg-primary/10 px-2 py-0.5 text-xs font-normal text-primary">
                {badgeText}
              </span>
            )}
          </CardTitle>
          {item.description && (
            <p className="text-sm text-muted-foreground">{item.description}</p>
          )}
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
            {item.version && (
              <span className="rounded bg-muted px-1.5 py-0.5">{item.version}</span>
            )}
            {item.author && <span>{item.author}</span>}
            {item.url?.github && (
              <a
                href={item.url.github}
                target="_blank"
                rel="noopener noreferrer"
                className="text-primary underline underline-offset-2"
              >
                GitHub
              </a>
            )}
          </div>
        </CardHeader>
        <CardContent className="flex flex-col gap-2 pt-0">
          {renderCustomActions ? (
            renderCustomActions(item)
          ) : (
            <>
              {onDetailClick && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => onDetailClick(item)}
                  disabled={loading}
                >
                  {detailButtonText}
                </Button>
              )}
              {onActionClick && actionButtonText && (
                <Button
                  variant={actionButtonVariant}
                  size="sm"
                  onClick={() => onActionClick(item)}
                  disabled={loading || actionDisabled}
                >
                  {actionButtonText}
                </Button>
              )}
            </>
          )}
        </CardContent>
      </Card>

      <Lightbox
        open={lightboxOpen}
        onOpenChange={setLightboxOpen}
        src={lightboxSrc}
      />
    </>
  )
}

interface ItemGridProps {
  items: ItemCardData[]
  loading?: boolean
  emptyMessage?: string
  skeletonCount?: number
  onDetailClick?: (item: ItemCardData) => void
  onActionClick?: (item: ItemCardData) => void
  detailButtonText?: string
  actionButtonText?: string | ((item: ItemCardData) => string)
  actionButtonVariant?: 'default' | 'destructive' | 'outline' | 'secondary'
  showScreenshot?: boolean
  getItemBadgeText?: (item: ItemCardData) => string | undefined
  isActionDisabled?: (item: ItemCardData) => boolean
  columns?: {
    sm?: number
    md?: number
    lg?: number
    xl?: number
    '2xl'?: number
  }
  renderCustomActions?: (item: ItemCardData) => React.ReactNode
}

export function ItemGrid({
  items,
  loading = false,
  emptyMessage = '暂无数据',
  skeletonCount = 4,
  onDetailClick,
  onActionClick,
  detailButtonText,
  actionButtonText,
  actionButtonVariant,
  showScreenshot = true,
  getItemBadgeText,
  isActionDisabled,
  renderCustomActions,
}: Omit<ItemGridProps, 'columns'>) {
  if (loading) {
    return (
      <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
        {[...Array(skeletonCount)].map((_, i) => (
          <Card key={i}>
            {showScreenshot && <Skeleton className="aspect-[4/3] w-full rounded-t-lg" />}
            <CardContent className="pt-4">
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="mt-2 h-3 w-full" />
            </CardContent>
          </Card>
        ))}
      </div>
    )
  }

  if (items.length === 0) {
    return (
      <Card>
        <CardContent className="py-12 text-center text-muted-foreground">
          {emptyMessage}
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
      {items.map((item) => (
        <ItemCard
          key={item.name}
          item={item}
          onDetailClick={onDetailClick}
          onActionClick={onActionClick}
          detailButtonText={detailButtonText}
          actionButtonText={typeof actionButtonText === 'function' ? actionButtonText(item) : actionButtonText}
          actionButtonVariant={actionButtonVariant}
          showScreenshot={showScreenshot}
          badgeText={getItemBadgeText?.(item)}
          actionDisabled={isActionDisabled?.(item)}
          renderCustomActions={renderCustomActions}
        />
      ))}
    </div>
  )
}
