<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const items: NavigationMenuItem[] = [
    {
        label: 'Issues',
        to: 'https://github.com/YuiNijika/Anon/issues',
        target: '_blank'
    },
    {
        label: 'Releases',
        to: 'https://github.com/YuiNijika/Anon/releases',
        target: '_blank'
    }
]
</script>

<template>
    <USeparator />
    <UFooter>
        <template #left>
            <p class="text-muted text-sm">© {{ new Date().getFullYear() }} Anon Framework</p>
        </template>

        <UNavigationMenu :items="items" variant="link" />

        <template #right>
            <UButton icon="line-md:github" color="neutral" variant="ghost" to="https://github.com/YuiNijika/Anon"
                target="_blank" aria-label="GitHub" />
        </template>
    </UFooter>
</template>