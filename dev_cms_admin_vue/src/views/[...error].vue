<script setup lang="ts">
import { ref } from 'vue'
const links = ref([
    {
        label: '返回控制台',
        to: '/console',
        icon: 'i-lucide-home'
    }
])
</script>

<template>
    <UPageHero 
        title="Page not found"
        description="页面未找到"
        headline="404" 
        :links="links"
    />
</template>