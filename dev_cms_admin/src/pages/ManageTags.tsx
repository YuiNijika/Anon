import { useState, useEffect } from 'react'
import { Card, Table, Button, App, Space, Modal, Form, Input } from 'antd'
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons'
import { useApiAdmin } from '@/hooks'

export default function ManageTags() {
  const apiAdmin = useApiAdmin()
  const app = App.useApp()
  const messageApi = app.message
  const [form] = Form.useForm()
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<any[]>([])
  const [modalVisible, setModalVisible] = useState(false)
  const [editingRecord, setEditingRecord] = useState<any>(null)

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      setLoading(true)
      const response = await apiAdmin.admin.get('/metas/tags')
      if (response.code === 200) {
        setData(response.data || [])
      } else {
        messageApi.error(response.message || '加载标签列表失败')
      }
    } catch (err) {
      messageApi.error('加载标签列表失败')
    } finally {
      setLoading(false)
    }
  }

  const handleAdd = () => {
    setEditingRecord(null)
    form.resetFields()
    setModalVisible(true)
  }

  const handleEdit = (record: any) => {
    setEditingRecord(record)
    form.setFieldsValue(record)
    setModalVisible(true)
  }

  const handleDelete = async (id: number) => {
    Modal.confirm({
      title: '确认删除',
      content: '确定要删除这个标签吗？',
      onOk: async () => {
        try {
          const baseUrl = import.meta.env.DEV ? '/anon-dev-server' : ''
          const url = `${baseUrl}/anon/cms/admin/metas/tags?id=${id}`
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

  const handleSubmit = async (values: any) => {
    try {
      let response
      if (editingRecord) {
        response = await apiAdmin.admin.put('/metas/tags', {
          id: editingRecord.id,
          ...values,
        })
      } else {
        response = await apiAdmin.admin.post('/metas/tags', values)
      }
      
      if (response.code === 200) {
        messageApi.success(editingRecord ? '更新成功' : '创建成功')
        setModalVisible(false)
        loadData()
      } else {
        messageApi.error(response.message || (editingRecord ? '更新失败' : '创建失败'))
      }
    } catch (err) {
      messageApi.error(editingRecord ? '更新失败' : '创建失败')
    }
  }

  const columns = [
    {
      title: 'ID',
      dataIndex: 'id',
      key: 'id',
      width: 80,
    },
    {
      title: '名称',
      dataIndex: 'name',
      key: 'name',
    },
    {
      title: '别名',
      dataIndex: 'slug',
      key: 'slug',
    },
    {
      title: '描述',
      dataIndex: 'description',
      key: 'description',
    },
    {
      title: '操作',
      key: 'action',
      width: 150,
      render: (_: any, record: any) => (
        <Space>
          <Button
            type="link"
            icon={<EditOutlined />}
            onClick={() => handleEdit(record)}
          >
            编辑
          </Button>
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
        title="标签管理"
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={handleAdd}>
            新增标签
          </Button>
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

      <Modal
        title={editingRecord ? '编辑标签' : '新增标签'}
        open={modalVisible}
        onCancel={() => setModalVisible(false)}
        footer={null}
      >
        <Form form={form} layout="vertical" onFinish={handleSubmit}>
          <Form.Item
            name="name"
            label="名称"
            rules={[{ required: true, message: '请输入标签名称' }]}
          >
            <Input placeholder="标签名称" />
          </Form.Item>

          <Form.Item name="slug" label="别名">
            <Input placeholder="URL 友好的别名（可选）" />
          </Form.Item>

          <Form.Item name="description" label="描述">
            <Input.TextArea rows={3} placeholder="标签描述（可选）" />
          </Form.Item>

          <Form.Item>
            <Space>
              <Button type="primary" htmlType="submit">
                保存
              </Button>
              <Button onClick={() => setModalVisible(false)}>取消</Button>
            </Space>
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}

