import { useState, useEffect } from 'react'
import { Card, Table, Button, App, Space, Modal, Form, Input, Select, Avatar, Dropdown } from 'antd'
import { PlusOutlined, EditOutlined, DeleteOutlined, MoreOutlined, UserOutlined } from '@ant-design/icons'
import type { MenuProps } from 'antd'
import { useUsers, useAuth } from '@/hooks'

const { Option } = Select

export default function ManageUsers() {
    const { loading, data, total, loadUsers, createUser, updateUser, deleteUser } = useUsers()
    const auth = useAuth()
    const app = App.useApp()
    const modal = app.modal
    const [form] = Form.useForm()
    const [page, setPage] = useState(1)
    const [pageSize, setPageSize] = useState(20)
    const [searchKeyword, setSearchKeyword] = useState('')
    const [filterGroup, setFilterGroup] = useState<string>('')
    const [modalVisible, setModalVisible] = useState(false)
    const [editingRecord, setEditingRecord] = useState<any>(null)

    useEffect(() => {
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
        loadUsers(params)
    }, [page, pageSize, searchKeyword, filterGroup, loadUsers])

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
                const success = await deleteUser(uid)
                if (success) {
                    const params: any = { page, page_size: pageSize }
                    if (searchKeyword) params.search = searchKeyword
                    if (filterGroup) params.group = filterGroup
                    loadUsers(params)
                }
            },
        })
    }

    const handleSubmit = async (values: any) => {
        if (editingRecord) {
            const updateData: any = {
                uid: editingRecord.uid,
                name: values.name,
                email: values.email,
                display_name: values.display_name,
                group: values.group,
            }
            if (values.password) {
                updateData.password = values.password
            }
            const result = await updateUser(updateData)
            if (result) {
                setModalVisible(false)
                const params: any = { page, page_size: pageSize }
                if (searchKeyword) params.search = searchKeyword
                if (filterGroup) params.group = filterGroup
                loadUsers(params)
            }
        } else {
            const result = await createUser(values)
            if (result) {
                setModalVisible(false)
                const params: any = { page, page_size: pageSize }
                if (searchKeyword) params.search = searchKeyword
                if (filterGroup) params.group = filterGroup
                loadUsers(params)
            }
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
            render: (group: string) => {
                const groupMap: Record<string, { label: string; color: string }> = {
                    admin: { label: '管理员', color: '#ff4d4f' },
                    editor: { label: '编辑', color: '#fa8c16' },
                    user: { label: '普通用户', color: '#1890ff' },
                }
                const groupInfo = groupMap[group] || { label: group, color: '#666' }
                return <span style={{ color: groupInfo.color }}>{groupInfo.label}</span>
            },
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
                            <Option value="editor">编辑</Option>
                            <Option value="user">普通用户</Option>
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
                        initialValue="user"
                        rules={[{ required: true, message: '请选择用户组' }]}
                    >
                        <Select disabled={editingRecord && editingRecord.uid === auth.user?.uid}>
                            <Option value="user">普通用户</Option>
                            <Option value="editor">编辑</Option>
                            <Option value="admin">管理员</Option>
                        </Select>
                    </Form.Item>
                    {editingRecord && editingRecord.uid === auth.user?.uid && (
                        <div style={{ marginTop: -16, marginBottom: 16, color: '#999', fontSize: 12 }}>
                            不能更改自己的用户组
                        </div>
                    )}

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

