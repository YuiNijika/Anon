<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$post = $this->post();
$this->components('head');
?>

<div class="card bg-base-100 shadow-md mb-4">
  <div class="card-body">
    <div class="mb-4">
      <span class="badge badge-outline">文章</span>
      <h1 class="text-3xl font-bold mt-3 mb-2"><?php echo $this->escape($post->title()); ?></h1>
      <p class="text-sm text-base-content/60">发布于 <?php echo $post->date('Y-m-d H:i'); ?></p>
    </div>
    <div class="prose max-w-none">
      <?php echo $this->markdown($post->content()); ?>
    </div>
  </div>
</div>

<?php 
$this->components('comments'); 
$this->components('foot'); 
?>
