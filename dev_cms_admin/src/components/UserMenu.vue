<script setup lang="ts">
import { ref } from 'vue'
const items = ref([
    [
        {
            label: 'Benjamin',
            avatar: {
                src: 'https://github.com/benjamincanac.png'
            },
            type: 'label'
        }
    ],
    [
        {
            label: 'Profile',
            icon: 'i-lucide-user'
        },
        {
            label: 'Billing',
            icon: 'i-lucide-credit-card'
        },
        {
            label: 'Settings',
            icon: 'i-lucide-cog',
            kbds: [',']
        },
        {
            label: 'Keyboard shortcuts',
            icon: 'i-lucide-monitor'
        }
    ],
    [
        {
            label: 'Team',
            icon: 'i-lucide-users'
        },
        {
            label: 'Invite users',
            icon: 'i-lucide-user-plus',
            children: [
                [
                    {
                        label: 'Email',
                        icon: 'i-lucide-mail'
                    },
                    {
                        label: 'Message',
                        icon: 'i-lucide-message-square'
                    }
                ],
                [
                    {
                        label: 'More',
                        icon: 'i-lucide-circle-plus'
                    }
                ]
            ]
        },
        {
            label: 'New team',
            icon: 'i-lucide-plus',
            kbds: ['meta', 'n']
        }
    ],
    [
        {
            label: 'GitHub',
            icon: 'i-simple-icons-github',
            to: 'https://github.com/nuxt/ui',
            target: '_blank'
        },
        {
            label: 'Support',
            icon: 'i-lucide-life-buoy',
            to: '/docs/components/dropdown-menu'
        },
        {
            label: 'API',
            icon: 'i-lucide-cloud',
            disabled: true
        }
    ],
    [
        {
            label: 'Logout',
            icon: 'i-lucide-log-out',
            kbds: ['shift', 'meta', 'q']
        }
    ]
])
</script>

<template>
    <UDropdownMenu :items="items">
        <UAvatar src="https://github.com/benjamincanac.png" size="sm" />
    </UDropdownMenu>
</template>