import { useEffect, useState } from 'react'
import { Card, Table, Button, App, Space, Modal, Form, Input, Dropdown } from 'antd'
import { PlusOutlined, EditOutlined, DeleteOutlined, MoreOutlined } from '@ant-design/icons'
import type { MenuProps } from 'antd'
import { useCategories } from '@/hooks'

export default function ManageCategories() {
    const { loading, data, loadCategories, createCategory, updateCategory, deleteCategory } = useCategories()
    const app = App.useApp()
    const modal = app.modal
    const [form] = Form.useForm()
    const [modalVisible, setModalVisible] = useState(false)
    const [editingRecord, setEditingRecord] = useState<any>(null)

    useEffect(() => {
        loadCategories()
    }, [loadCategories])

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
        modal.confirm({
            title: '确认删除',
            content: '确定要删除这个分类吗？',
            onOk: async () => {
                const success = await deleteCategory(id)
                if (success) {
                    loadCategories()
                }
            },
        })
    }

    const handleSubmit = async (values: any) => {
        if (editingRecord) {
            const result = await updateCategory({
                id: editingRecord.id,
                ...values,
            })
            if (result) {
                setModalVisible(false)
                loadCategories()
            }
        } else {
            const result = await createCategory(values)
            if (result) {
                setModalVisible(false)
                loadCategories()
            }
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
            ellipsis: true,
        },
        {
            title: '别名',
            dataIndex: 'slug',
            key: 'slug',
            ellipsis: true,
        },
        {
            title: '描述',
            dataIndex: 'description',
            key: 'description',
            ellipsis: true,
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
                title="分类管理"
                extra={
                    <Button type="primary" icon={<PlusOutlined />} onClick={handleAdd}>
                        新增分类
                    </Button>
                }
            >
                <Table
                    columns={columns}
                    dataSource={data}
                    loading={loading}
                    rowKey="id"
                    scroll={{ x: 600 }}
                    pagination={{
                        showSizeChanger: true,
                        showTotal: (total) => `共 ${total} 条`,
                    }}
                />
            </Card>

            <Modal
                title={editingRecord ? '编辑分类' : '新增分类'}
                open={modalVisible}
                onCancel={() => setModalVisible(false)}
                footer={null}
            >
                <Form form={form} layout="vertical" onFinish={handleSubmit}>
                    <Form.Item
                        name="name"
                        label="名称"
                        rules={[{ required: true, message: '请输入分类名称' }]}
                    >
                        <Input placeholder="分类名称" />
                    </Form.Item>

                    <Form.Item name="slug" label="别名">
                        <Input placeholder="URL 友好的别名（可选）" />
                    </Form.Item>

                    <Form.Item name="description" label="描述">
                        <Input.TextArea rows={3} placeholder="分类描述（可选）" />
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

