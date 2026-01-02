const DebugConsole = {
    setup() {
        const { ref, reactive, onMounted } = Vue;
        const activeSection = ref('overview');
        const loading = ref(false);
        const sectionLoading = ref(false);
        const userInfo = ref(null);
        const sectionData = reactive({
            performance: null,
            logs: null,
            errors: null,
            hooks: null
        });

        const navItems = [
            { id: 'overview', label: '系统概览' },
            { id: 'performance', label: '性能监控' },
            { id: 'logs', label: '系统日志' },
            { id: 'errors', label: '错误日志' },
            { id: 'hooks', label: 'Hook调试' },
            { id: 'tools', label: '调试工具' }
        ];

        const fetchToken = async () => {
            try {
                const response = await fetch('/auth/token', {
                    credentials: 'include'
                });
                const data = await response.json();
                if (data.success && data.data && data.data.token) {
                    localStorage.setItem('token', data.data.token);
                    return data.data.token;
                }
            } catch (err) {
                console.debug('获取 Token 失败:', err);
            }
            return null;
        };

        const checkAuth = async () => {
            try {
                let token = localStorage.getItem('token');

                if (!token) {
                    token = await fetchToken();
                }

                const headers = {};
                if (token) {
                    headers['X-API-Token'] = token;
                }

                const response = await fetch('/user/info', {
                    credentials: 'include',
                    headers: headers
                });
                const data = await response.json();
                if (data.success && data.data) {
                    userInfo.value = data.data;
                    if (data.data.token) {
                        localStorage.setItem('token', data.data.token);
                    } else if (!token && data.success) {
                        token = await fetchToken();
                    }
                } else {
                    window.location.href = '/anon/debug/login';
                }
            } catch (err) {
                console.error('检查登录状态失败:', err);
                window.location.href = '/anon/debug/login';
            }
        };

        const switchSection = (sectionId) => {
            activeSection.value = sectionId;
            if (sectionId !== 'overview' && !sectionData[sectionId]) {
                loadSectionData(sectionId);
            }
        };

        const loadSectionData = async (section) => {
            sectionLoading.value = true;
            try {
                const headers = {
                    'Content-Type': 'application/json'
                };

                const token = localStorage.getItem('token');
                if (token) {
                    headers['X-API-Token'] = token;
                }

                const response = await fetch(`/anon/debug/api/${section}`, {
                    credentials: 'include',
                    headers: headers
                });
                const data = await response.json();
                if (data.success && data.data) {
                    sectionData[section] = data.data;
                } else {
                    console.error('加载数据失败:', data.message);
                }
            } catch (err) {
                console.error('网络错误:', err);
            } finally {
                sectionLoading.value = false;
            }
        };

        const refreshData = () => {
            const currentSection = activeSection.value;
            if (currentSection !== 'overview') {
                sectionData[currentSection] = null;
                loadSectionData(currentSection);
            } else {
                window.location.reload();
            }
        };

        const clearDebugData = async () => {
            if (!confirm('确定要清理调试数据吗？')) {
                return;
            }
            try {
                const headers = {
                    'Content-Type': 'application/json'
                };

                const token = localStorage.getItem('token');
                if (token) {
                    headers['X-API-Token'] = token;
                }

                const response = await fetch('/anon/debug/api/clear', {
                    method: 'POST',
                    credentials: 'include',
                    headers: headers
                });
                const data = await response.json();
                if (data.success) {
                    alert('调试数据已清理');
                    refreshData();
                } else {
                    alert('清理失败: ' + data.message);
                }
            } catch (err) {
                alert('网络错误: ' + err.message);
            }
        };

        const exportDebugData = () => {
            window.open('/anon/debug/api/info', '_blank');
        };

        const clearAllData = () => {
            if (confirm('确定要清空所有调试数据吗？此操作不可恢复！')) {
                clearDebugData();
            }
        };

        const handleLogout = async () => {
            try {
                await fetch('/auth/logout', {
                    method: 'POST',
                    credentials: 'include'
                });
                localStorage.removeItem('token');
                window.location.href = '/anon/debug/login';
            } catch (err) {
                console.error('退出失败:', err);
                localStorage.removeItem('token');
                window.location.href = '/anon/debug/login';
            }
        };

        onMounted(() => {
            checkAuth();
        });

        return {
            activeSection,
            loading,
            sectionLoading,
            userInfo,
            sectionData,
            navItems,
            switchSection,
            refreshData,
            clearDebugData,
            exportDebugData,
            clearAllData,
            handleLogout
        };
    }
};

const DebugLogin = {
    setup() {
        const { ref, reactive, onMounted } = Vue;
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
};

