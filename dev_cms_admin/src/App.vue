<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useThemeSync, useAuthGuard } from '@/composables'

const auth = useAuthStore() as any

// 主题同步
useThemeSync()

// 认证守卫
const { mounted } = useAuthGuard()
</script>

<template>
  <UApp>
    <UNotifications />
    <div v-if="mounted">
      <AppHeader v-if="auth.isAuthenticated" />
      <UMain>
        <RouterView />
      </UMain>
    </div>
  </UApp>
</template>