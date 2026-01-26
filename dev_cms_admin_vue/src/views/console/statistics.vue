<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApiAdmin, type StatisticsData } from '@/composables/useApiAdmin'

const api = useApiAdmin()
const statistics = ref<StatisticsData | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

/**
 * 格式化文件大小
 */
function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return `${(bytes / Math.pow(k, i)).toFixed(2)} ${sizes[i]}`
}

/**
 * 格式化数字
 */
function formatNumber(num: number): string {
    if (num >= 1000000) {
        return `${(num / 1000000).toFixed(1)}M`
    }
    if (num >= 1000) {
        return `${(num / 1000).toFixed(1)}K`
    }
    return num.toString()
}

/**
 * 加载统计数据
 */
async function loadStatistics() {
    loading.value = true
    error.value = null
    try {
        const res = await api.getStatistics()
        if (res.code === 200 && res.data) {
            statistics.value = res.data
        } else {
            error.value = res.message || '获取统计数据失败'
        }
    } catch (err) {
        error.value = err instanceof Error ? err.message : '网络请求失败'
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadStatistics()
})

// 统计卡片配置
const statCards = [
    {
        key: 'posts',
        label: '文章总数',
        icon: 'i-lucide-file-text',
        color: 'blue',
        value: () => statistics.value?.posts ?? 0,
    },
    {
        key: 'published_posts',
        label: '已发布',
        icon: 'i-lucide-check-circle',
        color: 'green',
        value: () => statistics.value?.published_posts ?? 0,
    },
    {
        key: 'draft_posts',
        label: '草稿',
        icon: 'i-lucide-edit',
        color: 'yellow',
        value: () => statistics.value?.draft_posts ?? 0,
    },
    {
        key: 'comments',
        label: '评论总数',
        icon: 'i-lucide-message-square',
        color: 'purple',
        value: () => statistics.value?.comments ?? 0,
    },
    {
        key: 'approved_comments',
        label: '已通过',
        icon: 'i-lucide-check',
        color: 'green',
        value: () => statistics.value?.approved_comments ?? 0,
    },
    {
        key: 'pending_comments',
        label: '待审核',
        icon: 'i-lucide-clock',
        color: 'orange',
        value: () => statistics.value?.pending_comments ?? 0,
    },
    {
        key: 'attachments',
        label: '附件总数',
        icon: 'i-lucide-paperclip',
        color: 'indigo',
        value: () => statistics.value?.attachments ?? 0,
    },
    {
        key: 'attachments_size',
        label: '附件总大小',
        icon: 'i-lucide-hard-drive',
        color: 'teal',
        value: () => statistics.value?.attachments_size ?? 0,
        format: (val: number) => formatFileSize(val),
    },
    {
        key: 'categories',
        label: '分类',
        icon: 'i-lucide-folder',
        color: 'pink',
        value: () => statistics.value?.categories ?? 0,
    },
    {
        key: 'tags',
        label: '标签',
        icon: 'i-lucide-tag',
        color: 'cyan',
        value: () => statistics.value?.tags ?? 0,
    },
    {
        key: 'users',
        label: '用户总数',
        icon: 'i-lucide-users',
        color: 'gray',
        value: () => statistics.value?.users ?? 0,
    },
    {
        key: 'total_views',
        label: '总浏览量',
        icon: 'i-lucide-eye',
        color: 'red',
        value: () => statistics.value?.total_views ?? 0,
        format: (val: number) => formatNumber(val),
    },
]

function refreshStatistics() {
    loadStatistics()
}
</script>

<template>
    <UPageHeader
        title="数据统计"
        description="管理站点数据统计"
        :links="[{ label: '刷新', icon: 'i-lucide-refresh-cw', onClick: refreshStatistics }]"
    />
    <div class="space-y-6">
        <div v-if="error"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
            <div class="flex items-center gap-2">
                <UIcon name="i-lucide-alert-circle" class="h-5 w-5" />
                <span>{{ error }}</span>
            </div>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <UCard v-for="card in statCards" :key="card.key" class="transition-shadow hover:shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-bold">
                            <template v-if="loading && !statistics">
                                <span
                                    class="inline-block h-8 w-16 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></span>
                            </template>
                            <template v-else>
                                {{ card.format ? card.format(card.value()) : formatNumber(card.value()) }}
                            </template>
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg" :class="{
                        'bg-blue-100 dark:bg-blue-900/20': card.color === 'blue',
                        'bg-green-100 dark:bg-green-900/20': card.color === 'green',
                        'bg-yellow-100 dark:bg-yellow-900/20': card.color === 'yellow',
                        'bg-purple-100 dark:bg-purple-900/20': card.color === 'purple',
                        'bg-orange-100 dark:bg-orange-900/20': card.color === 'orange',
                        'bg-indigo-100 dark:bg-indigo-900/20': card.color === 'indigo',
                        'bg-teal-100 dark:bg-teal-900/20': card.color === 'teal',
                        'bg-pink-100 dark:bg-pink-900/20': card.color === 'pink',
                        'bg-cyan-100 dark:bg-cyan-900/20': card.color === 'cyan',
                        'bg-gray-100 dark:bg-gray-900/20': card.color === 'gray',
                        'bg-red-100 dark:bg-red-900/20': card.color === 'red',
                    }">
                        <UIcon :name="card.icon" class="h-6 w-6" :class="{
                            'text-blue-600 dark:text-blue-400': card.color === 'blue',
                            'text-green-600 dark:text-green-400': card.color === 'green',
                            'text-yellow-600 dark:text-yellow-400': card.color === 'yellow',
                            'text-purple-600 dark:text-purple-400': card.color === 'purple',
                            'text-orange-600 dark:text-orange-400': card.color === 'orange',
                            'text-indigo-600 dark:text-indigo-400': card.color === 'indigo',
                            'text-teal-600 dark:text-teal-400': card.color === 'teal',
                            'text-pink-600 dark:text-pink-400': card.color === 'pink',
                            'text-cyan-600 dark:text-cyan-400': card.color === 'cyan',
                            'text-gray-600 dark:text-gray-400': card.color === 'gray',
                            'text-red-600 dark:text-red-400': card.color === 'red',
                        }" />
                    </div>
                </div>
            </UCard>
        </div>
    </div>
</template>