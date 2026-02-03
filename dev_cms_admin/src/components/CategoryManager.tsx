import { useState } from 'react'
import { Plus, Edit2, Trash2, Settings, List } from 'lucide-react'
import { useCategories } from '@/hooks'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'

interface Category {
  id: number
  name: string
  slug?: string
  description?: string
}

interface CategoryManagerProps {
  categories: Category[]
  selectedCategoryId: number | null
  onCategoriesChange: (categories: Category[]) => void
  onCategorySelect: (categoryId: number | null) => void
}

interface CategoryFormData {
  name: string
  slug: string
  description: string
}

export default function CategoryManager({
  categories,
  selectedCategoryId,
  onCategoriesChange,
  onCategorySelect,
}: CategoryManagerProps) {
  const { createCategory, updateCategory, deleteCategory, loading } = useCategories()
  const [isManageDialogOpen, setIsManageDialogOpen] = useState(false)
  const [isAddDialogOpen, setIsAddDialogOpen] = useState(false)
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState<Category | null>(null)
  const [formData, setFormData] = useState<CategoryFormData>({
    name: '',
    slug: '',
    description: '',
  })

  const resetForm = () => {
    setFormData({ name: '', slug: '', description: '' })
  }

  const generateSlug = (name: string) => {
    return name
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '')
  }

  const handleNameChange = (name: string) => {
    setFormData(prev => ({
      ...prev,
      name,
      slug: prev.slug || generateSlug(name)
    }))
  }

  const handleAddCategory = async () => {
    if (!formData.name.trim()) {
      return
    }

    const result = await createCategory({
      name: formData.name.trim(),
      slug: formData.slug.trim() || generateSlug(formData.name),
      description: formData.description.trim(),
    })

    if (result) {
      onCategoriesChange([...categories, result])
      resetForm()
      setIsAddDialogOpen(false)
    }
  }

  const handleEditCategory = async () => {
    if (!editingCategory || !formData.name.trim()) {
      return
    }

    const result = await updateCategory({
      id: editingCategory.id,
      name: formData.name.trim(),
      slug: formData.slug.trim() || generateSlug(formData.name),
      description: formData.description.trim(),
    })

    if (result) {
      const updatedCategories = categories.map((cat) =>
        cat.id === editingCategory.id
          ? { ...cat, ...formData }
          : cat
      )
      onCategoriesChange(updatedCategories)
      resetForm()
      setEditingCategory(null)
      setIsEditDialogOpen(false)
    }
  }

  const handleDeleteCategory = async (categoryId: number, categoryName: string) => {
    if (!confirm(`确定要删除分类「${categoryName}」吗？此操作不可恢复。`)) {
      return
    }

    const success = await deleteCategory(categoryId)
    if (success) {
      const updatedCategories = categories.filter((cat) => cat.id !== categoryId)
      onCategoriesChange(updatedCategories)
      
      // 如果删除的是当前选中的分类，则清除选择
      if (selectedCategoryId === categoryId) {
        onCategorySelect(null)
      }
    }
  }

  const openEditDialog = (category: Category) => {
    setEditingCategory(category)
    setFormData({
      name: category.name,
      slug: category.slug || '',
      description: category.description || '',
    })
    setIsEditDialogOpen(true)
  }

  return (
    <>
      {/* 管理分类对话框 */}
      <Dialog open={isManageDialogOpen} onOpenChange={setIsManageDialogOpen}>
        <DialogTrigger asChild>
          <Button variant="outline" size="sm" className="w-full">
            <Settings className="h-4 w-4 mr-1" />
            管理分类
          </Button>
        </DialogTrigger>
        <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <List className="h-5 w-5" />
              分类管理
            </DialogTitle>
          </DialogHeader>
          
          <div className="space-y-4">
            {/* 新增分类按钮 */}
            <div className="flex justify-between items-center pt-2">
              <span className="text-sm text-muted-foreground">
                共 {categories.length} 个分类
              </span>
              <Dialog open={isAddDialogOpen} onOpenChange={setIsAddDialogOpen}>
                <DialogTrigger asChild>
                  <Button size="sm">
                    <Plus className="h-4 w-4 mr-1" />
                    新增分类
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>新增分类</DialogTitle>
                  </DialogHeader>
                  <div className="space-y-4">
                    <div>
                      <Label htmlFor="new-category-name">分类名称 *</Label>
                      <Input
                        id="new-category-name"
                        value={formData.name}
                        onChange={(e) => handleNameChange(e.target.value)}
                        placeholder="请输入分类名称"
                      />
                    </div>
                    <div>
                      <Label htmlFor="new-category-slug">URL 别名</Label>
                      <Input
                        id="new-category-slug"
                        value={formData.slug}
                        onChange={(e) => setFormData(prev => ({ ...prev, slug: e.target.value }))}
                        placeholder="自动生成或手动输入"
                      />
                    </div>
                    <div>
                      <Label htmlFor="new-category-desc">描述</Label>
                      <Textarea
                        id="new-category-desc"
                        value={formData.description}
                        onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                        placeholder="分类描述（可选）"
                        rows={3}
                      />
                    </div>
                    <div className="flex justify-end space-x-2">
                      <Button
                        variant="outline"
                        onClick={() => {
                          setIsAddDialogOpen(false)
                          resetForm()
                        }}
                      >
                        取消
                      </Button>
                      <Button onClick={handleAddCategory} disabled={loading}>
                        {loading ? '创建中...' : '创建'}
                      </Button>
                    </div>
                  </div>
                </DialogContent>
              </Dialog>
            </div>

            {/* 分类列表 */}
            <div className="space-y-3">
              {categories.length === 0 ? (
                <Card>
                  <CardContent className="text-center py-8">
                    <p className="text-muted-foreground">暂无分类，请先创建分类</p>
                  </CardContent>
                </Card>
              ) : (
                categories.map((category) => (
                  <Card key={category.id}>
                    <CardContent className="p-4">
                      <div className="flex items-start justify-between">
                        <div className="flex-1 space-y-2">
                          <div className="flex items-center gap-2">
                            <h3 className="font-medium">{category.name}</h3>
                            {selectedCategoryId === category.id && (
                              <Badge variant="default" className="text-xs">
                                当前选中
                              </Badge>
                            )}
                          </div>
                          {category.slug && (
                            <p className="text-sm text-muted-foreground">
                              别名: {category.slug}
                            </p>
                          )}
                          {category.description && (
                            <p className="text-sm text-muted-foreground line-clamp-2">
                              {category.description}
                            </p>
                          )}
                        </div>
                        <div className="flex items-center space-x-1 ml-4">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => onCategorySelect(category.id)}
                            className={selectedCategoryId === category.id ? "bg-muted" : ""}
                          >
                            选择
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openEditDialog(category)}
                          >
                            <Edit2 className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleDeleteCategory(category.id, category.name)}
                            disabled={loading}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                ))
              )}
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* 编辑分类弹窗 */}
      <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>编辑分类</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label htmlFor="edit-category-name">分类名称 *</Label>
              <Input
                id="edit-category-name"
                value={formData.name}
                onChange={(e) => handleNameChange(e.target.value)}
                placeholder="请输入分类名称"
              />
            </div>
            <div>
              <Label htmlFor="edit-category-slug">URL 别名</Label>
              <Input
                id="edit-category-slug"
                value={formData.slug}
                onChange={(e) => setFormData(prev => ({ ...prev, slug: e.target.value }))}
                placeholder="自动生成或手动输入"
              />
            </div>
            <div>
              <Label htmlFor="edit-category-desc">描述</Label>
              <Textarea
                id="edit-category-desc"
                value={formData.description}
                onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                placeholder="分类描述（可选）"
                rows={3}
              />
            </div>
            <div className="flex justify-end space-x-2">
              <Button
                variant="outline"
                onClick={() => {
                  setIsEditDialogOpen(false)
                  setEditingCategory(null)
                  resetForm()
                }}
              >
                取消
              </Button>
              <Button onClick={handleEditCategory} disabled={loading}>
                {loading ? '更新中...' : '更新'}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}