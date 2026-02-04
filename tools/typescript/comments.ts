interface VueApp {
    createApp: (options: VueAppOptions) => VueAppInstance;
}

interface VueAppInstance {
    mount: (selector: string) => void;
}

interface VueAppOptions {
    data: () => CommentAppData;
    computed: Record<string, () => any>;
    mounted: () => void;
    methods: Record<string, (...args: any[]) => any>;
    template: string;
}

interface CommentAppData {
    postId: number;
    comments: Comment[];
    isLoggedIn: boolean;
    message: string;
    messageType: 'info' | 'error';
    loading: boolean;
    replyingTo: ReplyTarget | null;
    captchaEnabled: boolean;
    captchaImage: string;
    captchaLoading: boolean;
    form: CommentForm;
}

interface Comment {
    id: number;
    name: string;
    email?: string;
    url?: string;
    content: string;
    created_at: number | string;
    parent_id?: number | null;
    children?: Comment[];
}

interface ReplyTarget {
    id: number;
    name: string;
}

interface CommentForm {
    name: string;
    email: string;
    url: string;
    content: string;
    parent_id: number | null;
    captcha: string;
}

interface ApiResponse {
    code?: number;
    message?: string;
    data?: {
        comments?: Comment[];
    };
}

declare const Vue: VueApp;

