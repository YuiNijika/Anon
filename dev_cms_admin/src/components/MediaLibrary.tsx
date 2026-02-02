import { useState, useEffect, useRef } from 'react'
import { Upload, Trash2, Search } from 'lucide-react'
import { toast } from 'sonner'
import { useApiAdmin } from '@/hooks'
import { getErrorMessage } from '@/lib/utils'
import { buildPublicUrl, getApiBaseUrl } from '@/utils/api'
import { getAdminToken, checkLoginStatus, getApiPrefix } from '@/utils/token'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { cn } from '@/lib/utils'

interface MediaLibraryProps {
  open: boolean
  onClose: () => void
  onSelect?: (attachment: any) => void
  multiple?: boolean
  accept?: string
}

export default function MediaLibrary({
  open,
  onClose,
  onSelect,
  multiple = false,
  accept,
}: MediaLibraryProps) {
  const apiAdmin = useApiAdmin()
  const [loading, setLoading] = useState(false)
  const [attachments, setAttachments] = useState<any[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [pageSize] = useState(20)
  const [filterType, setFilterType] = useState<string>('all')
  const [searchKeyword, setSearchKeyword] = useState('')
  const [selectedIds, setSelectedIds] = useState<number[]>([])
  const [imageFormat, setImageFormat] = useState<'original' | 'webp' | 'png' | 'jpg' | 'jpeg'>(
    'original'
  )
  const fileInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (open) {
      loadAttachments()
    }
  }, [open, page, filterType])

  const loadAttachments = async () => {
    try {
      setLoading(true)
      const params: Record<string, unknown> = { page, page_size: pageSize }
      if (filterType !== 'all') params.mime_type = filterType

      const response = await apiAdmin.admin.get('/attachments', params)
      if (response.code === 200) {
        setAttachments(response.data.list || [])
        setTotal(response.data.total || 0)
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '加载附件失败'))
    } finally {
      setLoading(false)
    }
  }

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return

    try {
      const formData = new FormData()
      formData.append('file', file)

      const baseUrl = getApiBaseUrl()
      const apiPrefix = await getApiPrefix()
      const prefix = apiPrefix || '/anon'
      const url = `${baseUrl}${prefix}/cms/admin/attachments`
      const isLoggedIn = await checkLoginStatus()
      const headers: HeadersInit = {}
      if (isLoggedIn) {
        const token = await getAdminToken()
        if (token) headers['X-API-Token'] = token
      }

      const response = await fetch(url, {
        method: 'POST',
        headers,
        body: formData,
        credentials: 'include',
      }).then((res) => res.json())

      if (response.code === 200) {
        toast.success('上传成功')
        loadAttachments()
      } else {
        toast.error(response.message || '上传失败')
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '上传失败'))
    }
    e.target.value = ''
  }

  const handleDelete = async (id: number) => {
    try {
      const baseUrl = getApiBaseUrl()
      const apiPrefix = await getApiPrefix()
      const prefix = apiPrefix || '/anon'
      const url = `${baseUrl}${prefix}/cms/admin/attachments?id=${id}`
      const isLoggedIn = await checkLoginStatus()
      const headers: HeadersInit = { 'Content-Type': 'application/json' }
      if (isLoggedIn) {
        const token = await getAdminToken()
        if (token) headers['X-API-Token'] = token
      }

      const response = await fetch(url, {
        method: 'DELETE',
        headers,
        credentials: 'include',
      }).then((res) => res.json())

      if (response.code === 200) {
        toast.success('删除成功')
        loadAttachments()
      } else {
        toast.error(response.message || '删除失败')
      }
    } catch (err) {
      toast.error(getErrorMessage(err, '删除失败'))
    }
  }

  const confirmDelete = async (id: number) => {
    if (!window.confirm('确定要删除该文件吗？此操作不可恢复。')) return
    await handleDelete(id)
  }

  const handleSelect = (attachment: any) => {
    if (multiple) {
      const newSelectedIds = selectedIds.includes(attachment.id)
        ? selectedIds.filter((id) => id !== attachment.id)
        : [...selectedIds, attachment.id]
      setSelectedIds(newSelectedIds)
    } else {
      onSelect?.(buildInsertAttachment(attachment))
      onClose()
    }
  }

  const handleConfirmSelection = () => {
    if (multiple && selectedIds.length > 0) {
      const selected = attachments
        .filter((a) => selectedIds.includes(a.id))
        .map((a) => buildInsertAttachment(a))
      onSelect?.(selected)
      onClose()
    }
  }

  const isImage = (mimeType: string) => mimeType?.startsWith('image/')

  const buildInsertAttachment = (attachment: any) => {
    if (!isImage(attachment?.mime_type)) return attachment
    if (imageFormat === 'original') return attachment
    const url = typeof attachment?.url === 'string' ? attachment.url : ''
    if (!url) return attachment
    return { ...attachment, insert_url: `${url}/${imageFormat}` }
  }

  const displayName = (a: any) => a?.name ?? a?.original_name ?? '-'

  const filteredAttachments = searchKeyword
    ? attachments.filter(
      (a) =>
        displayName(a).toLowerCase().includes(searchKeyword.toLowerCase()) ||
        (a.filename && a.filename.toLowerCase().includes(searchKeyword.toLowerCase()))
    )
    : attachments

  const totalPages = Math.max(1, Math.ceil(total / pageSize))

  return (
    <Dialog open={open} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-[900px] max-h-[90vh] flex flex-col gap-4">
        <DialogHeader>
          <DialogTitle>媒体库</DialogTitle>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex flex-wrap items-center gap-2">
              <input
                ref={fileInputRef}
                type="file"
                className="hidden"
                accept={accept}
                onChange={handleUpload}
              />
              <Button
                type="button"
                variant="default"
                onClick={() => fileInputRef.current?.click()}
              >
                <Upload className="mr-2 h-4 w-4" />
                上传文件
              </Button>
              {onSelect && (
                <Select
                  value={imageFormat}
                  onValueChange={(v) =>
                    setImageFormat(v as 'original' | 'webp' | 'png' | 'jpg' | 'jpeg')
                  }
                >
                  <SelectTrigger className="w-[140px]">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="original">插入原图</SelectItem>
                    <SelectItem value="webp">插入 WebP</SelectItem>
                    <SelectItem value="png">插入 PNG</SelectItem>
                    <SelectItem value="jpg">插入 JPG</SelectItem>
                    <SelectItem value="jpeg">插入 JPEG</SelectItem>
                  </SelectContent>
                </Select>
              )}
              <div className="relative">
                <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder="搜索文件..."
                  value={searchKeyword}
                  onChange={(e) => setSearchKeyword(e.target.value)}
                  className="w-[200px] pl-8"
                />
              </div>
            </div>
            <RadioGroup
              value={filterType}
              onValueChange={setFilterType}
              className="flex flex-row gap-2"
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="all" id="filter-all" />
                <Label htmlFor="filter-all" className="cursor-pointer text-sm">
                  全部
                </Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="image" id="filter-image" />
                <Label htmlFor="filter-image" className="cursor-pointer text-sm">
                  图片
                </Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="video" id="filter-video" />
                <Label htmlFor="filter-video" className="cursor-pointer text-sm">
                  视频
                </Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="audio" id="filter-audio" />
                <Label htmlFor="filter-audio" className="cursor-pointer text-sm">
                  音频
                </Label>
              </div>
            </RadioGroup>
          </div>

          <div
            className={cn(
              'grid gap-4 overflow-y-auto rounded-md border border-border bg-muted/30 p-4',
              'grid-cols-[repeat(auto-fill,minmax(150px,1fr))]',
              'max-h-[500px]'
            )}
          >
            {loading ? (
              <>
                {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
                  <div
                    key={i}
                    className="rounded-md border border-border bg-background p-2"
                  >
                    <Skeleton className="h-[120px] w-full rounded" />
                    <Skeleton className="mt-2 h-3 w-full" />
                  </div>
                ))}
              </>
            ) : filteredAttachments.length === 0 ? (
              <div className="col-span-full flex min-h-[200px] items-center justify-center py-12 text-muted-foreground">
                {searchKeyword ? '暂无匹配的媒体文件' : '暂无媒体文件'}
              </div>
            ) : (
              filteredAttachments.map((attachment) => {
                const selected = selectedIds.includes(attachment.id)
                return (
                  <div
                    key={attachment.id}
                    className={cn(
                      'relative cursor-pointer rounded-md border bg-background p-2 transition-colors hover:bg-muted/50',
                      selected ? 'border-primary ring-2 ring-primary/20' : 'border-border'
                    )}
                    onClick={() => handleSelect(attachment)}
                  >
                    {isImage(attachment.mime_type) ? (
                      <div className="aspect-[4/3] w-full overflow-hidden rounded">
                        <img
                          src={buildPublicUrl(attachment.url)}
                          alt={displayName(attachment)}
                          className="h-full w-full object-cover"
                        />
                      </div>
                    ) : (
                      <div className="flex aspect-[4/3] w-full items-center justify-center text-4xl">
                        📄
                      </div>
                    )}
                    <p
                      className="mt-2 truncate text-xs text-muted-foreground"
                      title={displayName(attachment)}
                    >
                      {displayName(attachment)}
                    </p>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="absolute right-1 top-1 h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                      onClick={(e) => {
                        e.stopPropagation()
                        confirmDelete(attachment.id)
                      }}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                )
              })
            )}
          </div>

          {total > pageSize && (
            <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
              >
                上一页
              </Button>
              <span>
                {page} / {totalPages}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={page >= totalPages}
                onClick={() => setPage((p) => p + 1)}
              >
                下一页
              </Button>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            关闭
          </Button>
          {multiple && (
            <Button
              onClick={handleConfirmSelection}
              disabled={selectedIds.length === 0}
            >
              插入选中 ({selectedIds.length})
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
