import { useState, useEffect } from 'react'
import { Card, Table, Button, App, Space, Modal, Form, Input, Select, Avatar, Dropdown } from 'antd'
import { PlusOutlined, EditOutlined, DeleteOutlined, MoreOutlined, UserOutlined } from '@ant-design/icons'
import type { MenuProps } from 'antd'
import { useApiAdmin } from '@/hooks'
import { getApiBaseUrl } from '@/utils/api'
import { getAdminToken, checkLoginStatus, getApiPrefix } from '@/utils/token'

const { Option } = Select

export default function ManageUsers() {
    const apiAdmin = useApiAdmin()
    const app = App.useApp()
    const messageApi = app.message
    const modal = app.modal
    const [form] = Form.useForm()
    const [loading, setLoading] = useState(false)
    const [data, setData] = useState<any[]>([])
    const [total, setTotal] = useState(0)
    const [page, setPage] = useState(1)
    const [pageSize, setPageSize] = useState(20)
    const [searchKeyword, setSearchKeyword] = useState('')
    const [filterGroup, setFilterGroup] = useState<string>('')
    const [modalVisible, setModalVisible] = useState(false)
    const [editingRecord, setEditingRecord] = useState<any>(null)

    useEffect(() => {
        loadData()
    }, [page, pageSize, searchKeyword, filterGroup])

    const loadData = async () => {
        try {
            setLoading(true)
            const params: any = {
                page,
                page_size: pageSize,
            }
            if (searchKeyword) {
                params.search = searchKeyword
            }
            if (filterGroup) {
                params.group = filterGroup
            }
            const response = await apiAdmin.admin.get('/users', params)
            if (response.code === 200) {
                setData(response.data.list || [])
                setTotal(response.data.total || 0)
            } else {
                messageApi.error(response.message || '加载用户列表失败')
            }
        } catch (err) {
            messageApi.error('加载用户列表失败')
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
        form.setFieldsValue({
            ...record,
            password: undefined,
        })
        setModalVisible(true)
    }

    const handleDelete = async (uid: number) => {
        modal.confirm({
            title: '确认删除',
            content: '确定要删除这个用户吗？此操作不可恢复。',
            onOk: async () => {
                try {
                    const baseUrl = getApiBaseUrl()
                    const apiPrefix = await getApiPrefix()
                    const prefix = apiPrefix || '/anon'
                    const url = `${baseUrl}${prefix}/cms/admin/users?uid=${uid}`
                    const isLoggedIn = await checkLoginStatus()
                    const headers: HeadersInit = {
                        'Content-Type': 'application/json',
                    }
                    if (isLoggedIn) {
                        const token = await getAdminToken()
                        if (token) {
                            headers['X-API-Token'] = token
                        }
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
                const updateData: any = {
                    uid: editingRecord.uid,
                    name: values.name,
                    email: values.email,
                    display_name: values.display_name,
                    group: values.group,
                    avatar: values.avatar,
                }
                if (values.password) {
                    updateData.password = values.password
                }
                response = await apiAdmin.admin.put('/users', updateData)
            } else {
                response = await apiAdmin.admin.post('/users', values)
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
            dataIndex: 'uid',
            key: 'uid',
            width: 80,
        },
        {
            title: '头像',
            dataIndex: 'avatar',
            key: 'avatar',
            width: 80,
            render: (avatar: string) => (
                <Avatar src={avatar} icon={<UserOutlined />} />
            ),
        },
        {
            title: '用户名',
            dataIndex: 'name',
            key: 'name',
            ellipsis: true,
            width: 150,
        },
        {
            title: '显示名称',
            dataIndex: 'display_name',
            key: 'display_name',
            ellipsis: true,
            width: 150,
            render: (text: string) => text || '-',
        },
        {
            title: '邮箱',
            dataIndex: 'email',
            key: 'email',
            ellipsis: true,
            width: 200,
        },
        {
            title: '用户组',
            dataIndex: 'group',
            key: 'group',
            width: 100,
            render: (group: string) => (
                <span style={{ color: group === 'admin' ? '#ff4d4f' : '#1890ff' }}>
                    {group === 'admin' ? '管理员' : '普通用户'}
                </span>
            ),
        },
        {
            title: '创建时间',
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
                        key: 'edit',
                        label: '编辑',
                        icon: <EditOutlined />,
                        onClick: () => handleEdit(record),
                    },
                    {
                        key: 'delete',
                        label: '删除',
                        icon: <DeleteOutlined />,
                        danger: true,
                        onClick: () => handleDelete(record.uid),
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
                title="用户管理"
                extra={
                    <Button type="primary" icon={<PlusOutlined />} onClick={handleAdd}>
                        新增用户
                    </Button>
                }
            >
                <Space wrap style={{ marginBottom: 16, width: '100%', justifyContent: 'space-between' }}>
                    <Space wrap>
                        <Input.Search
                            placeholder="搜索用户名、邮箱或显示名称"
                            allowClear
                            value={searchKeyword}
                            onChange={(e) => {
                                setSearchKeyword(e.target.value)
                                setPage(1)
                            }}
                            onSearch={() => {
                                setPage(1)
                                loadData()
                            }}
                            style={{ width: 300 }}
                        />
                        <Select
                            placeholder="筛选用户组"
                            allowClear
                            value={filterGroup || undefined}
                            onChange={(value) => {
                                setFilterGroup(value || '')
                                setPage(1)
                            }}
                            style={{ width: 150 }}
                        >
                            <Option value="admin">管理员</Option>
                            <Option value="member">普通用户</Option>
                        </Select>
                    </Space>
                </Space>

                <Table
                    columns={columns}
                    dataSource={data}
                    loading={loading}
                    rowKey="uid"
                    scroll={{ x: 1000 }}
                    pagination={{
                        current: page,
                        pageSize: pageSize,
                        total: total,
                        showSizeChanger: true,
                        showTotal: (total) => `共 ${total} 条`,
                        onChange: (page, pageSize) => {
                            setPage(page)
                            setPageSize(pageSize)
                        },
                    }}
                />
            </Card>

            <Modal
                title={editingRecord ? '编辑用户' : '新增用户'}
                open={modalVisible}
                onCancel={() => setModalVisible(false)}
                footer={null}
                width={600}
            >
                <Form form={form} layout="vertical" onFinish={handleSubmit}>
                    <Form.Item
                        name="name"
                        label="用户名"
                        rules={[{ required: true, message: '请输入用户名' }]}
                    >
                        <Input placeholder="用户名" />
                    </Form.Item>

                    <Form.Item
                        name="email"
                        label="邮箱"
                        rules={[
                            { required: true, message: '请输入邮箱' },
                            { type: 'email', message: '邮箱格式不正确' },
                        ]}
                    >
                        <Input placeholder="邮箱地址" />
                    </Form.Item>

                    <Form.Item
                        name="password"
                        label={editingRecord ? '新密码（留空不修改）' : '密码'}
                        rules={
                            editingRecord
                                ? []
                                : [{ required: true, message: '请输入密码' }, { min: 6, message: '密码长度至少6位' }]
                        }
                    >
                        <Input.Password placeholder={editingRecord ? '留空则不修改密码' : '密码（至少6位）'} />
                    </Form.Item>

                    <Form.Item name="display_name" label="显示名称">
                        <Input placeholder="显示名称（可选）" />
                    </Form.Item>

                    <Form.Item
                        name="group"
                        label="用户组"
                        initialValue="member"
                        rules={[{ required: true, message: '请选择用户组' }]}
                    >
                        <Select>
                            <Option value="member">普通用户</Option>
                            <Option value="admin">管理员</Option>
                        </Select>
                    </Form.Item>

                    <Form.Item name="avatar" label="头像URL">
                        <Input placeholder="头像URL（可选）" />
                    </Form.Item>

                    <Form.Item style={{ marginBottom: 0, textAlign: 'right' }}>
                        <Space>
                            <Button onClick={() => setModalVisible(false)}>取消</Button>
                            <Button type="primary" htmlType="submit">
                                保存
                            </Button>
                        </Space>
                    </Form.Item>
                </Form>
            </Modal>
        </div>
    )
}

