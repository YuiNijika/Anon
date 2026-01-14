<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useCaptcha } from '@/composables/useCaptcha'

const router = useRouter()
const auth = useAuth() as any
const captcha = useCaptcha()

const form = reactive({
    username: '',
    password: '',
    captcha: '',
    rememberMe: false
})

const handleSubmit = async (e: Event) => {
    e.preventDefault()

    if (!form.username || !form.password) {
        return
    }

    if (captcha.enabled.value && !form.captcha) {
        return
    }

    try {
        await auth.login(form)
        router.push('/')
    } catch (error) {
        if (captcha.enabled.value) {
            await captcha.refresh()
        }
    }
}

onMounted(() => {
    captcha.check()
    if (auth.isAuthenticated) {
        router.push('/')
    }
})
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4">
        <UCard class="w-full max-w-md">
            <template #header>
                <h2 class="text-xl font-semibold">登录</h2>
            </template>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <UInput v-model="form.username" placeholder="请输入用户名" :disabled="auth.loading" class="w-full" required />

                <UInput v-model="form.password" type="password" placeholder="请输入密码" :disabled="auth.loading"
                    class="w-full" required />

                <div v-if="captcha.enabled.value" class="flex gap-2">
                    <UInput v-model="form.captcha" placeholder="请输入验证码" :disabled="auth.loading" class="flex-1"
                        required />
                    <img v-if="captcha.image.value" :src="captcha.image.value" @click="captcha.refresh" alt="验证码"
                        class="w-32 h-10 cursor-pointer border border-gray-300 rounded" />
                </div>

                <div class="flex justify-end">
                    <UCheckbox v-model="form.rememberMe" :disabled="auth.loading" label="记住30天登录状态" />
                </div>

                <UButton type="submit" color="primary" block :loading="auth.loading" :disabled="auth.loading">
                    登录
                </UButton>
            </form>
        </UCard>
    </div>
</template>