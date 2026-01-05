// Vue 类型声明
declare const Vue: any;

// 类型定义
interface NavItem {
    id: string;
    label: string;
}

interface UserInfo {
    uid?: number;
    name?: string;
    email?: string;
    token?: string;
    [key: string]: any;
}

interface ApiResponse<T = any> {
    code: number;
    message?: string;
    data?: T;
}

interface SectionData {
    performance: any;
    logs: any;
    errors: any;
    hooks: any;
}

interface LoginForm {
    username: string;
    password: string;
    captcha: string;
}

// DebugConsole 组件
const DebugConsole = {
    setup() {
        const { ref, reactive, onMounted } = Vue;
        const activeSection = ref('overview');
        const loading = ref(false);
        const sectionLoading = ref(false);
        const userInfo = ref(null as UserInfo | null);
        const sectionData = reactive({
            performance: null,
            logs: null,
            errors: null,
            hooks: null
        });

        const navItems: NavItem[] = [
            { id: 'overview', label: '系统概览' },
            { id: 'performance', label: '性能监控' },
            { id: 'logs', label: '系统日志' },
            { id: 'errors', label: '错误日志' },
            { id: 'hooks', label: 'Hook调试' },
            { id: 'tools', label: '调试工具' }
        ];

        const fetchToken = async (): Promise<string | null> => {
            try {
                const response = await fetch('/auth/token', {
                    credentials: 'include'
                });
                const data: ApiResponse<{ token: string }> = await response.json();
                if (data.code === 200 && data.data?.token) {
                    localStorage.setItem('token', data.data.token);
                    return data.data.token;
                }
            } catch (err) {
                console.debug('获取 Token 失败:', err);
            }
            return null;
        };

        const checkAuth = async (): Promise<void> => {
            try {
                let token: string | null = localStorage.getItem('token');

                if (!token) {
                    token = await fetchToken();
                }

                const headers: Record<string, string> = {};
                if (token) {
                    headers['X-API-Token'] = token;
                }

                const response = await fetch('/user/info', {
                    credentials: 'include',
                    headers: headers
                });
                const data: ApiResponse<UserInfo> = await response.json();
                if (data.code === 200 && data.data) {
                    userInfo.value = data.data;
                    if (data.data.token) {
                        localStorage.setItem('token', data.data.token);
                    } else if (!token && data.code === 200) {
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

        const switchSection = (sectionId: string): void => {
            activeSection.value = sectionId;
            if (sectionId !== 'overview' && !sectionData[sectionId as keyof SectionData]) {
                loadSectionData(sectionId);
            }
        };

        const loadSectionData = async (section: string): Promise<void> => {
            sectionLoading.value = true;
            try {
                const headers: Record<string, string> = {
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
                const data: ApiResponse = await response.json();
                if (data.code === 200 && data.data) {
                    (sectionData as any)[section] = data.data;
                } else {
                    console.error('加载数据失败:', data.message);
                }
            } catch (err) {
                console.error('网络错误:', err);
            } finally {
                sectionLoading.value = false;
            }
        };

        const refreshData = (): void => {
            const currentSection = activeSection.value;
            if (currentSection !== 'overview') {
                (sectionData as any)[currentSection] = null;
                loadSectionData(currentSection);
            } else {
                window.location.reload();
            }
        };

        const clearDebugData = async (): Promise<void> => {
            if (!confirm('确定要清理调试数据吗？')) {
                return;
            }
            try {
                const headers: Record<string, string> = {
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
                const data: ApiResponse = await response.json();
                if (data.code === 200) {
                    alert('调试数据已清理');
                    refreshData();
                } else {
                    alert('清理失败: ' + data.message);
                }
            } catch (err: any) {
                alert('网络错误: ' + err.message);
            }
        };

        const exportDebugData = (): void => {
            window.open('/anon/debug/api/info', '_blank');
        };

        const clearAllData = (): void => {
            if (confirm('确定要清空所有调试数据吗？此操作不可恢复！')) {
                clearDebugData();
            }
        };

        const handleLogout = async (): Promise<void> => {
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

// DebugLogin 组件
const DebugLogin = {
    setup() {
        const { ref, reactive, onMounted } = Vue;
        const form = reactive({
            username: '',
            password: '',
            captcha: ''
        } as LoginForm);

        const loading = ref(false);
        const error = ref('');
        const success = ref('');
        const captchaEnabled = ref(false);
        const captchaImage = ref('');

        const checkCaptchaEnabled = async (): Promise<void> => {
            try {
                const response = await fetch('/anon/common/config');
                const data = await response.json() as ApiResponse<{ captcha: boolean }>;
                if (data.code === 200 && data.data?.captcha !== undefined) {
                    captchaEnabled.value = data.data.captcha;
                    if (captchaEnabled.value) {
                        refreshCaptcha();
                    }
                }
            } catch (err) {
                console.error('检查验证码配置失败:', err);
            }
        };

        const refreshCaptcha = async (): Promise<void> => {
            try {
                const response = await fetch('/auth/captcha');
                const data = await response.json() as ApiResponse<{ image: string }>;
                if (data.code === 200 && data.data?.image) {
                    captchaImage.value = data.data.image;
                    form.captcha = '';
                }
            } catch (err: any) {
                error.value = '获取验证码失败: ' + err.message;
            }
        };

        const handleLogin = async (): Promise<void> => {
            loading.value = true;
            error.value = '';
            success.value = '';

            try {
                const payload: Partial<LoginForm> = {
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

                const data = await response.json() as ApiResponse<{ token?: string }>;

                if (data.code === 200) {
                    if (data.data?.token) {
                        localStorage.setItem('token', data.data.token);
                    }
                    success.value = '登录成功，正在跳转...';
                    setTimeout(() => {
                        window.location.href = '/anon/debug/console';
                    }, 500);
                } else {
                    error.value = data.message || '登录失败';

                    if (captchaEnabled.value && (data.message?.includes('验证码') || data.message?.includes('captcha'))) {
                        form.captcha = '';
                        refreshCaptcha();
                    }
                }
            } catch (err: any) {
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

// 导出到全局作用域
if (typeof window !== 'undefined') {
    (window as any).DebugConsole = DebugConsole;
    (window as any).DebugLogin = DebugLogin;
}

