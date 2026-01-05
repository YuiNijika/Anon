<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

if (Anon_Check::isLoggedIn()) {
    header('Location: /anon/debug/console');
    exit;
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anon Debug Console</title>
    <link rel="stylesheet" href="/anon/static/debug/css">
    <script src="/anon/static/vue"></script>
</head>
<body class="login-page">
    <div id="app" class="login-container">
        <div class="login-header">
            <h1>Anon Debug Console</h1>
            <p>System debugging and monitoring interface</p>
        </div>
        
        <div v-if="error" class="error">{{ error }}</div>
        <div v-if="success" class="success">{{ success }}</div>
        
        <form @submit.prevent="handleLogin">
            <div class="form-group">
                <label>用户名</label>
                <input 
                    type="text" 
                    v-model="form.username" 
                    required 
                    :disabled="loading"
                    autocomplete="username"
                    placeholder="请输入用户名"
                />
            </div>
            
            <div class="form-group">
                <label>密码</label>
                <input 
                    type="password" 
                    v-model="form.password" 
                    required 
                    :disabled="loading"
                    autocomplete="current-password"
                    placeholder="请输入密码"
                />
            </div>
            
            <div v-if="captchaEnabled" class="captcha-group">
                <div class="form-group">
                    <label>验证码</label>
                    <input 
                        type="text" 
                        v-model="form.captcha" 
                        required 
                        :disabled="loading"
                        placeholder="请输入验证码"
                    />
                </div>
                <div class="captcha-image" @click="refreshCaptcha" :title="'点击刷新验证码'">
                    <img v-if="captchaImage" :src="captchaImage" alt="验证码" />
                    <span v-else>点击获取</span>
                </div>
            </div>
            
            <button type="submit" class="btn" :disabled="loading">
                <span v-if="loading">登录中...</span>
                <span v-else>登录</span>
            </button>
        </form>
    </div>
    
    <script src="/anon/static/debug/js"></script>
    <script>
        const { createApp, ref, reactive, onMounted } = Vue;
        createApp({
            setup: DebugLogin.setup
        }).mount('#app');
    </script>
</body>
</html>

