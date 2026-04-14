import '../assets/style.css';
import NProgress from 'nprogress';
import Prism from 'prismjs';
import 'prismjs-components-importer/esm';

// 导入 PrismJS 插件
import 'prismjs/plugins/toolbar/prism-toolbar.min.js';
import 'prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js';

declare global {
    interface Window {
        Anon?: any;
    }
}

NProgress.configure({
    easing: 'ease',
    speed: 300,
    showSpinner: false,
    trickleSpeed: 150,
    minimum: 0.1,
    parent: 'body',
});

function initThemeToggle() {
    const themeToggles = document.querySelectorAll('#theme-toggle, #theme-toggle-drawer');
    if (!themeToggles.length) return;

    function getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    const sunPath = 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z';
    const moonPath = 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z';

    function setTheme(theme: string) {
        document.documentElement.setAttribute('data-theme', theme);
        try {
            localStorage.setItem('theme', theme);
        } catch (e) { }
        updateThemeIcons(theme);
    }

    function updateThemeIcons(theme: string) {
        const d = theme === 'dim' ? sunPath : moonPath;
        themeToggles.forEach((el) => {
            const path = el.querySelector('svg path');
            if (path) path.setAttribute('d', d);
        });
    }

    function toggleTheme() {
        // 显示主题选择对话框
        showThemeSelector();
    }

    function showThemeSelector() {
        const themes = [
            { value: 'light', label: '浅色' },
            { value: 'dark', label: '深色' },
            { value: 'cupcake', label: '奶油' },
            { value: 'bumblebee', label: '蜜蜂' },
            { value: 'emerald', label: '翡翠' },
            { value: 'corporate', label: '商务' },
            { value: 'synthwave', label: '合成波' },
            { value: 'retro', label: '复古' },
            { value: 'cyberpunk', label: '赛博朋克' },
            { value: 'valentine', label: '情人节' },
            { value: 'halloween', label: '万圣节' },
            { value: 'garden', label: '花园' },
            { value: 'forest', label: '森林' },
            { value: 'aqua', label: '水色' },
            { value: 'lofi', label: '低保真' },
            { value: 'pastel', label: '粉彩' },
            { value: 'fantasy', label: '奇幻' },
            { value: 'wireframe', label: '线框' },
            { value: 'black', label: '黑色' },
            { value: 'luxury', label: '奢华' },
            { value: 'dracula', label: '德古拉' },
            { value: 'cmyk', label: 'CMYK' },
            { value: 'autumn', label: '秋天' },
            { value: 'business', label: '商业' },
            { value: 'acid', label: '酸性' },
            { value: 'lemonade', label: '柠檬水' },
            { value: 'night', label: '夜晚' },
            { value: 'coffee', label: '咖啡' },
            { value: 'winter', label: '冬天' },
            { value: 'dim', label: '昏暗' },
            { value: 'nord', label: '北欧' },
            { value: 'sunset', label: '日落' },
        ];

        const currentTheme = getCurrentTheme();
        
        // 创建对话框
        const dialog = document.createElement('dialog');
        dialog.className = 'modal modal-open';
        
        const dialogContent = `
            <div class="modal-box max-w-2xl">
                <h3 class="font-bold text-lg mb-4">选择主题</h3>
                <div class="grid grid-cols-3 gap-2 max-h-96 overflow-y-auto">
                    ${themes.map(theme => `
                        <button 
                            class="btn btn-sm ${theme.value === currentTheme ? 'btn-primary' : 'btn-outline'}" 
                            data-theme-value="${theme.value}"
                        >
                            ${theme.label}
                        </button>
                    `).join('')}
                </div>
                <div class="modal-action">
                    <button class="btn btn-sm" id="close-theme-dialog">关闭</button>
                </div>
            </div>
        `;
        
        dialog.innerHTML = dialogContent;
        document.body.appendChild(dialog);
        dialog.showModal();
        
        // 绑定事件
        dialog.querySelectorAll('[data-theme-value]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const theme = (e.target as HTMLElement).getAttribute('data-theme-value');
                if (theme) {
                    setTheme(theme);
                    dialog.close();
                    dialog.remove();
                }
            });
        });
        
        document.getElementById('close-theme-dialog')?.addEventListener('click', () => {
            dialog.close();
            dialog.remove();
        });
        
        // 点击背景关闭
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) {
                dialog.close();
                dialog.remove();
            }
        });
    }

    function initTheme() {
        const defaultTheme = getCurrentTheme(); // HTML 上的默认值（后台配置）
        let saved = null;
        try {
            saved = localStorage.getItem('theme');
        } catch (e) { }
        
        if (saved) {
            // 用户之前选择过，使用用户选择
            setTheme(saved);
        } else {
            // 首次访问，保存后台默认值
            localStorage.setItem('theme', defaultTheme);
            updateThemeIcons(defaultTheme);
        }
    }

    themeToggles.forEach((el) => el.addEventListener('click', toggleTheme));
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
}

