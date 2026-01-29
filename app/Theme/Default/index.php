<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$this->components('head');
?>

<div class="space-y-6">
  <!-- 站点介绍 -->
  <?php
    $siteTitle = (string)$this->options()->get('title', Anon_Common::NAME);
    $subtitle = (string)$this->options()->get('subtitle', '');
    $siteDesc = (string)$this->options()->get('description', '');
  ?>
  <div class="card bg-base-100 shadow-md">
    <div class="card-body">
      <h1 class="card-title text-4xl mb-2">
        <?php echo $this->escape($siteTitle); ?>
      </h1>
      <?php if (!empty($subtitle)): ?>
        <p class="text-lg text-base-content/70 mb-2"><?php echo $this->escape($subtitle); ?></p>
      <?php endif; ?>
      <?php if (!empty($siteDesc)): ?>
        <p class="text-base-content/60"><?php echo $this->escape($siteDesc); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- 文章列表 -->
  <?php $posts = $this->posts(12); ?>
  <?php if (empty($posts)): ?>
    <div class="card bg-base-100 shadow-md">
      <div class="card-body text-center">
        <p class="text-base-content/60">暂无文章</p>
      </div>
    </div>
  <?php else: ?>
    <div class="grid gap-4 sm:grid-cols-2">
      <?php foreach ($posts as $p): ?>
        <a href="/post/<?php echo $p->id(); ?>" class="card bg-base-100 shadow-md hover:shadow-lg transition-shadow">
          <div class="card-body">
            <h2 class="card-title text-lg mb-2">
              <?php echo $this->escape($p->title()); ?>
            </h2>
            <p class="text-sm text-base-content/70 line-clamp-2 mb-3">
              <?php echo $this->escape($p->excerpt(150)); ?>
            </p>
            <div class="card-actions justify-between items-center">
              <span class="badge badge-ghost badge-sm">
                <?php echo $p->date('Y-m-d'); ?>
              </span>
              <span class="text-xs text-base-content/50">阅读 →</span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php $this->components('foot'); ?>
