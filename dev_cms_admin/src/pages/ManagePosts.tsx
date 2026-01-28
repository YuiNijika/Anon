import { useState, useEffect } from 'react'
import { Card, Table, Button, App, Space, Input, Tag, Dropdown } from 'antd'
import { EditOutlined, DeleteOutlined, PlusOutlined, MoreOutlined } from '@ant-design/icons'
import type { MenuProps } from 'antd'
import { useNavigate } from 'react-router-dom'
import { useApiAdmin } from '@/hooks'
import { getApiBaseUrl } from '@/utils/api'
import { getAdminToken, checkLoginStatus, getApiPrefix } from '@/utils/token'

export default function ManagePosts() {
    const apiAdmin = useApiAdmin()
    const navigate = useNavigate()
    const app = App.useApp()
    const messageApi = app.message
    const modal = app.modal
    const [loading, setLoading] = useState(false)
    const [data, setData] = useState<any[]>([])
    const [total, setTotal] = useState(0)
    const [page, setPage] = useState(1)
    const [pageSize, setPageSize] = useState(20)
    const [searchKeyword, setSearchKeyword] = useState('')
    const [statusFilter, setStatusFilter] = useState<string>('all')
    const [typeFilter, setTypeFilter] = useState<string>('all')

    useEffect(() => {
        loadData()
    }, [page, pageSize, searchKeyword, statusFilter, typeFilter])

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

            if (statusFilter !== 'all') {
                params.status = statusFilter
            }

            if (typeFilter !== 'all') {
                params.type = typeFilter
            }

            const response = await apiAdmin.admin.get('/posts', params)
            if (response.code === 200) {
                setData(response.data.list || [])
                setTotal(response.data.total || 0)
            } else {
                messageApi.error(response.message || '加载文章列表失败')
            }
        } catch (err) {
            messageApi.error('加载文章列表失败')
        } finally {
            setLoading(false)
        }
    }

    const handleDelete = async (id: number) => {
        modal.confirm({
            title: '确认删除',
            content: '确定要删除这篇文章吗？',
            onOk: async () => {
                try {
                    const baseUrl = getApiBaseUrl()
                    const apiPrefix = await getApiPrefix()
                    const prefix = apiPrefix || '/anon'
                    const url = `${baseUrl}${prefix}/cms/admin/posts`
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
                        body: JSON.stringify({ id }),
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

    const handleEdit = (record: any) => {
        navigate(`/write?id=${record.id}`)
    }

    const columns = [
        {
            title: 'ID',
            dataIndex: 'id',
            key: 'id',
            width: 80,
        },
        {
            title: '标题',
            dataIndex: 'title',
            key: 'title',
            ellipsis: true,
            render: (text: string) => (
                <span title={text} style={{ maxWidth: '300px', display: 'inline-block' }}>
                    {text || '-'}
                </span>
            ),
        },
        {
            title: '类型',
            dataIndex: 'type',
            key: 'type',
            width: 80,
            render: (type: string) => (
                <Tag color={type === 'post' ? 'blue' : 'green'}>
                    {type === 'post' ? '文章' : '页面'}
                </Tag>
            ),
        },
        {
            title: '状态',
            dataIndex: 'status',
            key: 'status',
            width: 100,
            render: (status: string) => {
                const statusMap: Record<string, { color: string; text: string }> = {
                    draft: { color: 'default', text: '草稿' },
                    publish: { color: 'success', text: '已发布' },
                    private: { color: 'warning', text: '私有' },
                }
                const statusInfo = statusMap[status] || { color: 'default', text: status }
                return <Tag color={statusInfo.color}>{statusInfo.text}</Tag>
            },
        },
        {
            title: '浏览量',
            dataIndex: 'views',
            key: 'views',
            width: 100,
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
                title="文章管理"
                extra={
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={() => navigate('/write')}
                    >
                        新增文章
                    </Button>
                }
            >
                <Space direction="vertical" style={{ width: '100%' }} size="middle">
                    <Space wrap style={{ width: '100%' }} size={[8, 8]}>
                        <Input.Search
                            placeholder="搜索标题或别名"
                            allowClear
                            style={{ width: 300, maxWidth: '100%' }}
                            onSearch={(value) => {
                                setSearchKeyword(value)
                                setPage(1)
                            }}
                        />
                        <Button.Group>
                            <Button
                                type={statusFilter === 'all' ? 'primary' : 'default'}
                                onClick={() => {
                                    setStatusFilter('all')
                                    setPage(1)
                                }}
                            >
                                全部
                            </Button>
                            <Button
                                type={statusFilter === 'draft' ? 'primary' : 'default'}
                                onClick={() => {
                                    setStatusFilter('draft')
                                    setPage(1)
                                }}
                            >
                                草稿
                            </Button>
                            <Button
                                type={statusFilter === 'publish' ? 'primary' : 'default'}
                                onClick={() => {
                                    setStatusFilter('publish')
                                    setPage(1)
                                }}
                            >
                                已发布
                            </Button>
                        </Button.Group>
                        <Button.Group>
                            <Button
                                type={typeFilter === 'all' ? 'primary' : 'default'}
                                onClick={() => {
                                    setTypeFilter('all')
                                    setPage(1)
                                }}
                            >
                                全部
                            </Button>
                            <Button
                                type={typeFilter === 'post' ? 'primary' : 'default'}
                                onClick={() => {
                                    setTypeFilter('post')
                                    setPage(1)
                                }}
                            >
                                文章
                            </Button>
                            <Button
                                type={typeFilter === 'page' ? 'primary' : 'default'}
                                onClick={() => {
                                    setTypeFilter('page')
                                    setPage(1)
                                }}
                            >
                                页面
                            </Button>
                        </Button.Group>
                    </Space>

                    <Table
                        columns={columns}
                        dataSource={data}
                        loading={loading}
                        rowKey="id"
                        scroll={{ x: 800 }}
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
                </Space>
            </Card>
        </div>
    )
}

