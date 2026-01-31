import { useState, useEffect, useCallback } from 'react'
import { Card, Form, Input, Button, App, Space, Select, Radio, Divider } from 'antd'
import { EditOutlined, FileOutlined, PictureOutlined } from '@ant-design/icons'
import { useSearchParams } from 'react-router-dom'
import MDEditor from '@uiw/react-md-editor'
import '@uiw/react-md-editor/markdown-editor.css'
import { useTheme, useApiAdmin } from '@/hooks'
import MediaLibrary from '@/components/MediaLibrary'
import { buildPublicUrl } from '@/utils/api'

type ContentType = 'post' | 'page'

export default function Write() {
  const app = App.useApp()
  const message = app.message
  const apiAdmin = useApiAdmin()
  const { isDark } = useTheme()
  const [searchParams] = useSearchParams()
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(false)
  const [contentType, setContentType] = useState<ContentType>('post')
  const [content, setContent] = useState('')
  const [categories, setCategories] = useState<any[]>([])
  const [tags, setTags] = useState<any[]>([])
  const [mediaLibraryOpen, setMediaLibraryOpen] = useState(false)
  const [postId, setPostId] = useState<number | null>(null)
  const [loadingPost, setLoadingPost] = useState(false)

  // 加载分类和标签
  const loadCategories = useCallback(async () => {
    try {
      const response = await apiAdmin.admin.get('/metas/categories')
      if (response.code === 200) {
        setCategories(response.data || [])
      }
    } catch (err) {
      console.error('加载分类失败:', err)
    }
  }, [apiAdmin])

  const loadTags = useCallback(async () => {
    try {
      const response = await apiAdmin.admin.get('/metas/tags')
      if (response.code === 200) {
        setTags(response.data || [])
      }
    } catch (err) {
      console.error('加载标签失败:', err)
    }
  }, [apiAdmin])

  useEffect(() => {
    loadCategories()
    loadTags()
  }, [loadCategories, loadTags])

  /**
   * 加载文章数据（编辑模式）
   */
  const loadPost = useCallback(async (id: number) => {
    try {
      setLoadingPost(true)
      const response = await apiAdmin.admin.get('/posts', { id })
      if (response.code === 200) {
        const post = response.data
        setPostId(post.id)
        setContentType(post.type as ContentType)
        setContent(post.content || '')

        form.setFieldsValue({
          title: post.title,
          slug: post.slug,
          status: post.status,
          category: post.category,
          tags: post.tags || [],
          type: post.type,
        })
      } else {
        message.error('加载文章失败')
      }
    } catch (err) {
      message.error('加载文章失败')
    } finally {
      setLoadingPost(false)
    }
  }, [apiAdmin, form, message])

  /**
   * 检查 URL 参数，如果是编辑模式则加载文章
   */
  useEffect(() => {
    const id = searchParams.get('id')
    if (id) {
      const postIdNum = parseInt(id, 10)
      if (!isNaN(postIdNum) && postIdNum > 0) {
        loadPost(postIdNum)
      }
    } else {
      // 新建模式，重置表单
      setPostId(null)
      form.resetFields()
      setContent('')
      setContentType('post')
    }
  }, [searchParams, loadPost, form])

  // 同步编辑器值到表单
  useEffect(() => {
    const currentContent = form.getFieldValue('content') || ''
    if (currentContent !== content) {
      setContent(currentContent)
    }
  }, [form.getFieldValue('content')])

  const handleSubmit = async (values: any) => {
    try {
      setLoading(true)
      const submitData = {
        ...values,
        type: contentType,
        content: content,
      }

      let response
      if (postId) {
        // 更新文章
        response = await apiAdmin.admin.put('/posts', {
          id: postId,
          ...submitData,
        })
      } else {
        // 创建文章
        response = await apiAdmin.admin.post('/posts', submitData)
      }

      if (response.code === 200) {
        message.success(`${postId ? '更新' : '创建'}${contentType === 'post' ? '文章' : '页面'}成功`)
        if (!postId) {
          // 新建成功后，跳转到编辑模式
          const newPostId = response.data.id
          setPostId(newPostId)
          window.history.replaceState({}, '', `/write?id=${newPostId}`)
        }
      } else {
        message.error(response.message || `${postId ? '更新' : '创建'}失败`)
      }
    } catch (err) {
      message.error(`${postId ? '更新' : '创建'}${contentType === 'post' ? '文章' : '页面'}失败`)
    } finally {
      setLoading(false)
    }
  }

  const handleTypeChange = (type: ContentType) => {
    setContentType(type)
    form.setFieldValue('type', type)
    form.resetFields(['title', 'slug', 'status', 'category', 'tags', 'content'])
    form.setFieldValue('type', type)
  }

  const handleMediaSelect = (attachmentOrList: any) => {
    const list = Array.isArray(attachmentOrList) ? attachmentOrList : [attachmentOrList]
    const currentContent = content || ''

    const blocks = list
      .filter(Boolean)
      .map((attachment) => {
        const url = attachment.insert_url || attachment.url
        return `![${attachment.original_name}](${buildPublicUrl(url)})`
      })

    const newContent = currentContent + (currentContent ? '\n\n' : '') + blocks.join('\n\n')
    setContent(newContent)
    form.setFieldValue('content', newContent)
  }

  return (
    <div style={{ display: 'flex', gap: '20px' }}>
      {/* 左侧编辑区 - 9 栏 */}
      <div style={{ flex: '0 0 75%', width: '75%' }}>
        <Card>
          <Form form={form} layout="vertical" onFinish={handleSubmit}>
            <Form.Item
              name="title"
              label="标题"
              rules={[{ required: true, message: `请输入${contentType === 'post' ? '文章' : '页面'}标题` }]}
            >
              <Input
                placeholder={`${contentType === 'post' ? '文章' : '页面'}标题`}
                style={{ fontSize: '20px', fontWeight: 600 }}
              />
            </Form.Item>

            <Form.Item
              name="content"
              label={
                <Space style={{ width: '100%', justifyContent: 'space-between' }}>
                  <span>内容</span>
                  <Button
                    type="link"
                    icon={<PictureOutlined />}
                    onClick={() => setMediaLibraryOpen(true)}
                    size="small"
                  >
                    插入媒体
                  </Button>
                </Space>
              }
            >
              <div data-color-mode={isDark ? 'dark' : 'light'}>
                <MDEditor
                  value={content}
                  onChange={(value?: string) => {
                    const newValue = value || ''
                    setContent(newValue)
                    form.setFieldValue('content', newValue)
                  }}
                  preview="edit"
                  hideToolbar={false}
                  height={600}
                />
              </div>
            </Form.Item>
          </Form>
        </Card>
      </div>

      {/* 右侧 Meta 信息区 - 3 栏 */}
      <div style={{ flex: '0 0 25%', width: '25%' }}>
        <Card size="small">
          <Form form={form} layout="vertical" onFinish={handleSubmit}>
            {/* 内容类型 */}
            <Form.Item label="内容类型">
              <Radio.Group
                value={contentType}
                onChange={(e) => handleTypeChange(e.target.value)}
                disabled={!!postId}
                style={{ width: '100%' }}
              >
                <Space direction="vertical" style={{ width: '100%' }}>
                  <Radio.Button value="post" style={{ width: '100%', textAlign: 'center' }}>
                    <Space>
                      <EditOutlined />
                      <span>文章</span>
                    </Space>
                  </Radio.Button>
                  <Radio.Button value="page" style={{ width: '100%', textAlign: 'center' }}>
                    <Space>
                      <FileOutlined />
                      <span>页面</span>
                    </Space>
                  </Radio.Button>
                </Space>
              </Radio.Group>
            </Form.Item>

            <Divider style={{ margin: '16px 0' }} />

            {/* 发布 */}
            <Form.Item name="status" label="状态" initialValue="publish">
              <Select>
                <Select.Option value="draft">草稿</Select.Option>
                <Select.Option value="publish">发布</Select.Option>
              </Select>
            </Form.Item>

            <Form.Item>
              <Button type="primary" block htmlType="submit" loading={loading || loadingPost}>
                {postId ? '更新' : '立即发布'}
              </Button>
            </Form.Item>

            <Divider style={{ margin: '16px 0' }} />

            {/* 分类和标签（仅文章） */}
            {contentType === 'post' && (
              <>
                <Form.Item name="category" label="分类">
                  <Select
                    placeholder="选择分类"
                    allowClear
                    showSearch
                    filterOption={(input, option) =>
                      (option?.label ?? '').toLowerCase().includes(input.toLowerCase())
                    }
                    options={categories.map(cat => ({
                      value: cat.id,
                      label: cat.name,
                    }))}
                  />
                </Form.Item>

                <Form.Item name="tags" label="标签">
                  <Select
                    mode="tags"
                    placeholder="输入标签后按回车"
                    allowClear
                    showSearch
                    filterOption={(input, option) =>
                      (option?.label ?? '').toLowerCase().includes(input.toLowerCase())
                    }
                    options={tags.map(tag => ({
                      value: tag.name,
                      label: tag.name,
                    }))}
                    onSearch={async (value) => {
                      if (value && !tags.some(t => t.name === value)) {
                        // 可以在这里实现标签自动创建逻辑
                      }
                    }}
                  />
                </Form.Item>

                <Divider style={{ margin: '16px 0' }} />
              </>
            )}

            {/* 页面属性 */}
            <Form.Item name="slug" label="别名">
              <Input placeholder="URL 友好的别名" />
            </Form.Item>
          </Form>
        </Card>
      </div>

      {/* 媒体库 */}
      <MediaLibrary
        open={mediaLibraryOpen}
        onClose={() => setMediaLibraryOpen(false)}
        onSelect={handleMediaSelect}
      />
    </div>
  )
}

