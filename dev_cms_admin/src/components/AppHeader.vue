<script setup lang="ts">
import { ref, computed } from 'vue'
import type { NavigationMenuItem } from '@nuxt/ui'
import { useRoute } from 'vue-router'

const route = useRoute()

const items = computed<NavigationMenuItem[]>(() => [
    {
        label: '控制台',
        to: '/console',
        icon: 'i-lucide-home',
        active: route.path.startsWith('/console'),
        children: [
            {
                label: '统计信息',
                to: '/console/statistics',
                icon: 'i-lucide-bar-chart',
                active: route.path.startsWith('/console/statistics')
            }
        ]
    },
    {
        label: '撰写',
        to: '/write/post',
        icon: 'i-lucide-file-text',
        active: route.path.startsWith('/write'),
        children: [
            {
                label: '撰写文章',
                to: '/write/post',
                icon: 'i-lucide-file-text',
                active: route.path.startsWith('/write/post')
            },
            {
                label: '创建页面',
                to: '/write/page',
                icon: 'i-lucide-file-text',
                active: route.path.startsWith('/write/page')
            }
        ]
    },
    {
        label: '设置',
        to: '/settings/basic',
        icon: 'i-lucide-settings',
        active: route.path.startsWith('/settings'),
        children: [
            {
                label: '基本设置',
                to: '/settings/basic',
                icon: 'i-lucide-globe',
                active: route.path.startsWith('/settings/basic')
            },
            {
                label: '主题管理',
                to: '/settings/themes',
                icon: 'i-lucide-palette',
                active: route.path.startsWith('/settings/themes')
            }
        ]
    }
])
</script>

<template>
    <UHeader>
        <template #title>
            <span class="text-2xl font-bold">Anon Admin</span>
        </template>

        <UNavigationMenu :items="items" />

        <template #right>
            <UColorModeButton />

            <UserMenu />
        </template>

        <template #body>
            <UNavigationMenu :items="items" orientation="vertical" class="-mx-2.5" />
        </template>
    </UHeader>
</template>