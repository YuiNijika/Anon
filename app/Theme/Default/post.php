<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$post = $this->postIfExists();
if (!$post) {
    Anon_Cms_Theme::render('error', ['code' => 404, 'message' => '文章不存在或已被删除']);
    return;
}
$this->components('head');
?>

<div class="card bg-base-100 shadow-md">
  <div class="card-body">
    <?php if ($post) { ?>
      <div class="mb-4">
        <span class="badge badge-outline">文章</span>
        <h1 class="text-3xl font-bold mt-3 mb-2"><?php echo $this->escape($post->title()); ?></h1>
        <p class="text-sm text-base-content/60">发布于 <?php echo $post->date('Y-m-d H:i'); ?></p>
      </div>
      <div class="prose max-w-none">
        <?php echo $this->markdown($post->content()); ?>
            </div>
    <?php } ?>
            </div>
        </div>

<?php $this->components('foot'); ?>
