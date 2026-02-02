import { useState, useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
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
  DialogFooter,
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
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Plus, Pencil, Trash2, MoreHorizontal, User } from 'lucide-react'
import { useUsers, useAuth } from '@/hooks'
import type { User as UserType } from '@/services/admin'
import { cn } from '@/lib/utils'

const userSchema = z.object({
  name: z.string().min(1, '请输入用户名'),
  email: z.string().min(1, '请输入邮箱').email('邮箱格式不正确'),
  password: z.string().optional(),
  display_name: z.string().optional(),
  group: z.enum(['admin', 'editor', 'user']),
})

type UserFormValues = z.infer<typeof userSchema>

export default function ManageUsers() {
  const { loading, data, total, loadUsers, createUser, updateUser, deleteUser } = useUsers()
  const auth = useAuth()
  const [modalOpen, setModalOpen] = useState(false)
  const [editingRecord, setEditingRecord] = useState<UserType | null>(null)
  const [page, setPage] = useState(1)
  const [pageSize] = useState(20)
  const [searchKeyword, setSearchKeyword] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [filterGroup, setFilterGroup] = useState<string>('')
  const form = useForm<UserFormValues>({
    resolver: zodResolver(userSchema),
    defaultValues: {
      name: '',
      email: '',
      password: '',
      display_name: '',
      group: 'user',
    },
  })

  useEffect(() => {
    const params: Record<string, unknown> = { page, page_size: pageSize }
    if (searchKeyword) params.search = searchKeyword
    if (filterGroup) params.group = filterGroup
    loadUsers(params)
  }, [page, pageSize, searchKeyword, filterGroup, loadUsers])

  const handleAdd = () => {
    setEditingRecord(null)
    form.reset({ name: '', email: '', password: '', display_name: '', group: 'user' })
    setModalOpen(true)
  }

  const handleEdit = (record: UserType) => {
    setEditingRecord(record)
    form.reset({
      name: record.name,
      email: record.email,
      password: '',
      display_name: record.display_name ?? '',
      group: record.group,
    })
    setModalOpen(true)
  }

  const handleDelete = async (uid: number) => {
    if (!window.confirm('确定要删除这个用户吗？此操作不可恢复。')) return
    const success = await deleteUser(uid)
    if (success) {
      const params: Record<string, unknown> = { page, page_size: pageSize }
      if (searchKeyword) params.search = searchKeyword
      if (filterGroup) params.group = filterGroup
      loadUsers(params)
    }
  }

  const handleSubmit = async (values: UserFormValues) => {
    if (!editingRecord && (!values.password || values.password.length < 6)) {
      form.setError('password', { message: '密码至少6位' })
      return
    }
    if (editingRecord) {
      const updateData: Parameters<typeof updateUser>[0] = {
        uid: editingRecord.uid,
        name: values.name,
        email: values.email,
        display_name: values.display_name,
        group: values.group,
      }
      if (values.password?.trim()) (updateData as Record<string, unknown>).password = values.password
      const result = await updateUser(updateData)
      if (result) {
        setModalOpen(false)
        const params: Record<string, unknown> = { page, page_size: pageSize }
        if (searchKeyword) params.search = searchKeyword
        if (filterGroup) params.group = filterGroup
        loadUsers(params)
      }
    } else {
      const result = await createUser({ ...values, password: values.password || '' })
      if (result) {
        setModalOpen(false)
        const params: Record<string, unknown> = { page, page_size: pageSize }
        if (searchKeyword) params.search = searchKeyword
        if (filterGroup) params.group = filterGroup
        loadUsers(params)
      }
    }
  }

  const totalPages = Math.max(1, Math.ceil(total / pageSize))
  const isSelf = editingRecord && auth.user?.uid === editingRecord.uid
  const groupLabels: Record<string, string> = { admin: '管理员', editor: '编辑', user: '普通用户' }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>用户管理</CardTitle>
          <Button onClick={handleAdd}>
            <Plus className="mr-2 h-4 w-4" />
            新增用户
          </Button>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-2">
            <Input
              placeholder="搜索用户名、邮箱或显示名称"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && (setSearchKeyword(searchInput), setPage(1))}
              className="w-[280px]"
            />
            <Button variant="secondary" onClick={() => { setSearchKeyword(searchInput); setPage(1) }}>搜索</Button>
            <Select value={filterGroup || 'all'} onValueChange={(v) => { setFilterGroup(v === 'all' ? '' : v); setPage(1) }}>
              <SelectTrigger className="w-[140px]">
                <SelectValue placeholder="用户组" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">全部</SelectItem>
                <SelectItem value="admin">管理员</SelectItem>
                <SelectItem value="editor">编辑</SelectItem>
                <SelectItem value="user">普通用户</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {loading ? (
            <div className="py-12 text-center text-muted-foreground">加载中...</div>
          ) : !data?.length ? (
            <div className="py-12 text-center text-muted-foreground">暂无用户</div>
          ) : (
            <>
              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-[80px]">ID</TableHead>
                      <TableHead className="w-[80px]">头像</TableHead>
                      <TableHead className="w-[150px]">用户名</TableHead>
                      <TableHead className="w-[150px]">显示名称</TableHead>
                      <TableHead className="w-[200px]">邮箱</TableHead>
                      <TableHead className="w-[100px]">用户组</TableHead>
                      <TableHead className="w-[180px]">创建时间</TableHead>
                      <TableHead className="w-[80px]">操作</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {data.map((row) => (
                      <TableRow key={row.uid}>
                        <TableCell>{row.uid}</TableCell>
                        <TableCell>
                          <Avatar className="h-8 w-8">
                            <AvatarImage src={row.avatar} />
                            <AvatarFallback><User className="h-4 w-4" /></AvatarFallback>
                          </Avatar>
                        </TableCell>
                        <TableCell className="max-w-[150px] truncate">{row.name}</TableCell>
                        <TableCell className="max-w-[150px] truncate text-muted-foreground">{row.display_name || '-'}</TableCell>
                        <TableCell className="max-w-[200px] truncate">{row.email}</TableCell>
                        <TableCell>
                          <span className={cn(
                            'text-sm',
                            row.group === 'admin' && 'text-destructive',
                            row.group === 'editor' && 'text-amber-600 dark:text-amber-400',
                            row.group === 'user' && 'text-primary'
                          )}>
                            {groupLabels[row.group] ?? row.group}
                          </span>
                        </TableCell>
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
                              <DropdownMenuItem onClick={() => handleEdit(row)}>
                                <Pencil className="mr-2 h-4 w-4" />
                                编辑
                              </DropdownMenuItem>
                              <DropdownMenuItem className="text-destructive" onClick={() => handleDelete(row.uid)}>
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
              <div className="flex items-center justify-between text-sm text-muted-foreground">
                <span>共 {total} 条</span>
                <div className="flex items-center gap-2">
                  <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>上一页</Button>
                  <span>第 {page} / {totalPages} 页</span>
                  <Button variant="outline" size="sm" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)}>下一页</Button>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <Dialog open={modalOpen} onOpenChange={setModalOpen}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle>{editingRecord ? '编辑用户' : '新增用户'}</DialogTitle>
          </DialogHeader>
          <Form {...form}>
            <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>用户名</FormLabel>
                    <FormControl>
                      <Input placeholder="用户名" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>邮箱</FormLabel>
                    <FormControl>
                      <Input type="email" placeholder="邮箱地址" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{editingRecord ? '新密码（留空不修改）' : '密码'}</FormLabel>
                    <FormControl>
                      <Input type="password" placeholder={editingRecord ? '留空则不修改密码' : '密码（至少6位）'} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="display_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>显示名称</FormLabel>
                    <FormControl>
                      <Input placeholder="可选" {...field} />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="group"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>用户组</FormLabel>
                    <Select
                      value={field.value}
                      onValueChange={field.onChange}
                      disabled={!!isSelf}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="user">普通用户</SelectItem>
                        <SelectItem value="editor">编辑</SelectItem>
                        <SelectItem value="admin">管理员</SelectItem>
                      </SelectContent>
                    </Select>
                    {isSelf && <p className="text-xs text-muted-foreground">不能更改自己的用户组</p>}
                    <FormMessage />
                  </FormItem>
                )}
              />
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>取消</Button>
                <Button type="submit">保存</Button>
              </DialogFooter>
            </form>
          </Form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
