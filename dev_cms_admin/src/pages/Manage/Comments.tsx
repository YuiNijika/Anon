import { useState, useEffect, useCallback } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
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
import { MessageSquare, MoreHorizontal, Trash2, CheckCircle, Clock, AlertCircle, Ban, Pencil, Search, Reply } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { useComments } from '@/hooks'
import type { Comment, CommentListParams } from '@/services/admin'
import { cn } from '@/lib/utils'

const statusOptions = [
  { value: 'all', label: '全部' },
  { value: 'pending', label: '待审核' },
  { value: 'approved', label: '已通过' },
  { value: 'spam', label: '垃圾' },
  { value: 'trash', label: '已删除' },
]

const typeOptions = [
  { value: 'all', label: '全部' },
  { value: 'user', label: '登录用户' },
  { value: 'guest', label: '游客' },
]

const isReplyOptions = [
  { value: 'all', label: '全部' },
  { value: '1', label: '仅根评论' },
  { value: '2', label: '仅回复' },
]

const statusLabels: Record<string, string> = {
  pending: '待审核',
  approved: '已通过',
  spam: '垃圾',
  trash: '已删除',
}

export default function ManageComments() {
  const { loading, data, total, loadComments, updateCommentStatus, updateCommentContent, deleteComment } = useComments()
  const [page, setPage] = useState(1)
  const [pageSize] = useState(20)
  const [filterStatus, setFilterStatus] = useState<string>('')
  const [filterType, setFilterType] = useState<string>('')
  const [filterKeyword, setFilterKeyword] = useState('')
  const [filterIsReply, setFilterIsReply] = useState<string>('')
  const [filterDateFrom, setFilterDateFrom] = useState('')
  const [filterDateTo, setFilterDateTo] = useState('')
  const [editOpen, setEditOpen] = useState(false)
  const [editingComment, setEditingComment] = useState<Comment | null>(null)
  const [editContent, setEditContent] = useState('')

  const buildParams = useCallback((p: number = page): CommentListParams => {
    const params: CommentListParams = { page: p, page_size: pageSize }
    if (filterStatus) params.status = filterStatus
    if (filterType === 'user' || filterType === 'guest') params.type = filterType
    if (filterKeyword.trim()) params.keyword = filterKeyword.trim()
    if (filterIsReply === '1' || filterIsReply === '2') params.is_reply = Number(filterIsReply) as 1 | 2
    if (filterDateFrom) params.date_from = filterDateFrom
    if (filterDateTo) params.date_to = filterDateTo
    return params
  }, [page, pageSize, filterStatus, filterType, filterKeyword, filterIsReply, filterDateFrom, filterDateTo])

  const doLoad = useCallback(() => {
    loadComments(buildParams())
  }, [loadComments, buildParams])

  useEffect(() => {
    loadComments(buildParams())
  }, [page, pageSize, filterStatus, filterType, filterIsReply, filterDateFrom, filterDateTo, loadComments])

  const handleSearch = () => {
    setPage(1)
    loadComments(buildParams(1))
  }

  const handleStatusChange = async (id: number, status: 'approved' | 'pending' | 'spam' | 'trash') => {
    const ok = await updateCommentStatus(id, status)
    if (ok) doLoad()
  }

  const handleDelete = async (id: number) => {
    if (!window.confirm('确定要删除这条评论吗？删除后不可恢复。')) return
    const ok = await deleteComment(id)
    if (ok) doLoad()
  }

  const openEdit = (row: Comment) => {
    setEditingComment(row)
    setEditContent(row.content)
    setEditOpen(true)
  }

  const closeEdit = () => {
    setEditOpen(false)
    setEditingComment(null)
    setEditContent('')
  }

  const handleSaveEdit = async () => {
    if (!editingComment) return
    const ok = await updateCommentContent(editingComment.id, editContent)
    if (ok) {
      closeEdit()
      doLoad()
    }
  }

  const totalPages = Math.max(1, Math.ceil(total / pageSize))

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <MessageSquare className="h-5 w-5" />
            评论管理
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* 移动端筛选区域 */}
          <div className="lg:hidden space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-medium">筛选条件</h3>
              <Button variant="outline" size="sm" onClick={handleSearch}>
                <Search className="mr-1.5 h-4 w-4" />
                查询
              </Button>
            </div>
            
            <div className="space-y-3">
              <div>
                <label className="text-xs text-muted-foreground mb-1 block">状态</label>
                <Select value={filterStatus || 'all'} onValueChange={(v) => { setFilterStatus(v === 'all' ? '' : v); setPage(1); }}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="状态" />
                  </SelectTrigger>
                  <SelectContent>
                    {statusOptions.map((opt) => (
                      <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div>
                <label className="text-xs text-muted-foreground mb-1 block">类型</label>
                <Select value={filterType || 'all'} onValueChange={(v) => { setFilterType(v === 'all' ? '' : v); setPage(1); }}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="类型" />
                  </SelectTrigger>
                  <SelectContent>
                    {typeOptions.map((opt) => (
                      <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div>
                <label className="text-xs text-muted-foreground mb-1 block">根/回复</label>
                <Select value={filterIsReply || 'all'} onValueChange={(v) => { setFilterIsReply(v === 'all' ? '' : v); setPage(1); }}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="根/回复" />
                  </SelectTrigger>
                  <SelectContent>
                    {isReplyOptions.map((opt) => (
                      <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div>
                <label className="text-xs text-muted-foreground mb-1 block">内容关键词</label>
                <Input
                  placeholder="输入关键词"
                  value={filterKeyword}
                  onChange={(e) => setFilterKeyword(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs text-muted-foreground mb-1 block">开始日期</label>
                  <Input
                    type="date"
                    value={filterDateFrom}
                    onChange={(e) => { setFilterDateFrom(e.target.value); setPage(1); }}
                    aria-label="开始日期"
                  />
                </div>
                <div>
                  <label className="text-xs text-muted-foreground mb-1 block">结束日期</label>
                  <Input
                    type="date"
                    value={filterDateTo}
                    onChange={(e) => { setFilterDateTo(e.target.value); setPage(1); }}
                    aria-label="结束日期"
                  />
                </div>
              </div>
            </div>
          </div>

          {/* 桌面端筛选区域 */}
          <div className="hidden lg:flex flex-wrap items-end gap-3 rounded-lg border bg-muted/30 p-3">
            <Select value={filterStatus || 'all'} onValueChange={(v) => { setFilterStatus(v === 'all' ? '' : v); setPage(1); }}>
              <SelectTrigger className="w-[120px]">
                <SelectValue placeholder="状态" />
              </SelectTrigger>
              <SelectContent>
                {statusOptions.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={filterType || 'all'} onValueChange={(v) => { setFilterType(v === 'all' ? '' : v); setPage(1); }}>
              <SelectTrigger className="w-[120px]">
                <SelectValue placeholder="类型" />
              </SelectTrigger>
              <SelectContent>
                {typeOptions.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={filterIsReply || 'all'} onValueChange={(v) => { setFilterIsReply(v === 'all' ? '' : v); setPage(1); }}>
              <SelectTrigger className="w-[120px]">
                <SelectValue placeholder="根/回复" />
              </SelectTrigger>
              <SelectContent>
                {isReplyOptions.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Input
              className="w-[160px]"
              placeholder="内容关键词"
              value={filterKeyword}
              onChange={(e) => setFilterKeyword(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
            />
            <div className="flex flex-col gap-1">
              <label className="text-xs text-muted-foreground">开始日期</label>
              <Input
                type="date"
                className="w-[140px]"
                value={filterDateFrom}
                onChange={(e) => { setFilterDateFrom(e.target.value); setPage(1); }}
                aria-label="开始日期"
              />
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-xs text-muted-foreground">结束日期</label>
              <Input
                type="date"
                className="w-[140px]"
                value={filterDateTo}
                onChange={(e) => { setFilterDateTo(e.target.value); setPage(1); }}
                aria-label="结束日期"
              />
            </div>
            <Button variant="secondary" size="sm" onClick={handleSearch}>
              <Search className="mr-1.5 h-4 w-4" />
              查询
            </Button>
          </div>

          {loading ? (
            <div className="py-12 text-center text-muted-foreground">加载中...</div>
          ) : !data?.length ? (
            <div className="py-12 text-center text-muted-foreground">暂无评论</div>
          ) : (
            <>
              {/* 桌面端表格视图 */}
              <div className="hidden lg:block">
                <div className="rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-[50px]">ID</TableHead>
                        <TableHead className="w-[140px]">文章</TableHead>
                        <TableHead className="w-[120px]">评论者</TableHead>
                        <TableHead>内容</TableHead>
                        <TableHead className="w-[80px]">状态</TableHead>
                        <TableHead className="w-[90px]">IP</TableHead>
                        <TableHead className="w-[130px]">环境</TableHead>
                        <TableHead className="w-[130px]">时间</TableHead>
                        <TableHead className="w-[70px]">操作</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.map((row: Comment) => (
                        <TableRow
                          key={row.id}
                          className={cn(
                            row.is_reply && 'bg-muted/40'
                          )}
                        >
                          <TableCell className="text-muted-foreground">
                            {row.is_reply && (
                              <Reply className="inline h-3.5 w-3.5 text-muted-foreground mr-0.5 align-middle" aria-hidden />
                            )}
                            {row.id}
                          </TableCell>
                          <TableCell className="max-w-[160px] truncate" title={row.post_title}>
                            {row.post_title || `#${row.post_id}`}
                          </TableCell>
                          <TableCell className="max-w-[140px]">
                            <span className="truncate block">{row.name || row.email || '-'}</span>
                            {row.type === 'user' && (
                              <span className="text-xs text-muted-foreground">登录用户</span>
                            )}
                            {row.is_reply && row.reply_to_name && (
                              <span className="text-xs text-primary font-medium block">回复 @{row.reply_to_name}</span>
                            )}
                          </TableCell>
                          <TableCell className="max-w-[260px] text-muted-foreground" title={row.content}>
                            <span className="line-clamp-2 break-words">{row.content}</span>
                          </TableCell>
                          <TableCell>
                            <span
                              className={cn(
                                'text-sm',
                                row.status === 'approved' && 'text-green-600 dark:text-green-400',
                                row.status === 'pending' && 'text-amber-600 dark:text-amber-400',
                                row.status === 'spam' && 'text-orange-600 dark:text-orange-400',
                                row.status === 'trash' && 'text-muted-foreground'
                              )}
                            >
                              {statusLabels[row.status] ?? row.status}
                            </span>
                          </TableCell>
                          <TableCell className="text-muted-foreground text-sm font-mono">
                            {row.ip || '-'}
                          </TableCell>
                          <TableCell className="text-muted-foreground text-xs" title={row.user_agent || undefined}>
                            {(row.ua_browser != null && row.ua_os != null) ? (
                              <span className="block truncate max-w-[120px] cursor-help">
                                {row.ua_browser} · {row.ua_os}
                              </span>
                            ) : (
                              <span className="truncate max-w-[120px] block">{row.user_agent || '-'}</span>
                            )}
                          </TableCell>
                          <TableCell className="text-muted-foreground text-sm">
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
                                <DropdownMenuItem onClick={() => openEdit(row)}>
                                  <Pencil className="mr-2 h-4 w-4" />
                                  编辑
                                </DropdownMenuItem>
                                {row.status !== 'approved' && (
                                  <DropdownMenuItem onClick={() => handleStatusChange(row.id, 'approved')}>
                                    <CheckCircle className="mr-2 h-4 w-4" />
                                    通过
                                  </DropdownMenuItem>
                                )}
                                {row.status !== 'pending' && (
                                  <DropdownMenuItem onClick={() => handleStatusChange(row.id, 'pending')}>
                                    <Clock className="mr-2 h-4 w-4" />
                                    待审核
                                  </DropdownMenuItem>
                                )}
                                {row.status !== 'spam' && (
                                  <DropdownMenuItem onClick={() => handleStatusChange(row.id, 'spam')}>
                                    <AlertCircle className="mr-2 h-4 w-4" />
                                    标为垃圾
                                  </DropdownMenuItem>
                                )}
                                {row.status !== 'trash' && (
                                  <DropdownMenuItem onClick={() => handleStatusChange(row.id, 'trash')}>
                                    <Ban className="mr-2 h-4 w-4" />
                                    移至回收站
                                  </DropdownMenuItem>
                                )}
                                <DropdownMenuItem className="text-destructive" onClick={() => handleDelete(row.id)}>
                                  <Trash2 className="mr-2 h-4 w-4" />
                                  永久删除
                                </DropdownMenuItem>
                              </DropdownMenuContent>
                            </DropdownMenu>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </div>

              {/* 移动端卡片视图 */}
              <div className="lg:hidden space-y-3">
                {data.map((row: Comment) => (
                  <Card key={row.id} className={cn(row.is_reply && 'bg-muted/20')}>
                    <CardContent className="p-4">
                      <div className="space-y-3">
                        {/* 标题行 */}
                        <div className="flex items-start justify-between">
                          <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-muted-foreground">
                              {row.is_reply && <Reply className="inline h-3.5 w-3.5 mr-1" />}
                              #{row.id}
                            </span>
                            <Badge variant={row.status === 'approved' ? 'default' : 'secondary'}>
                              {statusLabels[row.status] ?? row.status}
                            </Badge>
                          </div>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              <DropdownMenuItem onClick={() => openEdit(row)}>
                                <Pencil className="mr-2 h-4 w-4" />
                                编辑
                              </DropdownMenuItem>
                              {row.status !== 'approved' && (
                                <DropdownMenuItem onClick={() => handleStatusChange(row.id, 'approved')}>
                                  <CheckCircle className="mr-2 h-4 w-4" />
                                  通过
                                </DropdownMenuItem>
                              )}
                              {row.status !== 'pending' && (
                                <DropdownMenuItem onClick={() => handleStatusChange(row.id, 'pending')}>
                                  <Clock className="mr-2 h-4 w-4" />
                                  待审核
                                </DropdownMenuItem>
                              )}
                              {row.status !== 'spam' && (
                                <DropdownMenuItem onClick={() => handleStatusChange(row.id, 'spam')}>
                                  <AlertCircle className="mr-2 h-4 w-4" />
                                  标为垃圾
                                </DropdownMenuItem>
                              )}
                              {row.status !== 'trash' && (
                                <DropdownMenuItem onClick={() => handleStatusChange(row.id, 'trash')}>
                                  <Ban className="mr-2 h-4 w-4" />
                                  移至回收站
                                </DropdownMenuItem>
                              )}
                              <DropdownMenuItem className="text-destructive" onClick={() => handleDelete(row.id)}>
                                <Trash2 className="mr-2 h-4 w-4" />
                                永久删除
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>

                        {/* 评论内容 */}
                        <div className="bg-muted/30 rounded p-3">
                          <p className="text-sm leading-relaxed">{row.content}</p>
                        </div>

                        {/* 评论者信息 */}
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            <span className="font-medium">{row.name || row.email || '匿名'}</span>
                            {row.type === 'user' && (
                              <Badge variant="outline" className="text-xs">登录用户</Badge>
                            )}
                          </div>
                          {row.is_reply && row.reply_to_name && (
                            <p className="text-sm text-primary">回复 @{row.reply_to_name}</p>
                          )}
                        </div>

                        {/* 文章信息 */}
                        {row.post_title && (
                          <div>
                            <p className="text-xs text-muted-foreground mb-1">文章</p>
                            <p className="text-sm font-medium truncate">{row.post_title}</p>
                          </div>
                        )}

                        {/* 元信息 */}
                        <div className="grid grid-cols-2 gap-4 text-xs text-muted-foreground">
                          {row.ip && (
                            <div>
                              <p className="mb-1">IP 地址</p>
                              <p className="font-mono">{row.ip}</p>
                            </div>
                          )}
                          {row.created_at && (
                            <div>
                              <p className="mb-1">时间</p>
                              <p>{new Date(row.created_at * 1000).toLocaleString('zh-CN')}</p>
                            </div>
                          )}
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>

              {/* 分页区域 */}
              <div className="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
                <span>共 {total} 条</span>
                <div className="flex items-center gap-2">
                  <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                    上一页
                  </Button>
                  <span>第 {page} / {totalPages} 页</span>
                  <Button variant="outline" size="sm" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)}>
                    下一页
                  </Button>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <Dialog open={editOpen} onOpenChange={(open) => !open && closeEdit()}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>编辑评论</DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid gap-2">
              <label className="text-sm font-medium">评论内容</label>
              <textarea
                className="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                value={editContent}
                onChange={(e) => setEditContent(e.target.value)}
                placeholder="评论内容"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={closeEdit}>
              取消
            </Button>
            <Button onClick={handleSaveEdit}>
              保存
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
