import { useEffect, useState } from 'react'
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
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Plus, Pencil, Trash2, MoreHorizontal } from 'lucide-react'
import { useCategories } from '@/hooks'
import type { Category } from '@/services/admin'

const categorySchema = z.object({
  name: z.string().min(1, '请输入名称'),
  slug: z.string().min(1, '请输入别名'),
  description: z.string().optional(),
})

type CategoryFormValues = z.infer<typeof categorySchema>

export default function ManageCategories() {
  const { loading, data, loadCategories, createCategory, updateCategory, deleteCategory } = useCategories()
  const [modalOpen, setModalOpen] = useState(false)
  const [editingRecord, setEditingRecord] = useState<Category | null>(null)
  const form = useForm<CategoryFormValues>({
    resolver: zodResolver(categorySchema),
    defaultValues: { name: '', slug: '', description: '' },
  })

  useEffect(() => {
    loadCategories()
  }, [loadCategories])

  const handleAdd = () => {
    setEditingRecord(null)
    form.reset({ name: '', slug: '', description: '' })
    setModalOpen(true)
  }

  const handleEdit = (record: Category) => {
    setEditingRecord(record)
    form.reset({ name: record.name, slug: record.slug, description: record.description ?? '' })
    setModalOpen(true)
  }

  const handleDelete = async (id: number) => {
    if (!window.confirm('确定要删除这个分类吗？')) return
    const success = await deleteCategory(id)
    if (success) loadCategories()
  }

  const handleSubmit = async (values: CategoryFormValues) => {
    if (editingRecord) {
      const result = await updateCategory({ id: editingRecord.id, ...values })
      if (result) {
        setModalOpen(false)
        loadCategories()
      }
    } else {
      const result = await createCategory(values)
      if (result) {
        setModalOpen(false)
        loadCategories()
      }
    }
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>分类管理</CardTitle>
          <Button onClick={handleAdd}>
            <Plus className="mr-2 h-4 w-4" />
            新增分类
          </Button>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="py-12 text-center text-muted-foreground">加载中...</div>
          ) : !data?.length ? (
            <div className="py-12 text-center text-muted-foreground">暂无分类</div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-[80px]">ID</TableHead>
                    <TableHead>名称</TableHead>
                    <TableHead>别名</TableHead>
                    <TableHead>描述</TableHead>
                    <TableHead className="w-[80px]">操作</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>{row.id}</TableCell>
                      <TableCell className="max-w-[200px] truncate">{row.name}</TableCell>
                      <TableCell className="max-w-[150px] truncate">{row.slug}</TableCell>
                      <TableCell className="max-w-[200px] truncate text-muted-foreground">{row.description ?? '-'}</TableCell>
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

      <Dialog open={modalOpen} onOpenChange={setModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingRecord ? '编辑分类' : '新增分类'}</DialogTitle>
          </DialogHeader>
          <Form {...form}>
            <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>名称</FormLabel>
                    <FormControl>
                      <Input placeholder="分类名称" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="slug"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>别名</FormLabel>
                    <FormControl>
                      <Input placeholder="url-slug" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="description"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>描述</FormLabel>
                    <FormControl>
                      <Input placeholder="可选" {...field} />
                    </FormControl>
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
