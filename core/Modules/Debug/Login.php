<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anon Debug Console</title>
    <script src="/core/Static/vue.global.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Consolas', 'Monaco', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .login-container {
            background: #2d2d30;
            border: 1px solid #3e3e42;
            border-radius: 8px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #569cd6;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #9cdcfe;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #cccccc;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            color: #d4d4d4;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #569cd6;
        }
        
        .captcha-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .captcha-group .form-group {
            flex: 1;
        }
        
        .captcha-image {
            width: 120px;
            height: 40px;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            cursor: pointer;
            background: #1e1e1e;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9cdcfe;
            font-size: 12px;
            margin-bottom: 20px;
        }
        
        .captcha-image:hover {
            border-color: #569cd6;
        }
        
        .captcha-image img {
            max-width: 100%;
            max-height: 100%;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #0e639c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: background-color 0.2s;
        }
        
        .btn:hover:not(:disabled) {
            background: #1177bb;
        }
        
        .btn:disabled {
            background: #3e3e42;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .error {
            color: #f44747;
            background: #2d1b1b;
            border: 1px solid #5a1e1e;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .success {
            color: #4ec9b0;
            background: #1b2d1b;
            border: 1px solid #1e5a1e;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .loading {
            text-align: center;
            color: #9cdcfe;
            padding: 20px;
        }
    </style>
</head>
<body>
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
    
    <script>
        const { createApp, ref, reactive, onMounted } = Vue;
        
        createApp({
            setup() {
                const form = reactive({
                    username: '',
                    password: '',
                    captcha: ''
                });
                
                const loading = ref(false);
                const error = ref('');
                const success = ref('');
                const captchaEnabled = ref(false);
                const captchaImage = ref('');
                
                const checkCaptchaEnabled = async () => {
                    try {
                        const response = await fetch('/anon/common/config');
                        const data = await response.json();
                        if (data.success && data.data && data.data.captcha) {
                            captchaEnabled.value = data.data.captcha;
                            if (captchaEnabled.value) {
                                refreshCaptcha();
                            }
                        }
                    } catch (err) {
                        console.error('检查验证码配置失败:', err);
                    }
                };
                
                const refreshCaptcha = async () => {
                    try {
                        const response = await fetch('/auth/captcha');
                        const data = await response.json();
                        if (data.success && data.data && data.data.image) {
                            captchaImage.value = data.data.image;
                            form.captcha = '';
                        }
                    } catch (err) {
                        error.value = '获取验证码失败: ' + err.message;
                    }
                };
                
                const handleLogin = async () => {
                    loading.value = true;
                    error.value = '';
                    success.value = '';
                    
                    try {
                        const payload = {
                            username: form.username,
                            password: form.password
                        };
                        
                        if (captchaEnabled.value) {
                            payload.captcha = form.captcha;
                        }
                        
                        const response = await fetch('/auth/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            credentials: 'include',
                            body: JSON.stringify(payload)
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            if (data.data && data.data.token) {
                                localStorage.setItem('token', data.data.token);
                            }
                            success.value = '登录成功，正在跳转...';
                            setTimeout(() => {
                                window.location.href = '/anon/debug/console';
                            }, 500);
                        } else {
                            error.value = data.message || '登录失败';
                            
                            if (captchaEnabled.value && (data.message.includes('验证码') || data.message.includes('captcha'))) {
                                form.captcha = '';
                                refreshCaptcha();
                            }
                        }
                    } catch (err) {
                        error.value = '网络错误: ' + err.message;
                    } finally {
                        loading.value = false;
                    }
                };
                
                onMounted(() => {
                    checkCaptchaEnabled();
                });
                
                return {
                    form,
                    loading,
                    error,
                    success,
                    captchaEnabled,
                    captchaImage,
                    refreshCaptcha,
                    handleLogin
                };
            }
        }).mount('#app');
    </script>
</body>
</html>

