<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$this->components('head');

$post_count = (int)$this->options()->get('post_count', 10, false);
$posts = $this->posts($post_count);
$nav = $this->pageNav();
?>

<div class="space-y-6">
  <!-- 站点介绍 -->
  <div class="card bg-base-100 shadow-md">
    <div class="card-body">
      <h1 class="card-title text-4xl mb-2"><?php echo $this->options()->get('title', null, false); ?></h1>
        <p class="text-lg text-base-content/70 mb-2"><?php echo $this->options()->get('subtitle', null, false); ?></p>
    </div>
  </div>

  <!-- 文章列表 -->
  <?php if (empty($posts)) { ?>
    <div class="card bg-base-100 shadow-md">
      <div class="card-body text-center">
        <p class="text-base-content/60">暂无文章</p>
      </div>
    </div>
  <?php } else { ?>
    <div class="grid gap-4 sm:grid-cols-2">
      <?php foreach ($posts as $p) { ?>
        <a href="<?php echo $this->permalink($p); ?>" class="card bg-base-100 shadow-md hover:shadow-lg transition-shadow">
          <div class="card-body">
            <h2 class="card-title text-lg mb-2"><?php echo $this->escape($p->title()); ?></h2>
            <p class="text-sm text-base-content/70 line-clamp-2 mb-3"><?php echo $this->escape($p->excerpt(150)); ?></p>
            <div class="card-actions justify-between items-center">
              <span class="badge badge-ghost badge-sm"><?php echo $p->date('Y-m-d'); ?></span>
              <span class="text-xs text-base-content/50">阅读 →</span>
            </div>
          </div>
        </a>
      <?php } ?>
    </div>

    <!-- 分页导航 -->
    <?php if ($nav) { ?>
      <div class="mt-8 flex justify-center gap-2">
        <?php if ($nav['prev']) { ?>
          <a href="<?php echo $this->escape($nav['prev']['link']); ?>" class="btn btn-sm">上一页</a>
        <?php } foreach ($nav['pages'] as $page) { if ($page['current']) { ?>
            <span class="btn btn-sm btn-active"><?php echo $page['page']; ?></span>
          <?php } else { ?>
            <a href="<?php echo $this->escape($page['link']); ?>" class="btn btn-sm"><?php echo $page['page']; ?></a>
          <?php } } if ($nav['next']) { ?>
          <a href="<?php echo $this->escape($nav['next']['link']); ?>" class="btn btn-sm">下一页</a>
        <?php } ?>
      </div>
    <?php } } ?>
</div>

<?php $this->components('foot'); ?>
