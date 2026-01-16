<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useThemeSync, useAuthGuard } from '@/composables'

const auth = useAuthStore() as any

useThemeSync()

const { mounted } = useAuthGuard()
</script>

<template>
  <UApp>
    <UNotifications />
    <div v-if="mounted">
      <AppHeader v-if="auth.isAuthenticated" />
      <UMain class="container mx-auto p-6">
        <RouterView />
      </UMain>
      <AppFooter v-if="auth.isAuthenticated" />
    </div>
  </UApp>
</template>