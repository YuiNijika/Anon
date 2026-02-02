import { useState, useEffect, useRef } from 'react'
import { toast } from 'sonner'
import { Upload, Trash2, MoreHorizontal, Inbox, CheckCircle, XCircle } from 'lucide-react'
import { getErrorMessage } from '@/lib/utils'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useAttachments } from '@/hooks'
import { buildPublicUrl, getApiBaseUrl } from '@/utils/api'
import { getAdminToken, checkLoginStatus, getApiPrefix } from '@/utils/token'

interface UploadFileItem {
  uid: string
  name: string
  status: 'uploading' | 'done' | 'error'
  percent?: number
  errorMessage?: string
}

function formatFileSize(size: number): string {
  if (!size) return '-'
  if (size < 1024) return `${size} B`
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(2)} KB`
  return `${(size / (1024 * 1024)).toFixed(2)} MB`
}

export default function ManageFiles() {
  const { loading, data, loadAttachments, deleteAttachment } = useAttachments()
  const [uploadModalOpen, setUploadModalOpen] = useState(false)
  const [uploadFileList, setUploadFileList] = useState<UploadFileItem[]>([])
  const [sort, setSort] = useState<'new' | 'old'>('new')
  const fileInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    loadAttachments({ sort })
  }, [sort, loadAttachments])

  const handleDelete = async (id: number) => {
    if (!window.confirm('确定要删除这个附件吗？')) return
    const success = await deleteAttachment(id)
    if (success) loadAttachments({ sort })
  }

  const handleUpload = async (file: File) => {
    const fileItem: UploadFileItem = {
      uid: `${Date.now()}-${Math.random()}`,
      name: file.name,
      status: 'uploading',
      percent: 0,
    }
    setUploadFileList((prev) => [...prev, fileItem])

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

      const xhr = new XMLHttpRequest()
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100)
          setUploadFileList((prev) =>
            prev.map((item) => (item.uid === fileItem.uid ? { ...item, percent } : item))
          )
        }
      })
      xhr.addEventListener('load', () => {
        let errorMessage = '上传失败'
        try {
          if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText)
            if (response.code === 200) {
              setUploadFileList((prev) =>
                prev.map((item) =>
                  item.uid === fileItem.uid ? { ...item, status: 'done', percent: 100 } : item
                )
              )
              toast.success(`${file.name} 上传成功`)
              loadAttachments({ sort })
              return
            }
            errorMessage = response.message || '上传失败'
          } else {
            try {
              const errRes = JSON.parse(xhr.responseText)
              errorMessage = errRes.message || `服务器错误 (${xhr.status})`
            } catch {
              errorMessage = `服务器错误 (${xhr.status})`
            }
          }
        } catch {
          errorMessage = '解析响应失败'
        }
        setUploadFileList((prev) =>
          prev.map((item) =>
            item.uid === fileItem.uid ? { ...item, status: 'error', errorMessage } : item
          )
        )
        toast.error(errorMessage)
      })
      xhr.addEventListener('error', () => {
        const msg = '网络错误，请检查网络连接'
        setUploadFileList((prev) =>
          prev.map((item) => (item.uid === fileItem.uid ? { ...item, status: 'error', errorMessage: msg } : item))
        )
        toast.error(msg)
      })
      xhr.open('POST', url)
      Object.keys(headers).forEach((key) => xhr.setRequestHeader(key, (headers as Record<string, string>)[key]))
      xhr.send(formData)
    } catch (err) {
      const msg = getErrorMessage(err, '上传失败')
      setUploadFileList((prev) =>
        prev.map((item) => (item.uid === fileItem.uid ? { ...item, status: 'error', errorMessage: msg } : item))
      )
      toast.error(msg)
    }
  }

  const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files
    if (!files?.length) return
    for (let i = 0; i < files.length; i++) {
      handleUpload(files[i]!)
    }
    e.target.value = ''
  }

  const handleUploadModalClose = () => {
    const hasUploading = uploadFileList.some((item) => item.status === 'uploading')
    if (hasUploading && !window.confirm('仍有文件正在上传，确定要关闭吗？')) return
    setUploadModalOpen(false)
    setUploadFileList([])
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>附件管理</CardTitle>
          <div className="flex items-center gap-2">
            <Select value={sort} onValueChange={(v: 'new' | 'old') => setSort(v)}>
              <SelectTrigger className="w-[120px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="new">新到老</SelectItem>
                <SelectItem value="old">老到新</SelectItem>
              </SelectContent>
            </Select>
            <Button onClick={() => setUploadModalOpen(true)}>
              <Upload className="mr-2 h-4 w-4" />
              上传文件
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="py-12 text-center text-muted-foreground">加载中...</div>
          ) : !data?.length ? (
            <div className="py-12 text-center text-muted-foreground">暂无附件</div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-[100px]">预览</TableHead>
                    <TableHead>文件名</TableHead>
                    <TableHead className="w-[120px]">类型</TableHead>
                    <TableHead className="w-[100px]">大小</TableHead>
                    <TableHead className="w-[180px]">上传时间</TableHead>
                    <TableHead className="w-[80px]">操作</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>
                        {row.mime_type?.startsWith('image/') ? (
                          <img
                            src={buildPublicUrl(row.url)}
                            alt={row.name}
                            className="h-14 w-14 rounded object-cover"
                          />
                        ) : (
                          <span className="text-muted-foreground">-</span>
                        )}
                      </TableCell>
                      <TableCell className="max-w-[300px] truncate" title={row.name || '-'}>
                        {row.name || '-'}
                      </TableCell>
                      <TableCell className="max-w-[120px] truncate text-muted-foreground">{row.mime_type ?? '-'}</TableCell>
                      <TableCell>{formatFileSize(row.size ?? 0)}</TableCell>
                      <TableCell className="text-muted-foreground">
                        {row.created_at ? new Date(row.created_at * 1000).toLocaleString('zh-CN') : '-'}
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon">
                              <MoreHorizontal className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem className="text-destructive" onClick={() => handleDelete(row.id)}>
                              <Trash2 className="mr-2 h-4 w-4" />
                              删除
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <Dialog open={uploadModalOpen} onOpenChange={(open) => !open && handleUploadModalClose()}>
        <DialogContent className="sm:max-w-[560px]">
          <DialogHeader>
            <DialogTitle>上传文件</DialogTitle>
          </DialogHeader>
          <input
            ref={fileInputRef}
            type="file"
            multiple
            className="hidden"
            onChange={onFileChange}
          />
          <div
            role="button"
            tabIndex={0}
            onClick={() => fileInputRef.current?.click()}
            onKeyDown={(e) => e.key === 'Enter' && fileInputRef.current?.click()}
            className="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 bg-muted/30 py-12 transition-colors hover:border-primary/50 hover:bg-muted/50"
          >
            <Inbox className="mb-2 h-10 w-10 text-muted-foreground" />
            <p className="text-sm font-medium">点击或拖拽文件到此区域上传</p>
            <p className="text-xs text-muted-foreground">支持多文件上传</p>
          </div>
          {uploadFileList.length > 0 && (
            <div className="space-y-2">
              <p className="text-sm font-medium">上传进度</p>
              <ul className="space-y-2">
                {uploadFileList.map((item) => (
                  <li key={item.uid} className="rounded border p-2">
                    <div className="flex items-center justify-between gap-2">
                      <span className="min-w-0 flex-1 truncate text-sm">{item.name}</span>
                      {item.status === 'done' && <CheckCircle className="h-4 w-4 shrink-0 text-green-600" />}
                      {item.status === 'error' && <XCircle className="h-4 w-4 shrink-0 text-destructive" />}
                    </div>
                    {item.status === 'uploading' && (
                      <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                        <div
                          className="h-full bg-primary transition-all"
                          style={{ width: `${item.percent ?? 0}%` }}
                        />
                      </div>
                    )}
                    {item.status === 'done' && (
                      <div className="mt-2 h-1.5 w-full rounded-full bg-green-500/20">
                        <div className="h-full w-full rounded-full bg-green-500" />
                      </div>
                    )}
                    {item.status === 'error' && item.errorMessage && (
                      <p className="mt-1 text-xs text-destructive">{item.errorMessage}</p>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
