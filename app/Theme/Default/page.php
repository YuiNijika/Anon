<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$this->components('head');
?>

<div class="card bg-base-100 shadow-md">
  <div class="card-body">
    <div class="mb-4">
      <span class="badge badge-outline">页面</span>
      <h1 class="text-3xl font-bold mt-3 mb-2">
        <?php echo $this->escape($this->page()['title'] ?? ''); ?>
      </h1>
      <p class="text-sm text-base-content/60">
        更新于 <?php echo date('Y-m-d H:i', strtotime($this->page()['updated_at'] ?? 'now')); ?>
      </p>
    </div>
    <div class="prose max-w-none">
      <?php echo $this->markdown($this->page()['content'] ?? ''); ?>
    </div>
  </div>
</div>

<?php $this->components('foot'); ?>
