<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApiAdmin, type ThemeSettings, type ThemeInfo } from '@/composables/useApiAdmin'
import { useNotify } from '@/composables/useNotify'

const api = useApiAdmin()
const notify = useNotify()

const loading = ref(true)
const switching = ref<string | null>(null)
const currentTheme = ref<string>('')
const themes = ref<ThemeInfo[]>([])

// 已启用的主题永远在最前
const themePosts = computed(() => {
    return [...themes.value]
        .sort((a, b) => {
            const aIsCurrent = a.name === currentTheme.value
            const bIsCurrent = b.name === currentTheme.value
            if (aIsCurrent && !bIsCurrent) return -1
            if (!aIsCurrent && bIsCurrent) return 1
            return 0
        })
        .map((theme) => ({
            title: theme.displayName,
            description: theme.description,
            image: theme.screenshot,
            badge: theme.name === currentTheme.value ? '当前使用' : undefined,
            authors: theme.author ? [{ name: theme.author }] : undefined,
            date: theme.version ? `v${theme.version}` : undefined,
            url: theme.url,
            themeName: theme.name,
            isActive: theme.name === currentTheme.value,
        }))
})

async function loadThemes() {
    loading.value = true
    try {
        const res = await api.getThemeSettings()
        if (res.code === 200 && res.data) {
            currentTheme.value = res.data.current
            themes.value = res.data.themes
        } else {
            notify.error(res.message || '获取主题列表失败')
        }
    } catch (err) {
        notify.error(err instanceof Error ? err.message : '网络请求失败')
    } finally {
        loading.value = false
    }
}

async function switchTheme(themeName: string) {
    if (themeName === currentTheme.value) {
        return
    }

    switching.value = themeName
    try {
        const res = await api.updateThemeSettings(themeName)
        if (res.code === 200) {
            currentTheme.value = themeName
            notify.success('切换主题成功')
        } else {
            notify.error(res.message || '切换主题失败')
        }
    } catch (err) {
        notify.error(err instanceof Error ? err.message : '网络请求失败')
    } finally {
        switching.value = null
    }
}

onMounted(() => {
    loadThemes()
})
</script>

<template>
    <UPageHeader title="主题管理" description="管理站点主题，切换和预览可用主题" />

    <div v-if="loading" class="flex justify-center py-12">
        <div class="text-gray-500">加载中...</div>
    </div>

    <div v-else>
        <div v-if="themes.length === 0" class="text-center py-12 text-gray-500">
            暂无可用主题
        </div>

        <UBlogPosts v-else orientation="horizontal">
            <UBlogPost v-for="post in themePosts" :key="post.themeName" :title="post.title"
                :description="post.description" :image="post.image" :badge="post.badge" :authors="post.authors"
                :date="post.date" :variant="post.isActive ? 'solid' : 'outline'"
                :class="post.isActive ? 'ring-2 ring-primary' : ''">
                <template #footer>
                    <div class="flex items-center justify-between mt-4">
                        <a v-if="post.url" :href="post.url" target="_blank" rel="noopener noreferrer"
                            class="text-sm text-primary hover:underline" @click.stop>
                            详情
                        </a>
                        <UButton v-if="!post.isActive" :loading="switching === post.themeName"
                            :disabled="switching !== null" color="primary" size="sm"
                            @click="switchTheme(post.themeName)">
                            启用
                        </UButton>
                        <UButton v-else disabled color="gray" variant="soft" size="sm">
                            已启用
                        </UButton>
                    </div>
                </template>
            </UBlogPost>
        </UBlogPosts>
    </div>
</template>