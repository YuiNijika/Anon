import { useState, useEffect, useRef } from 'react'
import { Form, Input, Button, App, Space, Divider, Typography, Select, Radio, Alert } from 'antd'
import { useApiAdmin } from '@/hooks'
import { AdminApi, type BasicSettings } from '@/services/admin'

const { TextArea } = Input
const { Title, Text } = Typography

// 路径预设风格
const PATH_STYLES = [
  {
    value: 'default',
    label: '默认风格',
    paths: {
      post: '/archives/{id}/',
      page: '/{slug}.html',
      category: '/category/{slug}/',
      tag: '/tag/{slug}/',
    },
  },
  {
    value: 'wordpress',
    label: 'WordPress 风格',
    paths: {
      post: '/archives/{slug}.html',
      page: '/{slug}.html',
      category: '/category/{slug}/',
      tag: '/tag/{slug}/',
    },
  },
  {
    value: 'date',
    label: '按日期归档',
    paths: {
      post: '/{year}/{month}/{day}/{slug}.html',
      page: '/{slug}.html',
      category: '/category/{slug}/',
      tag: '/tag/{slug}/',
    },
  },
  {
    value: 'category',
    label: '按分类归档',
    paths: {
      post: '/{category}/{slug}.html',
      page: '/{slug}.html',
      category: '/category/{slug}/',
      tag: '/tag/{slug}/',
    },
  },
  {
    value: 'custom',
    label: '个性化定义',
    paths: {
      post: '',
      page: '',
      category: '',
      tag: '',
    },
  },
]

// 可用参数说明
const POST_PARAMS = ['{id}', '{slug}', '{category}', '{directory}', '{year}', '{month}', '{day}']
const PAGE_PARAMS = ['{id}', '{slug}', '{directory}']
const CATEGORY_PARAMS = ['{id}', '{slug}', '{directory}']
const TAG_PARAMS = ['{id}', '{slug}']

