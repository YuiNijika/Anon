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

<?php Anon_Cms_Theme::components('head'); ?>

<div class="error-page">
    <div class="error-container">
        <h1><?php echo $statusCode; ?></h1>
        <p class="status-text"><?php echo Anon_Cms_Theme::escape($text); ?></p>
        <?php if (!empty($errorMessage)): ?>
            <p class="message"><?php echo Anon_Cms_Theme::escape($errorMessage); ?></p>
        <?php endif; ?>
        <div class="mt-3">
            <a href="/" class="btn">返回首页</a>
        </div>
    </div>
</div>

<?php Anon_Cms_Theme::components('foot'); ?>