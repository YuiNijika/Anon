<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$icp = $this->options()->get('icp_code', null, false);
?>
</main>

<footer class="border-t border-base-300 bg-base-100 mt-auto">
  <div class="container mx-auto max-w-4xl px-4 py-6">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-sm text-base-content/60">
      <p>Powered by <?php echo Anon_Common::NAME; ?> v<?php echo Anon_Common::VERSION; ?></p>
      <p>
        &copy; <?php echo date('Y'); ?> <?php $this->options()->get('title', true); ?>
        <?php if (!empty($icp)) { ?> · <a href="http://www.beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer" class="text-base-content/60"><?php echo $icp; ?></a><?php } ?>
    </p>
    </div>
  </div>
</footer>

  </div>
  <div class="drawer-side z-20">
    <label for="nav-drawer" class="drawer-overlay" aria-label="关闭菜单"></label>
    <ul class="menu p-4 w-64 min-h-full bg-base-100 text-base-content">
      <li class="menu-title">导航</li>
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
                  echo '<li><a href="' . $this->escape($url) . '" class="text-base">' . $this->escape($title) . '</a></li>';
              }
          }
          ?>
      <li class="pt-2">
        <button id="theme-toggle-drawer" class="btn btn-ghost btn-sm gap-2 w-full justify-start" type="button" aria-label="切换主题">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
          </svg>
          切换主题
        </button>
      </li>
    </ul>
  </div>
</div>

<?php 
    // JS 延迟加载
    $this->assets('main.js', null, ['defer' => 'defer']);
    $this->footMeta();
?>
</body>
</html>