export default function SettingsBasic() {
  const apiAdmin = useApiAdmin()
  const [form] = Form.useForm()
  const app = App.useApp()
  const message = app.message
  const [loading, setLoading] = useState(false)
  const fetchingRef = useRef(false)
  const [pathStyle, setPathStyle] = useState<string>('default')

  useEffect(() => {
    loadSettings()
  }, [apiAdmin])

  // 将逗号分隔的字符串转换为数组
  const stringToArray = (str: string | undefined): string[] => {
    if (!str) return []
    return str.split(',').map((k: string) => k.trim()).filter((k: string) => k)
  }

  // 将数组转换为逗号分隔的字符串
  const arrayToString = (arr: string[] | string | undefined): string => {
    if (Array.isArray(arr)) {
      return arr.filter((k: string) => k && k.trim()).join(',')
    }
    return arr || ''
  }

  const loadSettings = async () => {
    if (fetchingRef.current) return

    fetchingRef.current = true
    try {
      setLoading(true)

      // 加载基本设置（包含页面设置）
      const basicRes = await AdminApi.getBasicSettings(apiAdmin)

      if (basicRes.data) {
        const uploadTypes = basicRes.data.upload_allowed_types || {}

        const formData: any = {
          ...basicRes.data,
          keywords: stringToArray(basicRes.data.keywords),
          upload_allowed_types: {
            image: stringToArray(uploadTypes.image),
            media: stringToArray(uploadTypes.media),
            document: stringToArray(uploadTypes.document),
            other: stringToArray(uploadTypes.other),
          },
        }

        // 加载页面路径设置
        const routes = basicRes.data.routes || {}
        const postPath = Object.entries(routes).find(([_, template]) => template === 'post')?.[0] || '/archives/{id}/'
        const pagePath = Object.entries(routes).find(([_, template]) => template === 'page')?.[0] || '/{slug}.html'
        const categoryPath = Object.entries(routes).find(([_, template]) => template === 'category')?.[0] || '/category/{slug}/'
        const tagPath = Object.entries(routes).find(([_, template]) => template === 'tag')?.[0] || '/tag/{slug}/'

        // 检测路径风格
        const detectedStyle =
          PATH_STYLES.find(
            (style) =>
              style.paths.post === postPath &&
              style.paths.page === pagePath &&
              style.paths.category === categoryPath &&
              style.paths.tag === tagPath
          )?.value || 'custom'
        setPathStyle(detectedStyle)

        formData.postPath = postPath
        formData.postPathStyle = detectedStyle
        formData.pagePath = pagePath
        formData.categoryPath = categoryPath
        formData.tagPath = tagPath

        form.setFieldsValue(formData)
      }
    } catch (err) {
      message.error('加载设置失败')
    } finally {
      setLoading(false)
      fetchingRef.current = false
    }
  }

  const handlePathStyleChange = (style: string) => {
    setPathStyle(style)
    const selectedStyle = PATH_STYLES.find((s) => s.value === style)
    if (selectedStyle && selectedStyle.paths) {
      // 更新所有路径，保持风格一致
      form.setFieldsValue({
        postPath: selectedStyle.paths.post,
        pagePath: selectedStyle.paths.page,
        categoryPath: selectedStyle.paths.category,
        tagPath: selectedStyle.paths.tag,
      })
    }
  }

  const handleSubmit = async (values: any) => {
    try {
      setLoading(true)

      // 保存基本设置（包含页面设置）
      const uploadTypes = values.upload_allowed_types || {}
      
      // 构建路由规则
      const routes: Record<string, string> = {}
      if (values.postPath && values.postPath.trim()) {
        routes[values.postPath.trim()] = 'post'
      }
      if (values.pagePath && values.pagePath.trim()) {
        routes[values.pagePath.trim()] = 'page'
      }
      if (values.categoryPath && values.categoryPath.trim()) {
        routes[values.categoryPath.trim()] = 'category'
      }
      if (values.tagPath && values.tagPath.trim()) {
        routes[values.tagPath.trim()] = 'tag'
      }
      
      const basicData: BasicSettings = {
        title: values.title || '',
        subtitle: values.subtitle || 'Powered by AnonEcho',
        description: values.description || '',
        keywords: arrayToString(values.keywords),
        upload_allowed_types: {
          image: arrayToString(uploadTypes.image),
          media: arrayToString(uploadTypes.media),
          document: arrayToString(uploadTypes.document),
          other: arrayToString(uploadTypes.other),
        },
        routes: routes,
      }

      await AdminApi.updateBasicSettings(apiAdmin, basicData)

      message.success('设置已保存')
      await loadSettings()
    } catch (err) {
      message.error('保存设置失败')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <Form form={form} layout="vertical" onFinish={handleSubmit} preserve={false}>
        <Title level={4}>站点信息</Title>
        <Form.Item
          name="title"
          label="站点标题"
          rules={[{ required: true, message: '请输入站点标题' }]}
        >
          <Input placeholder="站点名称" />
        </Form.Item>

        <Form.Item
          name="subtitle"
          label="站点副标题"
          extra="如果为空则不显示"
        >
          <Input placeholder="Powered by AnonEcho" />
        </Form.Item>

        <Form.Item name="description" label="站点描述">
          <TextArea rows={3} placeholder="用简洁的文字描述您的站点" />
        </Form.Item>

        <Form.Item
          name="keywords"
          label="关键词"
          extra="输入关键词后按回车添加，点击标签上的 × 删除"
        >
          <Select
            mode="tags"
            placeholder="输入关键词后按回车"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
          />
        </Form.Item>

        <Divider />

        <Title level={4}>文件上传设置</Title>
        <div style={{ marginBottom: '16px' }}>
          <div style={{ fontSize: '13px', color: '#8c8c8c' }}>
            输入文件扩展名后按回车添加，点击标签上的 × 删除，也可以从下拉列表中选择常见格式
          </div>
        </div>

        <Form.Item
          name={['upload_allowed_types', 'image']}
          label="图片"
          extra="常见的图片格式"
        >
          <Select
            mode="tags"
            placeholder="输入或选择图片格式，如：jpg, png"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
            options={[
              { value: 'gif', label: 'gif' },
              { value: 'jpg', label: 'jpg' },
              { value: 'jpeg', label: 'jpeg' },
              { value: 'png', label: 'png' },
              { value: 'webp', label: 'webp' },
              { value: 'svg', label: 'svg' },
              { value: 'bmp', label: 'bmp' },
              { value: 'ico', label: 'ico' },
              { value: 'tiff', label: 'tiff' },
              { value: 'avif', label: 'avif' },
            ]}
          />
        </Form.Item>

        <Form.Item
          name={['upload_allowed_types', 'media']}
          label="媒体"
          extra="常见的音频和视频格式"
        >
          <Select
            mode="tags"
            placeholder="输入或选择媒体格式，如：mp3, mp4"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
            options={[
              { value: 'mp3', label: 'mp3' },
              { value: 'mp4', label: 'mp4' },
              { value: 'mov', label: 'mov' },
              { value: 'wmv', label: 'wmv' },
              { value: 'wma', label: 'wma' },
              { value: 'rmvb', label: 'rmvb' },
              { value: 'rm', label: 'rm' },
              { value: 'avi', label: 'avi' },
              { value: 'flv', label: 'flv' },
              { value: 'ogg', label: 'ogg' },
              { value: 'oga', label: 'oga' },
              { value: 'ogv', label: 'ogv' },
              { value: 'mkv', label: 'mkv' },
              { value: 'webm', label: 'webm' },
              { value: 'wav', label: 'wav' },
              { value: 'aac', label: 'aac' },
              { value: 'm4a', label: 'm4a' },
              { value: '3gp', label: '3gp' },
            ]}
          />
        </Form.Item>

        <Form.Item
          name={['upload_allowed_types', 'document']}
          label="文档"
          extra="常见的文档和表格格式"
        >
          <Select
            mode="tags"
            placeholder="输入或选择文档格式，如：pdf, doc"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
            options={[
              { value: 'pdf', label: 'pdf' },
              { value: 'doc', label: 'doc' },
              { value: 'docx', label: 'docx' },
              { value: 'xls', label: 'xls' },
              { value: 'xlsx', label: 'xlsx' },
              { value: 'ppt', label: 'ppt' },
              { value: 'pptx', label: 'pptx' },
              { value: 'txt', label: 'txt' },
              { value: 'rtf', label: 'rtf' },
              { value: 'odt', label: 'odt' },
              { value: 'ods', label: 'ods' },
              { value: 'odp', label: 'odp' },
            ]}
          />
        </Form.Item>

        <Form.Item
          name={['upload_allowed_types', 'other']}
          label="其他"
          extra="其他文件格式，如压缩包、安装包等"
        >
          <Select
            mode="tags"
            placeholder="输入或选择其他格式，如：zip, rar"
            tokenSeparators={[',']}
            style={{ width: '100%' }}
            allowClear
            options={[
              { value: 'zip', label: 'zip' },
              { value: 'rar', label: 'rar' },
              { value: '7z', label: '7z' },
              { value: 'tar', label: 'tar' },
              { value: 'gz', label: 'gz' },
              { value: 'bz2', label: 'bz2' },
              { value: 'exe', label: 'exe' },
              { value: 'dmg', label: 'dmg' },
              { value: 'iso', label: 'iso' },
              { value: 'apk', label: 'apk' },
              { value: 'ipa', label: 'ipa' },
            ]}
          />
        </Form.Item>

        <Divider />

        {/* 链接设置 */}
        <Title level={4}>链接设置</Title>

        <Alert
          message="提示"
          description="一旦你选择了某种链接风格请不要轻易修改它，这可能会影响搜索引擎收录和外部链接。"
          type="warning"
          showIcon
          style={{ marginBottom: '24px' }}
        />

        {/* 文章路径设置 */}
        <Title level={5} style={{ marginTop: '24px', marginBottom: '16px' }}>自定义文章路径</Title>
        <div style={{ marginBottom: '16px' }}>
          <Text type="secondary" style={{ fontSize: '13px' }}>
            选择一种合适的文章静态路径风格，使得你的网站链接更加友好。
          </Text>
        </div>

        <Form.Item name="postPathStyle" label="路径风格">
          <Radio.Group
            value={pathStyle}
            onChange={(e) => handlePathStyleChange(e.target.value)}
            options={PATH_STYLES.map((style) => ({
              label: style.label,
              value: style.value,
            }))}
          />
        </Form.Item>

        <Form.Item
          name="postPath"
          label="文章路径"
          rules={[
            { required: true, message: '请输入文章路径' },
            { pattern: /^\/.*/, message: '路径必须以 / 开头' },
            {
              validator: (_, value) => {
                if (!value) return Promise.resolve()
                const hasParam = POST_PARAMS.some((param) => value.includes(param))
                if (!hasParam) {
                  return Promise.reject(new Error('路径中至少包含一个可用参数'))
                }
                return Promise.resolve()
              },
            },
          ]}
          extra={
            <div style={{ fontSize: '12px', color: '#8c8c8c', marginTop: '4px' }}>
              可用参数: {POST_PARAMS.join(', ')}
            </div>
          }
        >
          <Input placeholder="/archives/{id}/" />
        </Form.Item>

        {/* 独立页面路径设置 */}
        <Form.Item
          name="pagePath"
          label="页面路径"
          rules={[
            { required: true, message: '请输入页面路径' },
            { pattern: /^\/.*/, message: '路径必须以 / 开头' },
            {
              validator: (_, value) => {
                if (!value) return Promise.resolve()
                const hasParam = PAGE_PARAMS.some((param) => value.includes(param))
                if (!hasParam) {
                  return Promise.reject(new Error('路径中至少包含一个可用参数'))
                }
                return Promise.resolve()
              },
            },
          ]}
          extra={
            <div style={{ fontSize: '12px', color: '#8c8c8c', marginTop: '4px' }}>
              可用参数: {PAGE_PARAMS.join(', ')}
            </div>
          }
        >
          <Input placeholder="/{slug}.html" />
        </Form.Item>

        {/* 分类路径设置 */}
        <Form.Item
          name="categoryPath"
          label="分类路径"
          rules={[
            { required: true, message: '请输入分类路径' },
            { pattern: /^\/.*/, message: '路径必须以 / 开头' },
            {
              validator: (_, value) => {
                if (!value) return Promise.resolve()
                const hasParam = CATEGORY_PARAMS.some((param) => value.includes(param))
                if (!hasParam) {
                  return Promise.reject(new Error('路径中至少包含一个可用参数'))
                }
                return Promise.resolve()
              },
            },
          ]}
          extra={
            <div style={{ fontSize: '12px', color: '#8c8c8c', marginTop: '4px' }}>
              可用参数: {CATEGORY_PARAMS.join(', ')}
            </div>
          }
        >
          <Input placeholder="/category/{slug}/" />
        </Form.Item>

        {/* 标签路径设置 */}
        <Form.Item
          name="tagPath"
          label="标签路径"
          rules={[
            { required: true, message: '请输入标签路径' },
            { pattern: /^\/.*/, message: '路径必须以 / 开头' },
            {
              validator: (_, value) => {
                if (!value) return Promise.resolve()
                const hasParam = TAG_PARAMS.some((param) => value.includes(param))
                if (!hasParam) {
                  return Promise.reject(new Error('路径中至少包含一个可用参数'))
                }
                return Promise.resolve()
              },
            },
          ]}
          extra={
            <div style={{ fontSize: '12px', color: '#8c8c8c', marginTop: '4px' }}>
              可用参数: {TAG_PARAMS.join(', ')}
            </div>
          }
        >
          <Input placeholder="/tag/{slug}/" />
        </Form.Item>

        <Form.Item>
          <Space>
            <Button type="primary" htmlType="submit" loading={loading}>
              保存更改
            </Button>
            <Button onClick={() => form.resetFields()}>重置</Button>
          </Space>
        </Form.Item>
      </Form>
    </div>
  )
}

