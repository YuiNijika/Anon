<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$this->components('head');
$page = $this->page();
?>

<div class="card bg-base-100 shadow-md">
  <div class="card-body">
    <?php if ($page) { ?>
      <div class="mb-4">
        <span class="badge badge-outline">页面</span>
        <h1 class="text-3xl font-bold mt-3 mb-2"><?php echo $this->escape($page->title()); ?></h1>
        <p class="text-sm text-base-content/60">更新于 <?php echo date('Y-m-d H:i', $page->modified()); ?></p>
      </div>
      <div class="prose max-w-none">
        <?php echo $this->markdown($page->content()); ?>
      </div>
    <?php } ?>
  </div>
</div>

<?php $this->components('foot'); ?>
