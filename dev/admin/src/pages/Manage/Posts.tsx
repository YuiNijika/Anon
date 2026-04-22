import { useState, useEffect } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
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
import { Pencil, Trash2, Plus, MoreHorizontal } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { usePosts } from '@/hooks'
import { cn } from '@/lib/utils'

export default function ManagePosts() {
  const { loading, data, total, loadPosts, deletePost } = usePosts()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [pageSize] = useState(20)
  const [searchKeyword, setSearchKeyword] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [typeFilter, setTypeFilter] = useState<string>('all')

  useEffect(() => {
    const params: Record<string, unknown> = { page, page_size: pageSize }
    if (searchKeyword) params.search = searchKeyword
    if (statusFilter !== 'all') params.status = statusFilter
    if (typeFilter !== 'all') params.type = typeFilter
    loadPosts(params)
  }, [page, pageSize, searchKeyword, statusFilter, typeFilter, loadPosts])

  const handleDelete = async (id: number) => {
    if (!window.confirm('确定要删除这篇文章吗？')) return
    const success = await deletePost(id)
    if (success) {
      const params: Record<string, unknown> = { page, page_size: pageSize }
      if (searchKeyword) params.search = searchKeyword
      if (statusFilter !== 'all') params.status = statusFilter
      if (typeFilter !== 'all') params.type = typeFilter
      loadPosts(params)
    }
  }

  const handleSearch = () => {
    setSearchKeyword(searchInput)
    setPage(1)
  }

  const totalPages = Math.max(1, Math.ceil(total / pageSize))

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>文章管理</CardTitle>
          <Button onClick={() => navigate('/write')}>
            <Plus className="mr-2 h-4 w-4" />
            新增文章
          </Button>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* 搜索区域 */}
          <div className="flex flex-col sm:flex-row gap-3">
            <div className="flex gap-2 flex-1">
              <Input
                placeholder="搜索标题或别名"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                className="w-full sm:w-[280px]"
              />
              <Button variant="secondary" onClick={handleSearch}>搜索</Button>
            </div>
          </div>

          {/* 筛选区域 */}
          <div className="space-y-3">
            <div className="text-sm font-medium text-muted-foreground">状态筛选</div>
            <div className="flex flex-wrap gap-2">
              {(['all', 'draft', 'publish'] as const).map((s) => (
                <Button
                  key={s}
                  variant={statusFilter === s ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => { setStatusFilter(s); setPage(1) }}
                >
                  {s === 'all' ? '全部' : s === 'draft' ? '草稿' : '已发布'}
                </Button>
              ))}
            </div>
          </div>

          <div className="space-y-3">
            <div className="text-sm font-medium text-muted-foreground">类型筛选</div>
            <div className="flex flex-wrap gap-2">
              {(['all', 'post', 'page'] as const).map((t) => (
                <Button
                  key={t}
                  variant={typeFilter === t ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => { setTypeFilter(t); setPage(1) }}
                >
                  {t === 'all' ? '全部' : t === 'post' ? '文章' : '页面'}
                </Button>
              ))}
            </div>
          </div>

          {loading ? (
            <div className="flex items-center justify-center py-12 text-muted-foreground">加载中...</div>
          ) : !data?.length ? (
            <div className="py-12 text-center text-muted-foreground">
              {searchKeyword ? '未找到匹配的文章' : '暂无文章'}
            </div>
          ) : (
            <>
              {/* 桌面端表格视图 */}
              <div className="hidden lg:block">
                <div className="rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-[80px]">ID</TableHead>
                        <TableHead>标题</TableHead>
                        <TableHead className="w-[80px]">类型</TableHead>
                        <TableHead className="w-[100px]">状态</TableHead>
                        <TableHead className="w-[100px]">浏览量</TableHead>
                        <TableHead className="w-[180px]">创建时间</TableHead>
                        <TableHead className="w-[80px]">操作</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.map((row) => (
                        <TableRow key={row.id}>
                          <TableCell>{row.id}</TableCell>
                          <TableCell className="max-w-[300px] truncate" title={row.title || '-'}>
                            {row.title || '-'}
                          </TableCell>
                          <TableCell>
                            <span className={cn(
                              'rounded px-2 py-0.5 text-xs',
                              row.type === 'post' ? 'bg-primary/10 text-primary' : 'bg-green-500/10 text-green-700 dark:text-green-400'
                            )}>
                              {row.type === 'post' ? '文章' : '页面'}
                            </span>
                          </TableCell>
                          <TableCell>
                            <span className={cn(
                              'rounded px-2 py-0.5 text-xs',
                              row.status === 'publish' ? 'bg-green-500/10 text-green-700 dark:text-green-400' : row.status === 'draft' ? 'bg-muted' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                            )}>
                              {row.status === 'draft' ? '草稿' : row.status === 'publish' ? '已发布' : row.status === 'private' ? '私有' : row.status}
                            </span>
                          </TableCell>
                          <TableCell>{row.views ?? 0}</TableCell>
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
                                <DropdownMenuItem onClick={() => navigate(`/write?id=${row.id}`)}>
                                  <Pencil className="mr-2 h-4 w-4" />
                                  编辑
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                  className="text-destructive"
                                  onClick={() => handleDelete(row.id)}
                                >
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
              </div>

              {/* 移动端卡片视图 */}
              <div className="lg:hidden space-y-3">
                {data.map((row) => (
                  <Card key={row.id}>
                    <CardContent className="p-4">
                      <div className="space-y-3">
                        <div className="flex items-start justify-between">
                          <div className="flex-1 min-w-0">
                            <h3 className="font-medium text-base leading-tight mb-1">
                              {row.title || '无标题'}
                            </h3>
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                              <span>ID: {row.id}</span>
                              <span>浏览量: {row.views ?? 0}</span>
                            </div>
                          </div>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              <DropdownMenuItem onClick={() => navigate(`/write?id=${row.id}`)}>
                                <Pencil className="mr-2 h-4 w-4" />
                                编辑
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                className="text-destructive"
                                onClick={() => handleDelete(row.id)}
                              >
                                <Trash2 className="mr-2 h-4 w-4" />
                                删除
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>

                        <div className="flex items-center gap-2">
                          <Badge variant={row.status === 'publish' ? 'default' : 'secondary'}>
                            {row.status === 'draft' ? '草稿' : row.status === 'publish' ? '已发布' : row.status === 'private' ? '私有' : row.status}
                          </Badge>
                          <Badge variant={row.type === 'post' ? 'default' : 'secondary'}>
                            {row.type === 'post' ? '文章' : '页面'}
                          </Badge>
                        </div>

                        {row.created_at && (
                          <div className="text-xs text-muted-foreground">
                            创建时间: {new Date(row.created_at * 1000).toLocaleString('zh-CN')}
                          </div>
                        )}
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
    </div>
  )
}
