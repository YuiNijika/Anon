<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApiAdmin, type BasicSettings } from '@/composables/useApiAdmin'
import { useNotify } from '@/composables/useNotify'

const api = useApiAdmin()
const notify = useNotify()

const loading = ref(true) // 初始为 true，等待数据加载
const saving = ref(false)
const form = ref<BasicSettings>({
    title: '',
    description: '',
    keywords: '',
    allow_register: false,
    api_prefix: '/api',
    api_enabled: false,
    upload_allowed_types: {
        image: 'gif,jpg,jpeg,png,tiff,bmp,webp,avif',
        media: 'mp3,mp4,mov,wmv,wma,rmvb,rm,avi,flv,ogg,oga,ogv',
        document: 'txt,doc,docx,xls,xlsx,ppt,pptx,zip,rar,pdf',
        other: '',
    },
})

/**
 * 加载设置
 */
async function loadSettings() {
    loading.value = true
    try {
        const res = await api.getBasicSettings()
        if (res.code === 200 && res.data) {
            // 将数据库中的设置值填充到表单
            form.value = {
                title: res.data.title || '',
                description: res.data.description || '',
                keywords: res.data.keywords || '',
                allow_register: res.data.allow_register ?? false,
                api_prefix: res.data.api_prefix || '/api',
                api_enabled: res.data.api_enabled ?? false,
                upload_allowed_types: res.data.upload_allowed_types || {
                    image: 'gif,jpg,jpeg,png,tiff,bmp,webp,avif',
                    media: 'mp3,mp4,mov,wmv,wma,rmvb,rm,avi,flv,ogg,oga,ogv',
                    document: 'txt,doc,docx,xls,xlsx,ppt,pptx,zip,rar,pdf',
                    other: '',
                },
            }
        } else {
            notify.error(res.message || '获取设置失败')
        }
    } catch (err) {
        notify.error(err instanceof Error ? err.message : '网络请求失败')
    } finally {
        loading.value = false
    }
}

/**
 * 保存设置
 */
async function handleSubmit() {
    if (!form.value.title.trim()) {
        notify.error('站点名称不能为空')
        return
    }

    if (!form.value.api_prefix.trim() || !form.value.api_prefix.startsWith('/')) {
        notify.error('API 前缀必须以 / 开头')
        return
    }

    saving.value = true
    try {
        const res = await api.updateBasicSettings(form.value)
        if (res.code === 200) {
            notify.success('保存成功')
        } else {
            notify.error(res.message || '保存失败')
        }
    } catch (err) {
        notify.error(err instanceof Error ? err.message : '网络请求失败')
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    loadSettings()
})
</script>

<template>
    <UPageHeader title="基本设置" description="管理站点基本信息" />

    <UCard>
        <template #header>
            <h2 class="text-lg font-semibold">站点信息</h2>
        </template>

        <div v-if="loading" class="flex justify-center py-8">
            <div class="text-gray-500">加载中...</div>
        </div>

        <form v-else @submit.prevent="handleSubmit" class="space-y-6">
            <div>
                <label class="block text-sm font-medium mb-2">
                    站点名称 <span class="text-red-500">*</span>
                </label>
                <UInput v-model="form.title" placeholder="请输入站点名称" :disabled="saving" required class="w-full" />
                <p class="text-sm text-gray-500 mt-1">站点的名称，将显示在浏览器标题栏和网站头部</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">站点描述</label>
                <UInput v-model="form.description" placeholder="请输入站点描述" :disabled="saving" type="textarea" :rows="4"
                    class="w-full" />
                <p class="text-sm text-gray-500 mt-1">站点的简短描述，用于 SEO 和社交媒体分享</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">关键词</label>
                <UInput v-model="form.keywords" placeholder="请输入关键词，多个关键词用逗号分隔" :disabled="saving" class="w-full" />
                <p class="text-sm text-gray-500 mt-1">站点的关键词，用于 SEO，多个关键词请用逗号分隔</p>
            </div>
            <USeparator />
            <h3 class="text-lg font-semibold mb-4">用户设置</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium mb-1">允许注册</label>
                        <p class="text-sm text-gray-500">是否允许新用户注册</p>
                    </div>
                    <UCheckbox v-model="form.allow_register" :disabled="saving" />
                </div>
            </div>

            <USeparator />
            <h3 class="text-lg font-semibold mb-4">API 设置</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">
                        API 前缀 <span class="text-red-500">*</span>
                    </label>
                    <UInput v-model="form.api_prefix" placeholder="/api" :disabled="saving" required class="w-full" />
                    <p class="text-sm text-gray-500 mt-1">API 接口的前缀路径，必须以 / 开头</p>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium mb-1">启用 API</label>
                        <p class="text-sm text-gray-500">是否启用 API 功能</p>
                    </div>
                    <UCheckbox v-model="form.api_enabled" :disabled="saving" />
                </div>
            </div>


            <USeparator />
            <h3 class="text-lg font-semibold mb-4">上传文件类型设置</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">图片文件</label>
                    <UInput v-model="form.upload_allowed_types.image" placeholder="gif,jpg,jpeg,png,tiff,bmp,webp,avif"
                        :disabled="saving" class="w-full" />
                    <p class="text-sm text-gray-500 mt-1">允许上传的图片格式，用逗号分隔</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">多媒体文件</label>
                    <UInput v-model="form.upload_allowed_types.media"
                        placeholder="mp3,mp4,mov,wmv,wma,rmvb,rm,avi,flv,ogg,oga,ogv" :disabled="saving"
                        class="w-full" />
                    <p class="text-sm text-gray-500 mt-1">允许上传的多媒体格式，用逗号分隔</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">常用档案文件</label>
                    <UInput v-model="form.upload_allowed_types.document"
                        placeholder="txt,doc,docx,xls,xlsx,ppt,pptx,zip,rar,pdf" :disabled="saving" class="w-full" />
                    <p class="text-sm text-gray-500 mt-1">允许上传的文档格式，用逗号分隔</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">其他格式</label>
                    <UInput v-model="form.upload_allowed_types.other" placeholder="cpp,h,mak" :disabled="saving"
                        class="w-full" />
                    <p class="text-sm text-gray-500 mt-1">其他允许上传的文件格式，用逗号分隔</p>
                </div>
            </div>

            <UButton type="submit" color="primary" :loading="saving" :disabled="saving" block>
                保存设置
            </UButton>
        </form>
    </UCard>
</template>