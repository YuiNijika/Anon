<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { onMounted } from 'vue'

const auth = useAuthStore() as any
const router = useRouter()

onMounted(() => {
  if (!auth.initialized) {
    auth.initialize()
  }
  
  if (auth.isAuthenticated || auth.hasToken) {
    router.push('/console')
  } else {
    router.push('/login')
  }
})
</script>

<template>

</template>