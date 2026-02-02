<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<?php 
    $this->headMeta();
    $this->assets('style.css');
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
<body class="bg-base-200 flex flex-col">
<header class="sticky top-0 z-10 border-b border-base-300 bg-base-100/95 backdrop-blur">
  <div class="container mx-auto max-w-4xl px-4">
    <div class="navbar px-0 py-3">
      <div class="navbar-start">
        <a href="/" class="btn btn-ghost text-lg font-bold">
          <?php echo $this->escape($this->options()->get('title', Anon_Common::NAME)); ?>
        </a>
      </div>
      <div class="navbar-end">
        <ul class="menu menu-horizontal px-1 gap-2">
          <li><a href="/" class="btn btn-ghost btn-sm">首页</a></li>
          <li><a href="/about" class="btn btn-ghost btn-sm">关于</a></li>
          <li>
            <button id="theme-toggle" class="btn btn-ghost btn-sm" aria-label="切换主题" type="button" style="padding: 0.375rem;">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
              </svg>
            </button>
          </li>
        </ul>
      </div>
    </div>
  </div>
</header>

<main class="container mx-auto max-w-4xl px-4 py-8 flex-1" style="min-height: calc(100vh - 140px);">
