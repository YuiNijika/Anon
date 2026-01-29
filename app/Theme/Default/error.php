<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$code = $this->get('code', 404);
$message = $this->get('message', '页面未找到');

const Anon_PageMeta = [
    'title' => '页面未找到',
    'description' => '页面未找到',
    'robots' => 'noindex, nofollow',
];

$statusText = [
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    500 => 'Internal Server Error'
];
$text = $statusText[$code] ?? 'Error';
?>

<?php $this->components('head'); ?>

<div class="card bg-base-100 shadow-md">
  <div class="card-body text-center py-16">
    <h1 class="text-6xl font-bold text-error mb-4"><?php echo (int)$code; ?></h1>
    <p class="text-xl text-base-content/70 mb-2"><?php echo $this->escape($text); ?></p>
    <?php if (!empty($message)): ?>
      <p class="text-sm text-base-content/60 mb-6"><?php echo $this->escape($message); ?></p>
    <?php endif; ?>
    <a href="/" class="btn btn-primary">返回首页</a>
  </div>
</div>

<?php $this->components('foot'); ?>