import { Fancybox } from '@fancyapps/ui';
import NProgress from 'nprogress';
import Prism from 'prismjs';
import 'prismjs-components-importer/esm';

NProgress.configure({
  easing: 'ease',
  speed: 400,
  showSpinner: true,
  trickleSpeed: 200,
  minimum: 0.4,
  parent: 'body',
});

function wrapProseImagesForFancybox() {
  const prose = document.querySelector('.prose');
  if (!prose) return;
  Array.from(prose.querySelectorAll('img')).forEach((img) => {
    if (!img.hasAttribute('loading')) {
      img.setAttribute('loading', 'lazy');
    }
    if (img.closest('a[data-fancybox]')) return;
    const href = img.currentSrc || img.getAttribute('src') || '';
    if (!href) return;
    const parent = img.parentNode;
    if (!parent) return;
    const a = document.createElement('a');
    a.href = href;
    a.setAttribute('data-fancybox', 'prose');
    parent.insertBefore(a, img);
    a.appendChild(img);
  });
}

function initLightbox() {
  Fancybox.destroy();
  wrapProseImagesForFancybox();
  Fancybox.bind('[data-fancybox="prose"]', {});
}

function initCodeHighlight() {
  Prism.highlightAll();
}

function initPage() {
  initLightbox();
  initCodeHighlight();
}

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
    const d = theme === 'dark' ? sunPath : moonPath;
    themeToggles.forEach((el) => {
      const path = el.querySelector('svg path');
      if (path) path.setAttribute('d', d);
    });
  }

  function toggleTheme() {
    setTheme(getCurrentTheme() === 'light' ? 'dark' : 'light');
  }

  function initTheme() {
    let saved = 'light';
    try {
      const stored = localStorage.getItem('theme');
      if (stored === 'light' || stored === 'dark') saved = stored;
    } catch (e) { }
    if (getCurrentTheme() !== saved) setTheme(saved);
    else updateThemeIcons(getCurrentTheme());
  }

  themeToggles.forEach((el) => el.addEventListener('click', toggleTheme));
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
  } else {
    initTheme();
  }
}

function initPjax() {
  const main = document.querySelector('main');
  if (!main) return;

  document.body.addEventListener('click', (ev) => {
    const a = (ev.target as HTMLElement | null)?.closest?.('a');
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
        main.innerHTML = newMain.innerHTML;
        if (newTitle) document.title = newTitle.textContent;
        history.pushState({ pjax: true }, '', a.href);
        initPage();
        window.scrollTo(0, 0);
        const navCb = document.getElementById('nav-drawer');
        if (navCb && navCb instanceof HTMLInputElement) navCb.checked = false;
        NProgress.done();
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
          main.innerHTML = newMain.innerHTML;
          if (newTitle) document.title = newTitle.textContent;
          initPage();
        }
        const navCb = document.getElementById('nav-drawer');
        if (navCb && navCb instanceof HTMLInputElement) navCb.checked = false;
        NProgress.done();
      })
      .catch(() => {
        NProgress.done();
        location.reload();
      });
  });
}

function prefetchOnHover() {
  document.body.addEventListener('mouseover', (ev) => {
    const a = (ev.target as HTMLElement | null)?.closest?.('a[href^="/"]') as HTMLAnchorElement | null | undefined;
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

function initWhenReady() {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
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

function run() {
  initThemeToggle();
  initPage();
  initPjax();
  prefetchOnHover();
  closeDrawerOnNavClick();
}

if (typeof requestIdleCallback !== 'undefined') {
  requestIdleCallback(initWhenReady, { timeout: 1500 });
} else {
  setTimeout(initWhenReady, 0);
}
