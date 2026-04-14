<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$this->components('head');
?>

<div class="card bg-base-100 border border-base-300/50">
  <div class="card-body p-8 text-center">
    <div class="text-6xl mb-4">😕</div>
    <h1 class="text-2xl font-bold mb-2">页面未找到</h1>
    <p class="text-base-content/60 mb-6">抱歉，您访问的页面不存在</p>
    <a href="/" class="btn btn-primary">返回首页</a>
  </div>
</div>

<?php $this->components('foot'); ?>
