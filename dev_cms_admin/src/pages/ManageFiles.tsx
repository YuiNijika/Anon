import { useState, useEffect } from 'react'
import { Card, Table, Button, App, Space, Upload, Modal, Image } from 'antd'
import { UploadOutlined, DeleteOutlined, EyeOutlined } from '@ant-design/icons'
import type { UploadProps } from 'antd'
import { useApiAdmin } from '@/hooks'

export default function ManageFiles() {
  const apiAdmin = useApiAdmin()
  const app = App.useApp()
  const messageApi = app.message
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<any[]>([])

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      setLoading(true)
      const response = await apiAdmin.admin.get('/attachments')
      if (response.code === 200) {
        setData(response.data.list || [])
      } else {
        messageApi.error(response.message || '加载附件列表失败')
      }
    } catch (err) {
      messageApi.error('加载附件列表失败')
    } finally {
      setLoading(false)
    }
  }

  const handleDelete = async (id: number) => {
    Modal.confirm({
      title: '确认删除',
      content: '确定要删除这个附件吗？',
      onOk: async () => {
        try {
          const baseUrl = import.meta.env.DEV ? '/anon-dev-server' : ''
          const url = `${baseUrl}/anon/cms/admin/attachments?id=${id}`
          const token = localStorage.getItem('token')
          const headers: HeadersInit = {
            'Content-Type': 'application/json',
          }
          if (token) {
            headers['X-API-Token'] = token
          }
          
          const response = await fetch(url, {
            method: 'DELETE',
            headers,
            credentials: 'include',
          }).then(res => res.json())
          
          if (response.code === 200) {
            messageApi.success('删除成功')
            loadData()
          } else {
            messageApi.error(response.message || '删除失败')
          }
        } catch (err) {
          messageApi.error('删除失败')
        }
      },
    })
  }

  const uploadProps: UploadProps = {
    name: 'file',
    customRequest: async (options) => {
      const { file, onSuccess, onError } = options
      
      try {
        const formData = new FormData()
        formData.append('file', file as File)
        
        const baseUrl = import.meta.env.DEV ? '/anon-dev-server' : ''
        const url = `${baseUrl}/anon/cms/admin/attachments`
        const token = localStorage.getItem('token')
        const headers: HeadersInit = {}
        if (token) {
          headers['X-API-Token'] = token
        }
        
        const response = await fetch(url, {
          method: 'POST',
          headers,
          body: formData,
          credentials: 'include',
        }).then(res => res.json())
        
        if (response.code === 200) {
          onSuccess?.(response.data)
          messageApi.success(`${(file as File).name} 上传成功`)
          loadData()
        } else {
          onError?.(new Error(response.message || '上传失败'))
          messageApi.error(response.message || '上传失败')
        }
      } catch (err) {
        onError?.(err as Error)
        messageApi.error('上传失败')
      }
    },
  }

  const columns = [
    {
      title: '预览',
      dataIndex: 'url',
      key: 'preview',
      width: 100,
      render: (url: string, record: any) => {
        if (record.mime_type?.startsWith('image/')) {
          return (
            <Image
              src={url}
              alt={record.original_name}
              width={60}
              height={60}
              style={{ objectFit: 'cover' }}
              preview={{
                mask: <EyeOutlined />,
              }}
            />
          )
        }
        return <span>-</span>
      },
    },
    {
      title: '文件名',
      dataIndex: 'original_name',
      key: 'original_name',
    },
    {
      title: '类型',
      dataIndex: 'mime_type',
      key: 'mime_type',
      width: 120,
    },
    {
      title: '大小',
      dataIndex: 'file_size',
      key: 'file_size',
      width: 100,
      render: (size: number) => {
        if (!size) return '-'
        if (size < 1024) return `${size} B`
        if (size < 1024 * 1024) return `${(size / 1024).toFixed(2)} KB`
        return `${(size / (1024 * 1024)).toFixed(2)} MB`
      },
    },
    {
      title: '上传时间',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 180,
    },
    {
      title: '操作',
      key: 'action',
      width: 120,
      render: (_: any, record: any) => (
        <Space>
          <Button
            type="link"
            danger
            icon={<DeleteOutlined />}
            onClick={() => handleDelete(record.id)}
          >
            删除
          </Button>
        </Space>
      ),
    },
  ]

  return (
    <div>
      <Card
        title="附件管理"
        extra={
          <Upload {...uploadProps}>
            <Button type="primary" icon={<UploadOutlined />}>
              上传文件
            </Button>
          </Upload>
        }
      >
        <Table
          columns={columns}
          dataSource={data}
          loading={loading}
          rowKey="id"
          pagination={{
            showSizeChanger: true,
            showTotal: (total) => `共 ${total} 条`,
          }}
        />
      </Card>
    </div>
  )
}

