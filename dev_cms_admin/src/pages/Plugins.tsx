import { useState, useEffect, useRef } from 'react'
import {
  Card,
  Table,
  Tag,
  Space,
  Upload,
  Button,
  Switch,
  App,
  Input,
  Dropdown,
  Empty,
} from 'antd'
import { UploadOutlined, DeleteOutlined, MoreOutlined } from '@ant-design/icons'
import type { MenuProps } from 'antd'
import { useApiAdmin, usePlugins } from '@/hooks'
import { AdminApi, type Plugin } from '@/services/admin'

export default function Plugins() {
  const apiAdmin = useApiAdmin()
  const { uploadPlugin, activatePlugin, deactivatePlugin, deletePlugin } = usePlugins()
  const { message, modal } = App.useApp()

  const [loading, setLoading] = useState(false)
  const [plugins, setPlugins] = useState<Plugin[]>([])
  const [uploading, setUploading] = useState(false)
  const [searchKeyword, setSearchKeyword] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const fetchingRef = useRef(false)

  useEffect(() => {
    loadPlugins()
  }, [apiAdmin])

  useEffect(() => {
    loadPlugins()
  }, [searchKeyword, statusFilter])

  const loadPlugins = async () => {
    if (fetchingRef.current) return

    fetchingRef.current = true
    try {
      setLoading(true)
      const res = await AdminApi.getPlugins(apiAdmin)
      if (res.data) {
        let filteredPlugins = res.data.list || []

        if (searchKeyword) {
          filteredPlugins = filteredPlugins.filter(
            (plugin) =>
              plugin.name.toLowerCase().includes(searchKeyword.toLowerCase()) ||
              (plugin.description && plugin.description.toLowerCase().includes(searchKeyword.toLowerCase()))
          )
        }

        if (statusFilter === 'active') {
          filteredPlugins = filteredPlugins.filter((plugin) => plugin.active)
        } else if (statusFilter === 'inactive') {
          filteredPlugins = filteredPlugins.filter((plugin) => !plugin.active)
        }

        const sortedPlugins = [...filteredPlugins].sort((a, b) => {
          if (a.active && !b.active) return -1
          if (!a.active && b.active) return 1
          return 0
        })

        setPlugins(sortedPlugins)
      }
    } catch (err) {
      message.error('加载插件列表失败')
    } finally {
      setLoading(false)
      fetchingRef.current = false
    }
  }

  const handleUpload = async (file: File) => {
    setUploading(true)
    try {
      await uploadPlugin(file)
      await loadPlugins()
    } finally {
      setUploading(false)
    }
    return false
  }

  const handleToggle = async (plugin: Plugin) => {
    try {
      if (plugin.active) {
        await deactivatePlugin(plugin.slug)
      } else {
        await activatePlugin(plugin.slug)
      }
      await loadPlugins()
    } catch (err) {
      // Error handled in hook
    }
  }

  const handleDelete = (plugin: Plugin) => {
    if (plugin.active) {
      message.warning('请先停用插件再删除')
      return
    }

    modal.confirm({
      title: '确认删除',
      content: `确定要删除插件 "${plugin.name}" 吗？此操作不可恢复。`,
      onOk: async () => {
        await deletePlugin(plugin.slug)
        await loadPlugins()
      },
    })
  }

  const getModeColor = (mode: string) => {
    switch (mode) {
      case 'cms':
        return 'blue'
      case 'api':
        return 'green'
      case 'auto':
        return 'orange'
      default:
        return 'default'
    }
  }

  const columns = [
    {
      title: '插件名称',
      dataIndex: 'name',
      key: 'name',
      ellipsis: true,
      render: (text: string, record: Plugin) => (
        <Space>
          <span title={text}>{text || '-'}</span>
          {record.active && <Tag color="success">已启用</Tag>}
        </Space>
      ),
    },
    {
      title: '描述',
      dataIndex: 'description',
      key: 'description',
      ellipsis: true,
      render: (text: string) => (
        <span title={text} style={{ maxWidth: '300px', display: 'inline-block' }}>
          {text || '-'}
        </span>
      ),
    },
    {
      title: '模式',
      dataIndex: 'mode',
      key: 'mode',
      width: 100,
      render: (mode: string) => (
        <Tag color={getModeColor(mode)}>{mode.toUpperCase()}</Tag>
      ),
    },
    {
      title: '版本',
      dataIndex: 'version',
      key: 'version',
      width: 100,
      render: (version: string) => version || '-',
    },
    {
      title: '作者',
      dataIndex: 'author',
      key: 'author',
      width: 150,
      ellipsis: true,
      render: (author: string) => author || '-',
    },
    {
      title: '状态',
      dataIndex: 'active',
      key: 'active',
      width: 100,
      render: (active: boolean, record: Plugin) => (
        <Switch
          checked={active}
          onChange={() => handleToggle(record)}
          checkedChildren="启用"
          unCheckedChildren="停用"
        />
      ),
    },
    {
      title: '操作',
      key: 'action',
      width: 80,
      fixed: 'right' as const,
      render: (_: any, record: Plugin) => {
        const items: MenuProps['items'] = [
          {
            key: 'delete',
            label: '删除',
            icon: <DeleteOutlined />,
            danger: true,
            disabled: record.active,
            onClick: () => handleDelete(record),
          },
        ]
        return (
          <Dropdown menu={{ items }} trigger={['click']}>
            <Button type="text" icon={<MoreOutlined />} />
          </Dropdown>
        )
      },
    },
  ]

  return (
    <div>
      <Card
        title="插件管理"
        extra={
          <Upload
            accept=".zip"
            showUploadList={false}
            beforeUpload={handleUpload}
            disabled={uploading}
          >
            <Button type="primary" icon={<UploadOutlined />} loading={uploading}>
              上传插件
            </Button>
          </Upload>
        }
      >
        <Space direction="vertical" style={{ width: '100%' }} size="middle">
          <Space wrap style={{ width: '100%' }} size={[8, 8]}>
            <Input.Search
              placeholder="搜索插件名称或描述"
              allowClear
              style={{ width: 300, maxWidth: '100%' }}
              onSearch={(value) => {
                setSearchKeyword(value)
              }}
            />
            <Button.Group>
              <Button
                type={statusFilter === 'all' ? 'primary' : 'default'}
                onClick={() => setStatusFilter('all')}
              >
                全部
              </Button>
              <Button
                type={statusFilter === 'active' ? 'primary' : 'default'}
                onClick={() => setStatusFilter('active')}
              >
                已启用
              </Button>
              <Button
                type={statusFilter === 'inactive' ? 'primary' : 'default'}
                onClick={() => setStatusFilter('inactive')}
              >
                未启用
              </Button>
            </Button.Group>
          </Space>

          <Table
            columns={columns}
            dataSource={plugins}
            loading={loading}
            rowKey="slug"
            scroll={{ x: 800 }}
            locale={{
              emptyText: searchKeyword ? (
                <Empty description="未找到匹配的插件" />
              ) : (
                <Empty description="暂无插件" />
              ),
            }}
            pagination={{
              showSizeChanger: true,
              showTotal: (total) => `共 ${total} 条`,
            }}
          />
        </Space>
      </Card>
    </div>
  )
}
