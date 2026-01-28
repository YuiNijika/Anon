import { useState, useEffect } from 'react'
import { Spin, Modal, Upload, Image, Button, Space, App, Radio, Input, Empty } from 'antd'
import { UploadOutlined, DeleteOutlined, SearchOutlined } from '@ant-design/icons'
import type { UploadProps } from 'antd'
import { useApiAdmin } from '@/hooks'
import { buildPublicUrl, getApiBaseUrl } from '@/utils/api'
import { getAdminToken, checkLoginStatus, getApiPrefix } from '@/utils/token'

interface MediaLibraryProps {
    open: boolean
    onClose: () => void
    onSelect?: (attachment: any) => void
    multiple?: boolean
    accept?: string
}

export default function MediaLibrary({ open, onClose, onSelect, multiple = false, accept }: MediaLibraryProps) {
    const apiAdmin = useApiAdmin()
    const app = App.useApp()
    const messageApi = app.message
    const [loading, setLoading] = useState(false)
    const [attachments, setAttachments] = useState<any[]>([])
    const [total, setTotal] = useState(0)
    const [page, setPage] = useState(1)
    const [pageSize] = useState(20)
    const [filterType, setFilterType] = useState<string>('all')
    const [searchKeyword, setSearchKeyword] = useState('')
    const [selectedIds, setSelectedIds] = useState<number[]>([])

    useEffect(() => {
        if (open) {
            loadAttachments()
        }
    }, [open, page, filterType])

    const loadAttachments = async () => {
        try {
            setLoading(true)
            const params: any = {
                page,
                page_size: pageSize,
            }

            if (filterType !== 'all') {
                params.mime_type = filterType
            }

            const response = await apiAdmin.admin.get('/attachments', params)
            if (response.code === 200) {
                setAttachments(response.data.list || [])
                setTotal(response.data.total || 0)
            }
        } catch (err) {
            messageApi.error('加载附件失败')
        } finally {
            setLoading(false)
        }
    }

    const handleUpload: UploadProps['customRequest'] = async (options) => {
        const { file, onSuccess, onError } = options

        try {
            const formData = new FormData()
            formData.append('file', file as File)

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

            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData,
                credentials: 'include',
            }).then(res => res.json())

            if (response.code === 200) {
                messageApi.success('上传成功')
                onSuccess?.(response.data)
                loadAttachments()
            } else {
                onError?.(new Error(response.message || '上传失败'))
                messageApi.error(response.message || '上传失败')
            }
        } catch (err) {
            onError?.(err as Error)
            messageApi.error('上传失败')
        }
    }

    const handleDelete = async (id: number) => {
        try {
            const baseUrl = getApiBaseUrl()
            const apiPrefix = await getApiPrefix()
            const prefix = apiPrefix || '/anon'
            const url = `${baseUrl}${prefix}/cms/admin/attachments?id=${id}`
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
                loadAttachments()
            } else {
                messageApi.error(response.message || '删除失败')
            }
        } catch (err) {
            messageApi.error('删除失败')
        }
    }

    const handleSelect = (attachment: any) => {
        if (multiple) {
            const newSelectedIds = selectedIds.includes(attachment.id)
                ? selectedIds.filter(id => id !== attachment.id)
                : [...selectedIds, attachment.id]
            setSelectedIds(newSelectedIds)
        } else {
            onSelect?.(attachment)
            onClose()
        }
    }

    const handleConfirmSelection = () => {
        if (multiple && selectedIds.length > 0) {
            const selected = attachments.filter(a => selectedIds.includes(a.id))
            onSelect?.(selected)
            onClose()
        }
    }

    const isImage = (mimeType: string) => {
        return mimeType?.startsWith('image/')
    }

    const filteredAttachments = searchKeyword
        ? attachments.filter(a =>
            a.original_name?.toLowerCase().includes(searchKeyword.toLowerCase()) ||
            a.filename?.toLowerCase().includes(searchKeyword.toLowerCase())
        )
        : attachments

    return (
        <Modal
            title="媒体库"
            open={open}
            onCancel={onClose}
            width={900}
            footer={[
                <Button key="close" onClick={onClose}>
                    关闭
                </Button>,
                multiple && (
                    <Button
                        key="confirm"
                        type="primary"
                        onClick={handleConfirmSelection}
                        disabled={selectedIds.length === 0}
                    >
                        插入选中 ({selectedIds.length})
                    </Button>
                ),
            ].filter(Boolean)}
        >
            <Space direction="vertical" style={{ width: '100%' }} size="middle">
                {/* 工具栏 */}
                <Space style={{ width: '100%', justifyContent: 'space-between' }}>
                    <Space>
                        <Upload customRequest={handleUpload} showUploadList={false} accept={accept}>
                            <Button type="primary" icon={<UploadOutlined />}>
                                上传文件
                            </Button>
                        </Upload>
                        <Input
                            placeholder="搜索文件..."
                            prefix={<SearchOutlined />}
                            value={searchKeyword}
                            onChange={(e) => setSearchKeyword(e.target.value)}
                            style={{ width: 200 }}
                            allowClear
                        />
                    </Space>
                    <Radio.Group value={filterType} onChange={(e) => setFilterType(e.target.value)}>
                        <Radio.Button value="all">全部</Radio.Button>
                        <Radio.Button value="image">图片</Radio.Button>
                        <Radio.Button value="video">视频</Radio.Button>
                        <Radio.Button value="audio">音频</Radio.Button>
                    </Radio.Group>
                </Space>

                {/* 文件网格 */}
                <Spin spinning={loading}>
                    {filteredAttachments.length === 0 && !loading ? (
                        <div
                            style={{
                                maxHeight: '500px',
                                padding: '48px 16px',
                                borderRadius: '4px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <Empty description={searchKeyword ? '暂无匹配的媒体文件' : '暂无媒体文件'} />
                        </div>
                    ) : (
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))',
                                gap: '16px',
                                maxHeight: '500px',
                                overflowY: 'auto',
                                padding: '16px',
                                border: '1px solid #f0f0f0',
                                borderRadius: '4px',
                            }}
                        >
                            {filteredAttachments.map((attachment) => {
                                const selected = selectedIds.includes(attachment.id)
                                return (
                                    <div
                                        key={attachment.id}
                                        style={{
                                            position: 'relative',
                                            border: selected ? '2px solid #1890ff' : '1px solid #d9d9d9',
                                            borderRadius: '4px',
                                            padding: '8px',
                                            cursor: 'pointer',
                                            backgroundColor: selected ? '#e6f7ff' : '#fff',
                                        }}
                                        onClick={() => handleSelect(attachment)}
                                    >
                                        {isImage(attachment.mime_type) ? (
                                            <Image
                                                src={buildPublicUrl(attachment.url)}
                                                alt={attachment.original_name}
                                                style={{ width: '100%', height: '120px', objectFit: 'cover' }}
                                                preview={false}
                                            />
                                        ) : (
                                            <div
                                                style={{
                                                    width: '100%',
                                                    height: '120px',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    backgroundColor: '#f5f5f5',
                                                    fontSize: '48px',
                                                }}
                                            >
                                                📄
                                            </div>
                                        )}
                                        <div
                                            style={{
                                                marginTop: '8px',
                                                fontSize: '12px',
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap',
                                            }}
                                            title={attachment.original_name}
                                        >
                                            {attachment.original_name}
                                        </div>
                                        <Button
                                            type="text"
                                            danger
                                            size="small"
                                            icon={<DeleteOutlined />}
                                            onClick={(e) => {
                                                e.stopPropagation()
                                                handleDelete(attachment.id)
                                            }}
                                            style={{ position: 'absolute', top: '4px', right: '4px' }}
                                        />
                                    </div>
                                )
                            })}
                        </div>
                    )}
                </Spin>

                {/* 分页 */}
                {total > pageSize && (
                    <div style={{ textAlign: 'center' }}>
                        <Button
                            disabled={page === 1}
                            onClick={() => setPage(page - 1)}
                            style={{ marginRight: '8px' }}
                        >
                            上一页
                        </Button>
                        <span style={{ margin: '0 16px' }}>
                            {page} / {Math.ceil(total / pageSize)}
                        </span>
                        <Button
                            disabled={page >= Math.ceil(total / pageSize)}
                            onClick={() => setPage(page + 1)}
                        >
                            下一页
                        </Button>
                    </div>
                )}
            </Space>
        </Modal>
    )
}