function updateAnonConfig(doc: Document) {
    const scripts = doc.querySelectorAll('script');
    for (let i = 0; i < scripts.length; i++) {
        const script = scripts[i];
        const content = script.textContent || '';
        if (content.includes('window.Anon =')) {
            const match = content.match(/window\.Anon\s*=\s*(\{[\s\S]*?\});/);
            if (match && match[1]) {
                try {
                    window.Anon = JSON.parse(match[1]);
                } catch (e) {
                    console.error('PJAX: Failed to parse window.Anon', e);
                }
            }
            break;
        }
    }
}

function initPjax() {
    const main = document.querySelector('main');
    if (!main) return;

    document.body.addEventListener('click', (ev) => {
        const target = ev.target as HTMLElement;
        const a = target?.closest?.('a') as HTMLAnchorElement | null;
        if (!a || !a.href) return;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
        if (a.target === '_blank' || a.hasAttribute('download')) return;
        if (ev.ctrlKey || ev.metaKey || ev.shiftKey) return;
        try {
            const url = new URL(a.href);
            if (url.origin !== location.origin || url.pathname === location.pathname) return;
        } catch (e) {
            return;
        }

        ev.preventDefault();
        NProgress.start();
        fetch(a.href, {
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => {
                if (!r.ok) throw new Error(String(r.status));
                return r.text();
            })
            .then((html) => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newMain = doc.querySelector('main');
                const newTitle = doc.querySelector('title');
                if (!newMain) throw new Error('no main');

                updateAnonConfig(doc);

                main.innerHTML = newMain.innerHTML;
                if (newTitle) document.title = newTitle.textContent;
                history.pushState({ pjax: true }, '', a.href);
                window.scrollTo(0, 0);
                const navCb = document.getElementById('nav-drawer');
                if (navCb && navCb instanceof HTMLInputElement) navCb.checked = false;
                
                // 延迟初始化，确保 DOM 完全更新
                setTimeout(() => {
                    initPage();
                    NProgress.done();
                }, 50);
            })
            .catch(() => {
                NProgress.done();
                location.href = a.href;
            });
    });

    window.addEventListener('popstate', () => {
        NProgress.start();
        fetch(location.href, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.ok ? r.text() : Promise.reject())
            .then((html) => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newMain = doc.querySelector('main');
                const newTitle = doc.querySelector('title');
                if (newMain) {
                    updateAnonConfig(doc);

                    main.innerHTML = newMain.innerHTML;
                    if (newTitle) document.title = newTitle.textContent;
                    const navCb = document.getElementById('nav-drawer');
                    if (navCb && navCb instanceof HTMLInputElement) navCb.checked = false;
                    
                    // 延迟初始化，确保 DOM 完全更新
                    setTimeout(() => {
                        initPage();
                        NProgress.done();
                    }, 50);
                } else {
                    NProgress.done();
                }
            })
            .catch(() => {
                NProgress.done();
                location.reload();
            });
    });
}

function prefetchOnHover() {
    document.body.addEventListener('mouseover', (ev) => {
        const target = ev.target as HTMLElement;
        const a = target?.closest?.('a[href^="/"]') as HTMLAnchorElement | null;
        if (!a || a.dataset.prefetched) return;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#')) return;
        try {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = href;
            document.head.appendChild(link);
            a.dataset.prefetched = '1';
        } catch (e) { }
    }, { passive: true });
}

function closeDrawerOnNavClick() {
    const cb = document.getElementById('nav-drawer');
    if (!cb || !(cb instanceof HTMLInputElement)) return;
    document.querySelectorAll('.drawer-side a[href]').forEach((a) => {
        a.addEventListener('click', () => {
            cb.checked = false;
        });
    });
}

function initCodeHighlight() {
    // 清除旧的代码高亮和工具栏
    document.querySelectorAll('pre.code-toolbar').forEach((pre) => {
        pre.classList.remove('code-toolbar');
        const toolbar = pre.querySelector('.toolbar');
        if (toolbar) {
            toolbar.remove();
        }
    });
    
    // 重新高亮所有代码块
    Prism.highlightAll();
}

function initPage() {
    initCodeHighlight();
    const reloadComments = (window as unknown as { __anonInitComments?: () => void }).__anonInitComments;
    if (typeof reloadComments === 'function') reloadComments();
}

function run() {
    initThemeToggle();
    initPage();
    initPjax();
    prefetchOnHover();
    closeDrawerOnNavClick();
    
    // 测试 NProgress
    setTimeout(() => {
        console.log('NProgress test start');
        NProgress.start();
        setTimeout(() => {
            NProgress.done();
            console.log('NProgress test done');
        }, 1000);
    }, 500);
}

if (typeof requestIdleCallback !== 'undefined') {
    requestIdleCallback(run, { timeout: 1500 });
} else {
    setTimeout(run, 0);
}
