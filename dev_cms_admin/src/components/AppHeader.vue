<script setup lang="ts">
import { computed } from 'vue'
import type { NavigationMenuItem } from '@nuxt/ui'
import { useRoute } from 'vue-router'

const route = useRoute()

const items = computed<NavigationMenuItem[]>(() => [{
    label: 'Docs',
    to: '/docs/getting-started',
    icon: 'i-lucide-book-open',
    active: route.path.startsWith('/docs/getting-started')
}, {
    label: 'Components',
    to: '/docs/components',
    icon: 'i-lucide-box',
    active: route.path.startsWith('/docs/components')
}, {
    label: 'Figma',
    icon: 'i-simple-icons-figma',
    to: 'https://go.nuxt.com/figma-ui',
    target: '_blank'
}, {
    label: 'Releases',
    icon: 'i-lucide-rocket',
    to: 'https://github.com/nuxt/ui/releases',
    target: '_blank'
}])
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