<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="<?php $this->options()->get('color_scheme', 'light', true); ?>">
<head>
<?php 
    $this->headMeta();
    $this->assets('style.css');
    $this->assets('fancybox.css');
    $this->assets('prism-tomorrow.min.css');
    $this->assets('nprogress.css');
?>
<script>
  (function() {
    try {
      const saved = localStorage.getItem('theme');
      if (saved === 'light' || saved === 'dark') {
        document.documentElement.setAttribute('data-theme', saved);
      }
    } catch (e) {}
  })();
</script>
</head>
<body class="bg-base-200">
<div class="drawer">
  <input id="nav-drawer" type="checkbox" class="drawer-toggle" aria-label="导航菜单" />
  <div class="drawer-content flex flex-col min-h-screen">
<header class="sticky top-0 z-10 border-b border-base-300 bg-base-100/95 backdrop-blur">
  <div class="container mx-auto max-w-4xl px-4">
    <div class="navbar px-0 py-3">
      <div class="navbar-start">
        <a href="/" class="btn btn-ghost text-lg font-bold">
          <?php $this->options()->get('title', true) ?>
        </a>
      </div>
      <div class="navbar-end">
        <ul class="menu menu-horizontal px-1 gap-2 hidden md:flex">
          <?php
          // 从主题设置获取导航链接
          $navbarLinks = $this->theme()->get('navbar_links', []);
          
          // 如果为空，使用默认首页
          if (empty($navbarLinks)) {
              $navbarLinks = ['首页|' . $this->siteUrl()];
          }
          
          // 输出导航链接
          foreach ($navbarLinks as $link) {
              $parts = explode('|', $link, 2);
              $title = isset($parts[0]) ? trim($parts[0]) : '';
              $url = isset($parts[1]) ? trim($parts[1]) : '/';
              
              if (!empty($title)) {
                  echo '<li><a href="' . $this->escape($url) . '" class="btn btn-ghost btn-sm">' . $this->escape($title) . '</a></li>';
              }
          }
          ?>
          <li>
            <button id="theme-toggle" class="btn btn-ghost btn-sm" aria-label="切换主题" type="button" style="padding: 0.375rem;">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
              </svg>
            </button>
          </li>
        </ul>
        <label for="nav-drawer" class="btn btn-ghost btn-square drawer-button md:hidden" aria-label="打开菜单">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </label>
      </div>
    </div>
  </div>
</header>

<main class="container mx-auto max-w-4xl px-4 py-8 flex-1" style="min-height: calc(100vh - 140px);">
