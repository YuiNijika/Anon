<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const auth = useAuth() as any
const router = useRouter()

const handleLogout = async () => {
    try {
        await auth.logout()
    } catch (error) {
        // 静默处理错误
    } finally {
        router.push('/')
    }
}

const items = computed(() => {
    const user = auth.user
    const userName = user?.display_name || user?.displayName || user?.public_name || user?.publicName || user?.name || user?.email || '用户'
    const userAvatar = user?.avatar || user?.avatar_url || undefined

    return [
        [
            {
                label: userName,
                avatar: userAvatar ? { src: userAvatar } : undefined,
                type: 'label'
            }
        ],
        [
            {
                label: '退出登录',
                icon: 'i-lucide-log-out',
                onSelect: handleLogout
            }
        ]
    ]
})

const avatarSrc = computed(() => {
    const user = auth.user
    return user?.avatar || user?.avatar_url || undefined
})
</script>

<template>
    <UDropdownMenu :items="items">
        <UAvatar v-if="avatarSrc" :src="avatarSrc" size="sm" />
        <UAvatar v-else :alt="auth.user?.name || '用户'" size="sm" />
    </UDropdownMenu>
</template>