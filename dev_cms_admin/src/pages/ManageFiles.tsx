import { useState, useEffect } from 'react'
import { Card, Table, Button, App, Upload, Modal, Image, Dropdown, Progress, List, Typography, Select, Space } from 'antd'
import { UploadOutlined, DeleteOutlined, MoreOutlined, InboxOutlined, CheckCircleOutlined, CloseCircleOutlined } from '@ant-design/icons'
import type { UploadProps, MenuProps, UploadFile } from 'antd'
import { useAttachments } from '@/hooks'
import { buildPublicUrl, getApiBaseUrl } from '@/utils/api'
import { getAdminToken, checkLoginStatus, getApiPrefix } from '@/utils/token'

const { Dragger } = Upload
const { Text } = Typography
const { Option } = Select

interface UploadFileItem extends UploadFile {
  status?: 'uploading' | 'done' | 'error'
  percent?: number
  errorMessage?: string
}

export default function ManageFiles() {
  const { loading, data, loadAttachments, deleteAttachment } = useAttachments()
  const app = App.useApp()
  const messageApi = app.message
  const modal = app.modal
  const [uploadModalVisible, setUploadModalVisible] = useState(false)
  const [uploadFileList, setUploadFileList] = useState<UploadFileItem[]>([])
  const [sort, setSort] = useState<'new' | 'old'>('new')

  useEffect(() => {
    loadAttachments({ sort })
  }, [sort, loadAttachments])

  const handleDelete = async (id: number) => {
    modal.confirm({
      title: '确认删除',
      content: '确定要删除这个附件吗？',
      onOk: async () => {
        const success = await deleteAttachment(id)
        if (success) {
          loadAttachments({ sort })
        }
      },
    })
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
        if (token) {
          headers['X-API-Token'] = token
        }
      }

      const xhr = new XMLHttpRequest()

      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100)
          setUploadFileList((prev) =>
            prev.map((item) =>
              item.uid === fileItem.uid ? { ...item, percent } : item
            )
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
                  item.uid === fileItem.uid
                    ? { ...item, status: 'done', percent: 100 }
                    : item
                )
              )
              messageApi.success(`${file.name} 上传成功`)
              loadAttachments({ sort })
              return
            } else {
              errorMessage = response.message || '上传失败'
            }
          } else {
            // 尝试解析错误响应
            try {
              const errorResponse = JSON.parse(xhr.responseText)
              errorMessage = errorResponse.message || `服务器错误 (${xhr.status})`
            } catch {
              errorMessage = `服务器错误 (${xhr.status}): ${xhr.statusText || '未知错误'}`
            }
          }
        } catch (parseError) {
          errorMessage = `解析响应失败: ${xhr.statusText || '未知错误'}`
        }

        setUploadFileList((prev) =>
          prev.map((item) =>
            item.uid === fileItem.uid
              ? { ...item, status: 'error', errorMessage }
              : item
          )
        )
        messageApi.error(errorMessage)
      })

      xhr.addEventListener('error', () => {
        const errorMessage = '网络错误，请检查网络连接'
        setUploadFileList((prev) =>
          prev.map((item) =>
            item.uid === fileItem.uid
              ? { ...item, status: 'error', errorMessage }
              : item
          )
        )
        messageApi.error(errorMessage)
      })

      xhr.addEventListener('abort', () => {
        const errorMessage = '上传已取消'
        setUploadFileList((prev) =>
          prev.map((item) =>
            item.uid === fileItem.uid
              ? { ...item, status: 'error', errorMessage }
              : item
          )
        )
        messageApi.warning(errorMessage)
      })

      xhr.open('POST', url)
      Object.keys(headers).forEach((key) => {
        xhr.setRequestHeader(key, headers[key])
      })
      xhr.send(formData)
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : '上传失败'
      setUploadFileList((prev) =>
        prev.map((item) =>
          item.uid === fileItem.uid
            ? { ...item, status: 'error', errorMessage }
            : item
        )
      )
      messageApi.error(errorMessage)
    }
  }

  const uploadProps: UploadProps = {
    name: 'file',
    multiple: true,
    showUploadList: false,
    beforeUpload: (file) => {
      handleUpload(file)
      return false
    },
  }

  const handleUploadModalClose = () => {
    const hasUploading = uploadFileList.some((item) => item.status === 'uploading')
    if (hasUploading) {
      modal.confirm({
        title: '确认关闭',
        content: '仍有文件正在上传，确定要关闭吗？',
        onOk: () => {
          setUploadModalVisible(false)
          setUploadFileList([])
        },
      })
    } else {
      setUploadModalVisible(false)
      setUploadFileList([])
    }
  }

  const columns = [
    {
      title: '预览',
      dataIndex: 'url',
      key: 'preview',
      width: 100,
      fixed: 'left' as const,
      render: (url: string, record: any) => {
        if (record.mime_type?.startsWith('image/')) {
          return (
            <Image
              src={buildPublicUrl(url)}
              alt={record.original_name}
              width={60}
              height={60}
              style={{ objectFit: 'cover' }}
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
      ellipsis: true,
      render: (text: string) => (
        <span title={text} style={{ maxWidth: '300px', display: 'inline-block' }}>
          {text || '-'}
        </span>
      ),
    },
    {
      title: '类型',
      dataIndex: 'mime_type',
      key: 'mime_type',
      width: 120,
      ellipsis: true,
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
      render: (timestamp: number) => {
        if (!timestamp) return '-'
        return new Date(timestamp * 1000).toLocaleString('zh-CN')
      },
    },
    {
      title: '操作',
      key: 'action',
      width: 80,
      fixed: 'right' as const,
      render: (_: any, record: any) => {
        const items: MenuProps['items'] = [
          {
            key: 'delete',
            label: '删除',
            icon: <DeleteOutlined />,
            danger: true,
            onClick: () => handleDelete(record.id),
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
        title="附件管理"
        extra={
          <Space>
            <Select
              value={sort}
              onChange={(value) => setSort(value)}
              style={{ width: 120 }}
            >
              <Option value="new">新到老</Option>
              <Option value="old">老到新</Option>
            </Select>
            <Button
              type="primary"
              icon={<UploadOutlined />}
              onClick={() => setUploadModalVisible(true)}
            >
              上传文件
            </Button>
          </Space>
        }
      >
        <Table
          columns={columns}
          dataSource={data}
          loading={loading}
          rowKey="id"
          scroll={{ x: 800 }}
          pagination={{
            showSizeChanger: true,
            showTotal: (total) => `共 ${total} 条`,
          }}
        />
      </Card>

      <Modal
        title="上传文件"
        open={uploadModalVisible}
        onCancel={handleUploadModalClose}
        footer={null}
        width={600}
      >
        <Dragger {...uploadProps} style={{ marginBottom: 24 }}>
          <p className="ant-upload-drag-icon">
            <InboxOutlined />
          </p>
          <p className="ant-upload-text">点击或拖拽文件到此区域上传</p>
          <p className="ant-upload-hint">支持多文件上传</p>
        </Dragger>

        {uploadFileList.length > 0 && (
          <div>
            <Text strong style={{ marginBottom: 12, display: 'block' }}>
              上传进度
            </Text>
            <List
              dataSource={uploadFileList}
              renderItem={(item) => (
                <List.Item>
                  <div style={{ width: '100%' }}>
                    <div
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        marginBottom: 8,
                      }}
                    >
                      <Text ellipsis style={{ flex: 1, marginRight: 8 }}>
                        {item.name}
                      </Text>
                      {item.status === 'done' && (
                        <CheckCircleOutlined style={{ color: '#52c41a' }} />
                      )}
                      {item.status === 'error' && (
                        <CloseCircleOutlined style={{ color: '#ff4d4f' }} />
                      )}
                    </div>
                    {item.status === 'uploading' && (
                      <Progress
                        percent={item.percent}
                        size="small"
                        status="active"
                      />
                    )}
                    {item.status === 'done' && (
                      <Progress percent={100} size="small" status="success" />
                    )}
                    {item.status === 'error' && (
                      <>
                        <Progress percent={0} size="small" status="exception" />
                        {item.errorMessage && (
                          <Text type="danger" style={{ fontSize: 12, marginTop: 4, display: 'block' }}>
                            {item.errorMessage}
                          </Text>
                        )}
                      </>
                    )}
                  </div>
                </List.Item>
              )}
            />
          </div>
        )}
      </Modal>
    </div>
  )
}

