<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
?>
</main>

<footer class="border-t border-base-300 bg-base-100 mt-auto">
  <div class="container mx-auto max-w-3xl px-4 py-6">
    <div class="flex flex-col items-center justify-center gap-2 text-sm text-base-content/60">
      <p>&copy; <?php echo date('Y'); ?> <?php $this->options()->get('title', true); ?></p>
      <p>Powered by <?php echo Anon_Common::NAME; ?> v<?php echo Anon_Common::VERSION; ?></p>
      
      <?php 
      // 显示备案信息
      $icpNumber = $this->theme()->get('icp_number', '');
      $policeNumber = $this->theme()->get('police_number', '');
      
      if (!empty($icpNumber) || !empty($policeNumber)) :
      ?>
      <div class="flex flex-wrap items-center justify-center gap-3 mt-2">
        <?php if (!empty($icpNumber)) : ?>
          <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer" class="hover:text-base-content transition-colors">
            <?php echo $this->escape($icpNumber); ?>
          </a>
        <?php endif; ?>
        
        <?php if (!empty($policeNumber)) : ?>
          <a href="http://www.beian.gov.cn/portal/registerSystemInfo" target="_blank" rel="noopener noreferrer" class="hover:text-base-content transition-colors flex items-center gap-1">
            <?php echo $this->escape($policeNumber); ?>
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</footer>

  </div>
  <div class="drawer-side z-20">
    <label for="nav-drawer" class="drawer-overlay" aria-label="关闭菜单"></label>
    <ul class="menu p-4 w-64 min-h-full bg-base-100 text-base-content">
      <li class="menu-title">导航</li>
      <?php
      $navbarLinks = $this->theme()->get('navbar_links', []);
      if (empty($navbarLinks)) {
          $navbarLinks = ['首页|' . $this->siteUrl()];
      }
      
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
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
          </svg>
          切换主题
        </button>
      </li>
    </ul>
  </div>
</div>

<?php 
    // 开发模式下 main.js 已由 Vite HMR 加载
    if (!$this->isViteDevMode()) {
        $this->assets('main.js', null, ['defer' => 'defer']);
    }
    $this->footMeta();
?>
</body>
</html>
