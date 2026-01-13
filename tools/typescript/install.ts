declare const Vue: any;

interface ApiResponse<T = any> {
    code: number;
    message: string;
    data: T;
}

interface DatabaseForm {
    db_host: string;
    db_port: number;
    db_user: string;
    db_pass: string;
    db_name: string;
    db_prefix: string;
}

interface SiteForm {
    username: string;
    email: string;
    password: string;
    site_title: string;
    site_description: string;
}

(window as any).InstallApp = {
    setup() {
        const { ref, computed, h, onMounted } = Vue;
        const currentStep = ref('mode');
        const selectedMode = ref('api');
        const error = ref('');
        const success = ref('');
        const loading = ref(false);
        const csrfToken = ref('');
        const existingTables = ref([] as string[]);

        const databaseForm = ref({
            db_host: 'localhost',
            db_port: 3306,
            db_user: 'root',
            db_pass: '',
            db_name: '',
            db_prefix: 'anon_'
        } as DatabaseForm);

        const siteForm = ref({
            username: 'admin',
            email: '',
            password: '',
            site_title: '',
            site_description: ''
        } as SiteForm);

        // 计算密码强度，根据长度和字符类型评分
        const passwordStrength = computed(() => {
            const pwd = siteForm.value.password;
            if (!pwd) return 0;
            let strength = 0;
            if (pwd.length >= 8) strength += 20;
            if (pwd.length >= 12) strength += 20;
            if (/[A-Z]/.test(pwd)) strength += 20;
            if (/[0-9]/.test(pwd)) strength += 20;
            if (/[^A-Za-z0-9]/.test(pwd)) strength += 20;
            return strength;
        });

        // 根据密码强度返回对应颜色
        const passwordStrengthColor = computed(() => {
            const strength = passwordStrength.value;
            if (strength < 40) return '#e74c3c';
            if (strength < 70) return '#f39c12';
            return '#2ecc71';
        });

        // 获取 CSRF Token
        const fetchToken = async () => {
            try {
                const response = await fetch('/anon/install/api/token');
                const data: ApiResponse<{ csrf_token: string }> = await response.json();
                if (data.code === 200) {
                    csrfToken.value = data.data.csrf_token;
                } else {
                    error.value = data.message || '获取 Token 失败';
                }
            } catch (e: any) {
                error.value = '网络错误：' + e.message;
            }
        };

        // 处理模式选择
        const handleModeSelect = async (appMode: string) => {
            if (loading.value) return;

            error.value = '';
            loading.value = true;

            try {
                const response = await fetch('/anon/install/api/mode', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken.value,
                        app_mode: appMode
                    })
                });

                const data: ApiResponse<{ mode: string }> = await response.json();

                if (data.code === 200) {
                    selectedMode.value = appMode;
                    currentStep.value = 'database';
                    loading.value = false;
                } else {
                    error.value = data.message || '模式选择失败';
                    loading.value = false;
                }
            } catch (e: any) {
                error.value = '网络错误：' + e.message;
                loading.value = false;
            }
        };

        // 处理数据库配置提交
        const handleDatabaseSubmit = async () => {
            if (loading.value) return;

            if (!databaseForm.value.db_pass) {
                error.value = '数据库密码不能为空';
                return;
            }

            error.value = '';
            loading.value = true;

            try {
                const response = await fetch('/anon/install/api/database', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken.value,
                        ...databaseForm.value
                    })
                });

                const data: ApiResponse<{ mode: string; tables_exist?: boolean; existing_tables?: string[] }> = await response.json();

                if (data.code === 200) {
                    if (data.data.tables_exist && data.data.existing_tables) {
                        existingTables.value = data.data.existing_tables;
                        currentStep.value = 'overwrite';
                    } else if (selectedMode.value === 'cms') {
                        currentStep.value = 'site';
                    } else {
                        currentStep.value = 'admin';
                    }
                    loading.value = false;
                } else {
                    error.value = data.message || '数据库配置失败';
                    loading.value = false;
                }
            } catch (e: any) {
                error.value = '网络错误：' + e.message;
                loading.value = false;
            }
        };

        // 处理站点配置提交
        const handleSiteSubmit = async () => {
            if (loading.value) return;

            if (siteForm.value.password.length < 8) {
                error.value = '密码长度至少需要8个字符';
                return;
            }

            if (!siteForm.value.email.includes('@')) {
                error.value = '请输入有效的邮箱地址';
                return;
            }

            error.value = '';
            loading.value = true;

            try {
                const response = await fetch('/anon/install/api/site', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken.value,
                        ...siteForm.value
                    })
                });

                const data: ApiResponse<{ redirect: string }> = await response.json();

                if (data.code === 200) {
                    success.value = data.message || '安装成功！';
                    setTimeout(() => {
                        window.location.href = data.data.redirect || '/';
                    }, 1000);
                } else {
                    error.value = data.message || '安装失败';
                    loading.value = false;
                }
            } catch (e: any) {
                error.value = '网络错误：' + e.message;
                loading.value = false;
            }
        };

        // 处理管理员账号提交
        const handleAdminSubmit = async () => {
            if (loading.value) return;

            if (siteForm.value.password.length < 8) {
                error.value = '密码长度至少需要8个字符';
                return;
            }

            if (!siteForm.value.email.includes('@')) {
                error.value = '请输入有效的邮箱地址';
                return;
            }

            error.value = '';
            loading.value = true;

            try {
                const response = await fetch('/anon/install/api/install', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken.value,
                        username: siteForm.value.username,
                        password: siteForm.value.password,
                        email: siteForm.value.email
                    })
                });

                const data: ApiResponse<{ redirect: string }> = await response.json();

                if (data.code === 200) {
                    success.value = data.message || '安装成功！';
                    setTimeout(() => {
                        window.location.href = data.data.redirect || '/';
                    }, 1000);
                } else {
                    error.value = data.message || '安装失败';
                    loading.value = false;
                }
            } catch (e: any) {
                error.value = '网络错误：' + e.message;
                loading.value = false;
            }
        };

        // 处理覆盖确认
        const handleOverwriteConfirm = async (confirm: boolean) => {
            if (loading.value) return;

            error.value = '';
            loading.value = true;

            try {
                const response = await fetch('/anon/install/api/confirm-overwrite', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken.value,
                        confirm: confirm ? 'yes' : 'no'
                    })
                });

                const data: ApiResponse = await response.json();

                if (data.code === 200) {
                    if (confirm) {
                        if (selectedMode.value === 'cms') {
                            currentStep.value = 'site';
                        } else {
                            currentStep.value = 'admin';
                        }
                    } else {
                        currentStep.value = 'database';
                    }
                    loading.value = false;
                } else {
                    error.value = data.message || '确认失败';
                    loading.value = false;
                }
            } catch (e: any) {
                error.value = '网络错误：' + e.message;
                loading.value = false;
            }
        };

        // 返回上一步
        const handleBack = () => {
            if (currentStep.value === 'database') {
                currentStep.value = 'mode';
            } else if (currentStep.value === 'overwrite') {
                currentStep.value = 'database';
            } else if (currentStep.value === 'site' || currentStep.value === 'admin') {
                if (existingTables.value.length > 0) {
                    currentStep.value = 'overwrite';
                } else {
                    currentStep.value = 'database';
                }
            }
            error.value = '';
            success.value = '';
        };

        // 模式选择步骤组件
        const ModeSelectStep = () => {
            return h('div', { class: 'install-container' }, [
                h('div', { class: 'install-header' }, [
                    h('h1', '系统安装向导'),
                    h('p', '选择安装模式')
                ]),
                error.value ? h('div', { class: 'error' }, error.value) : null,
                success.value ? h('div', { class: 'success' }, success.value) : null,
                h('form', {
                    onSubmit: (e: Event) => {
                        e.preventDefault();
                    }
                }, [
                    h('div', { class: 'form-group' }, [
                        h('label', '安装模式'),
                        h('select', {
                            value: 'api',
                            onChange: (e: Event) => {
                                const target = e.target as HTMLSelectElement;
                                handleModeSelect(target.value);
                            },
                            disabled: loading.value
                        }, [
                            h('option', { value: 'api' }, 'API 模式'),
                            h('option', { value: 'cms' }, 'CMS 模式')
                        ]),
                        h('div', { class: 'requirements' }, [
                            'API 模式：纯 API 接口，不加载主题系统',
                            h('br'),
                            'CMS 模式：内容管理系统，支持主题和页面'
                        ])
                    ]),
                    h('button', {
                        type: 'button',
                        class: 'btn',
                        onClick: () => {
                            const select = document.querySelector('select') as HTMLSelectElement;
                            if (select) {
                                handleModeSelect(select.value);
                            }
                        },
                        disabled: loading.value
                    }, loading.value ? '处理中...' : '下一步')
                ])
            ]);
        };

        // 数据库配置步骤组件
        const DatabaseStep = () => {
            return h('div', { class: 'install-container' }, [
                h('div', { class: 'install-header' }, [
                    h('h1', '系统安装向导'),
                    h('p', '配置数据库')
                ]),
                error.value ? h('div', { class: 'error' }, error.value) : null,
                success.value ? h('div', { class: 'success' }, success.value) : null,
                h('form', {
                    onSubmit: (e: Event) => {
                        e.preventDefault();
                        handleDatabaseSubmit();
                    }
                }, [
                    h('h3', '数据库配置'),
                    h('div', { class: 'form-group' }, [
                        h('label', '数据库主机'),
                        h('input', {
                            type: 'text',
                            value: databaseForm.value.db_host,
                            onInput: (e: Event) => {
                                databaseForm.value.db_host = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            disabled: loading.value,
                            placeholder: 'localhost'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '数据库端口'),
                        h('input', {
                            type: 'number',
                            value: databaseForm.value.db_port,
                            onInput: (e: Event) => {
                                databaseForm.value.db_port = parseInt((e.target as HTMLInputElement).value) || 3306;
                            },
                            min: 1,
                            max: 65535,
                            required: true,
                            disabled: loading.value,
                            placeholder: '3306'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '数据库用户名'),
                        h('input', {
                            type: 'text',
                            value: databaseForm.value.db_user,
                            onInput: (e: Event) => {
                                databaseForm.value.db_user = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            disabled: loading.value,
                            placeholder: 'root'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '数据库密码'),
                        h('input', {
                            type: 'password',
                            value: databaseForm.value.db_pass,
                            onInput: (e: Event) => {
                                databaseForm.value.db_pass = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            disabled: loading.value,
                            placeholder: '数据库密码'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '数据库名称'),
                        h('input', {
                            type: 'text',
                            value: databaseForm.value.db_name,
                            onInput: (e: Event) => {
                                databaseForm.value.db_name = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            disabled: loading.value,
                            placeholder: '数据库名称'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '数据表前缀'),
                        h('input', {
                            type: 'text',
                            value: databaseForm.value.db_prefix,
                            onInput: (e: Event) => {
                                databaseForm.value.db_prefix = (e.target as HTMLInputElement).value;
                            },
                            pattern: '[a-zA-Z0-9_]+',
                            required: true,
                            disabled: loading.value,
                            placeholder: 'anon_'
                        }),
                        h('div', { class: 'requirements' }, '只能包含字母、数字和下划线')
                    ]),
                    h('div', { style: 'display: flex; gap: 10px;' }, [
                        h('button', {
                            type: 'button',
                            onClick: handleBack,
                            disabled: loading.value,
                            class: 'btn',
                            style: 'background: #95a5a6; flex: 1;'
                        }, loading.value ? '处理中...' : '返回上一步'),
                        h('button', {
                            type: 'submit',
                            disabled: loading.value,
                            class: 'btn',
                            style: 'flex: 2;'
                        }, loading.value ? '配置中...' : '下一步')
                    ])
                ])
            ]);
        };

        // 站点配置步骤组件
        const SiteStep = () => {
            return h('div', { class: 'install-container' }, [
                h('div', { class: 'install-header' }, [
                    h('h1', '系统安装向导'),
                    h('p', '配置站点信息')
                ]),
                error.value ? h('div', { class: 'error' }, error.value) : null,
                success.value ? h('div', { class: 'success' }, success.value) : null,
                h('form', {
                    onSubmit: (e: Event) => {
                        e.preventDefault();
                        handleSiteSubmit();
                    }
                }, [
                    h('h3', '管理员账号'),
                    h('div', { class: 'form-group' }, [
                        h('label', '用户名'),
                        h('input', {
                            type: 'text',
                            value: siteForm.value.username,
                            onInput: (e: Event) => {
                                siteForm.value.username = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            disabled: loading.value,
                            placeholder: 'admin'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '邮箱'),
                        h('input', {
                            type: 'email',
                            value: siteForm.value.email,
                            onInput: (e: Event) => {
                                siteForm.value.email = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            disabled: loading.value,
                            placeholder: 'admin@example.com'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '密码'),
                        h('input', {
                            type: 'password',
                            value: siteForm.value.password,
                            onInput: (e: Event) => {
                                siteForm.value.password = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            minLength: 8,
                            disabled: loading.value,
                            placeholder: '至少8个字符'
                        }),
                        h('div', { class: 'password-strength' }, [
                            h('div', {
                                class: 'password-strength-bar',
                                style: {
                                    width: passwordStrength.value + '%',
                                    backgroundColor: passwordStrengthColor.value
                                }
                            })
                        ]),
                        h('div', { class: 'requirements' }, '至少8个字符，建议包含大小写字母、数字和符号')
                    ]),
                    h('h3', '站点信息'),
                    h('div', { class: 'form-group' }, [
                        h('label', '网站标题'),
                        h('input', {
                            type: 'text',
                            value: siteForm.value.site_title,
                            onInput: (e: Event) => {
                                siteForm.value.site_title = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            disabled: loading.value,
                            placeholder: '网站标题'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '网站介绍'),
                        h('textarea', {
                            value: siteForm.value.site_description,
                            onInput: (e: Event) => {
                                siteForm.value.site_description = (e.target as HTMLTextAreaElement).value;
                            },
                            disabled: loading.value,
                            placeholder: '网站介绍',
                            rows: 4,
                            style: 'width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; font-family: inherit;'
                        })
                    ]),
                    h('div', { style: 'display: flex; gap: 10px;' }, [
                        h('button', {
                            type: 'button',
                            onClick: handleBack,
                            disabled: loading.value,
                            class: 'btn',
                            style: 'background: #95a5a6; flex: 1;'
                        }, loading.value ? '处理中...' : '返回上一步'),
                        h('button', {
                            type: 'submit',
                            disabled: loading.value,
                            class: 'btn',
                            style: 'flex: 2;'
                        }, loading.value ? '安装中...' : '开始安装')
                    ])
                ])
            ]);
        };

        // 管理员账号步骤组件
        const AdminStep = () => {
            return h('div', { class: 'install-container' }, [
                h('div', { class: 'install-header' }, [
                    h('h1', '系统安装向导'),
                    h('p', '创建管理员账号')
                ]),
                error.value ? h('div', { class: 'error' }, error.value) : null,
                success.value ? h('div', { class: 'success' }, success.value) : null,
                h('form', {
                    onSubmit: (e: Event) => {
                        e.preventDefault();
                        handleAdminSubmit();
                    }
                }, [
                    h('h3', '管理员账号'),
                    h('div', { class: 'form-group' }, [
                        h('label', '用户名'),
                        h('input', {
                            type: 'text',
                            value: siteForm.value.username,
                            onInput: (e: Event) => {
                                siteForm.value.username = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            disabled: loading.value,
                            placeholder: 'admin'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '邮箱'),
                        h('input', {
                            type: 'email',
                            value: siteForm.value.email,
                            onInput: (e: Event) => {
                                siteForm.value.email = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            disabled: loading.value,
                            placeholder: 'admin@example.com'
                        })
                    ]),
                    h('div', { class: 'form-group' }, [
                        h('label', '密码'),
                        h('input', {
                            type: 'password',
                            value: siteForm.value.password,
                            onInput: (e: Event) => {
                                siteForm.value.password = (e.target as HTMLInputElement).value;
                            },
                            required: true,
                            minLength: 8,
                            disabled: loading.value,
                            placeholder: '至少8个字符'
                        }),
                        h('div', { class: 'password-strength' }, [
                            h('div', {
                                class: 'password-strength-bar',
                                style: {
                                    width: passwordStrength.value + '%',
                                    backgroundColor: passwordStrengthColor.value
                                }
                            })
                        ]),
                        h('div', { class: 'requirements' }, '至少8个字符，建议包含大小写字母、数字和符号')
                    ]),
                    h('div', { style: 'display: flex; gap: 10px;' }, [
                        h('button', {
                            type: 'button',
                            onClick: handleBack,
                            disabled: loading.value,
                            class: 'btn',
                            style: 'background: #95a5a6; flex: 1;'
                        }, loading.value ? '处理中...' : '返回上一步'),
                        h('button', {
                            type: 'submit',
                            disabled: loading.value,
                            class: 'btn',
                            style: 'flex: 2;'
                        }, loading.value ? '安装中...' : '开始安装')
                    ])
                ])
            ]);
        };

        // 组件挂载时获取 Token
        // 获取已选择的模式
        const fetchMode = async () => {
            try {
                const response = await fetch('/anon/install/api/get-mode');
                const data: ApiResponse<{ mode: string | null }> = await response.json();
                if (data.code === 200 && data.data.mode) {
                    selectedMode.value = data.data.mode;
                    // 如果已设置模式，直接跳转到数据库配置步骤
                    currentStep.value = 'database';
                }
            } catch (e: any) {
                // 忽略错误，继续显示模式选择
            }
        };

        onMounted(() => {
            fetchToken();
            fetchMode();
        });

        // 覆盖确认步骤组件
        const OverwriteStep = () => {
            return h('div', { class: 'install-container' }, [
                h('div', { class: 'install-header' }, [
                    h('h1', '系统安装向导'),
                    h('p', '检测到已存在的表')
                ]),
                error.value ? h('div', { class: 'error' }, error.value) : null,
                h('div', { class: 'form-group' }, [
                    h('p', { style: 'color: #e74c3c; margin-bottom: 15px;' }, '警告：检测到以下表已存在于数据库中：'),
                    h('ul', { style: 'list-style: none; padding: 0; margin: 0 0 20px 0;' }, existingTables.value.map((table: string) =>
                        h('li', { style: 'padding: 8px; background: #f8f9fa; margin-bottom: 5px; border-radius: 4px;' }, table)
                    )),
                    h('p', { style: 'color: #e74c3c; margin-bottom: 20px;' }, '如果继续安装，这些表将被删除并重新创建，所有数据将丢失！')
                ]),
                h('div', { style: 'display: flex; gap: 10px;' }, [
                    h('button', {
                        type: 'button',
                        onClick: () => handleOverwriteConfirm(false),
                        disabled: loading.value,
                        class: 'btn',
                        style: 'background: #95a5a6; flex: 1;'
                    }, '取消'),
                    h('button', {
                        type: 'button',
                        onClick: () => handleOverwriteConfirm(true),
                        disabled: loading.value,
                        class: 'btn',
                        style: 'background: #e74c3c; flex: 1;'
                    }, loading.value ? '处理中...' : '确认覆盖安装')
                ])
            ]);
        };

        // 根据当前步骤返回对应组件
        return () => {
            if (currentStep.value === 'mode') {
                return ModeSelectStep();
            } else if (currentStep.value === 'database') {
                return DatabaseStep();
            } else if (currentStep.value === 'overwrite') {
                return OverwriteStep();
            } else if (currentStep.value === 'site') {
                return SiteStep();
            } else if (currentStep.value === 'admin') {
                return AdminStep();
            }
            return ModeSelectStep();
        };
    }
};