(function () {
    function runCommentsApp(): void {
        const el = document.getElementById('comments-app');
        if (!el || typeof Vue === 'undefined' || !Vue.createApp) {
            return;
        }

        const postIdStr = el.getAttribute('data-post-id') || '0';
        const postId = parseInt(postIdStr, 10);
        if (!postId) {
            return;
        }

        const isLoggedIn = el.getAttribute('data-comment-logged-in') === '1';
        const template = el.innerHTML;
        el.innerHTML = '';

        Vue.createApp({
            data(): CommentAppData {
                return {
                    postId: postId,
                    comments: [],
                    isLoggedIn: isLoggedIn,
                    message: '',
                    messageType: 'info',
                    loading: false,
                    replyingTo: null,
                    captchaEnabled: false,
                    captchaImage: '',
                    captchaLoading: false,
                    form: {
                        name: '',
                        email: '',
                        url: '',
                        content: '',
                        parent_id: null,
                        captcha: '',
                    },
                };
            },
            computed: {
                topLevelComments(): Comment[] {
                    return this.comments || [];
                },
                totalCommentCount(): number {
                    const list = this.comments || [];
                    let n = 0;
                    for (let i = 0; i < list.length; i++) {
                        n += 1;
                        n += list[i].children && list[i].children.length ? list[i].children.length : 0;
                    }
                    return n;
                },
            },
            mounted(): void {
                this.loadComments();
                this.checkCaptchaEnabled();
            },
            methods: {
                setReplyTo(comment: Comment): void {
                    this.replyingTo = {
                        id: comment.id,
                        name: comment.name || '?',
                    };
                    this.form.parent_id = comment.id;
                },
                cancelReply(): void {
                    this.replyingTo = null;
                    this.form.parent_id = null;
                },
                formatDate(createdAt: number | string | null | undefined): string {
                    if (createdAt == null || createdAt === '') {
                        return '';
                    }
                    let t: number;
                    if (typeof createdAt === 'number') {
                        t = createdAt < 1e12 ? createdAt * 1000 : createdAt;
                    } else {
                        t = new Date(createdAt).getTime();
                    }
                    if (!t || isNaN(t)) {
                        return '';
                    }
                    const d = new Date(t);
                    const y = d.getFullYear();
                    const m = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    const h = String(d.getHours()).padStart(2, '0');
                    const min = String(d.getMinutes()).padStart(2, '0');
                    return `${y}-${m}-${day} ${h}:${min}`;
                },
                loadComments(): void {
                    const vm = this;
                    fetch(`/anon/cms/comments?post_id=${encodeURIComponent(this.postId)}`, {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    })
                        .then((r) => r.json())
                        .then((res: ApiResponse) => {
                            const data = res && res.data ? res.data : {};
                            vm.comments = data.comments || [];
                        })
                        .catch(() => {
                            vm.comments = [];
                        });
                },
                checkCaptchaEnabled(): void {
                    const vm = this;
                    if (vm.isLoggedIn) {
                        vm.captchaEnabled = false;
                        return;
                    }
                    fetch('/get-config', {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    })
                        .then((r) => r.json())
                        .then((res: any) => {
                            if (res && res.code === 200 && res.data && res.data.captcha) {
                                vm.captchaEnabled = true;
                                vm.loadCaptcha();
                            } else {
                                vm.captchaEnabled = false;
                            }
                        })
                        .catch(() => {
                            vm.captchaEnabled = false;
                        });
                },
                loadCaptcha(): void {
                    const vm = this;
                    if (vm.captchaLoading || !vm.captchaEnabled) {
                        return;
                    }
                    vm.captchaLoading = true;
                    fetch('/auth/captcha', {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    })
                        .then((r) => r.json())
                        .then((res: any) => {
                            vm.captchaLoading = false;
                            if (res && res.code === 200 && res.data && res.data.image) {
                                vm.captchaImage = res.data.image;
                                vm.form.captcha = '';
                            }
                        })
                        .catch(() => {
                            vm.captchaLoading = false;
                        });
                },
                refreshCaptcha(): void {
                    this.loadCaptcha();
                },
                submitComment(): void {
                    const vm = this;
                    this.message = '';
                    if (!this.form.content.trim()) {
                        this.messageType = 'error';
                        this.message = this.isLoggedIn
                            ? '请填写评论内容'
                            : '请填写名称、邮箱和评论内容';
                        return;
                    }
                    if (!this.isLoggedIn && (!this.form.name.trim() || !this.form.email.trim())) {
                        this.messageType = 'error';
                        this.message = '请填写名称、邮箱和评论内容';
                        return;
                    }
                    if (this.captchaEnabled && !this.form.captcha.trim()) {
                        this.messageType = 'error';
                        this.message = '请输入验证码';
                        return;
                    }
                    this.loading = true;
                    const params: Record<string, string> = {
                        post_id: String(this.postId),
                        content: this.form.content.trim(),
                        url: this.form.url.trim(),
                    };
                    if (this.form.parent_id) {
                        params.parent_id = String(this.form.parent_id);
                    }
                    if (!this.isLoggedIn) {
                        params.name = this.form.name.trim();
                        params.email = this.form.email.trim();
                    }
                    if (this.captchaEnabled) {
                        params.captcha = this.form.captcha.trim();
                    }
                    const body = new URLSearchParams(params);
                    fetch('/anon/cms/comments', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        credentials: 'same-origin',
                        body: body.toString(),
                    })
                        .then((r) => r.json())
                        .then((res: ApiResponse) => {
                            vm.loading = false;
                            if (res && res.code === 200) {
                                vm.messageType = 'info';
                                vm.message = res.message || '评论已提交，待审核通过后会显示。';
                                vm.form = {
                                    name: '',
                                    email: '',
                                    url: '',
                                    content: '',
                                    parent_id: null,
                                    captcha: '',
                                };
                                vm.replyingTo = null;
                                if (vm.captchaEnabled) {
                                    vm.loadCaptcha();
                                }
                                vm.loadComments();
                            } else {
                                vm.messageType = 'error';
                                vm.message = res && res.message ? res.message : '提交失败，请重试。';
                                if (vm.captchaEnabled && res && res.message && res.message.indexOf('验证码') !== -1) {
                                    vm.loadCaptcha();
                                }
                            }
                        })
                        .catch(() => {
                            vm.loading = false;
                            vm.messageType = 'error';
                            vm.message = '提交失败，请检查网络后重试。';
                        });
                },
            },
            template: template,
        }).mount('#comments-app');
    }

    runCommentsApp();

    (window as any).__anonInitComments = runCommentsApp;

    if (typeof document.addEventListener !== 'undefined') {
        document.addEventListener('pjax:complete', function () {
            const initFn = (window as any).__anonInitComments;
            if (typeof initFn === 'function') {
                setTimeout(function () {
                    initFn();
                }, 100);
            }
        });
    }
})();
