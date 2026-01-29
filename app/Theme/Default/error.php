<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_PageMeta = [
    'description' => '页面未找到',
    'robots' => 'noindex, nofollow',
];

$errorInfo = Anon_Cms_PageMeta::getError([
    'code' => $code ?? 404,
    'message' => $message ?? '页面未找到',
]);
$statusCode = $errorInfo['code'];
$errorMessage = $errorInfo['message'];
$text = $errorInfo['text'];
?>

<?php $this->components('head'); ?>

<div class="card bg-base-100 shadow-md">
  <div class="card-body text-center py-16">
    <h1 class="text-6xl font-bold text-error mb-4"><?php echo (int)$statusCode; ?></h1>
    <p class="text-xl text-base-content/70 mb-2"><?php echo $this->escape($text); ?></p>
    <?php if (!empty($errorMessage)): ?>
      <p class="text-sm text-base-content/60 mb-6"><?php echo $this->escape($errorMessage); ?></p>
    <?php endif; ?>
    <a href="/" class="btn btn-primary">返回首页</a>
  </div>
</div>

<?php $this->components('foot'); ?>
