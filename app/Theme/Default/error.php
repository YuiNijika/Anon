<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
const Anon_PageMeta = [
    'description' => '页面未找到',
    'robots' => 'noindex, nofollow',
];

// 获取错误信息，从外部变量传入 code 和 message
$errorInfo = Anon_Cms_PageMeta::getError([
    'code' => $code ?? 404,
    'message' => $message ?? '页面未找到',
]);
$statusCode = $errorInfo['code'];
$errorMessage = $errorInfo['message'];
$text = $errorInfo['text'];

// 传递错误信息给 head 组件
$seo = Anon_Cms_PageMeta::getSeo([
    'code' => $statusCode,
    'message' => $errorMessage,
    'title' => $errorInfo['title'],
    'description' => $errorMessage,
]);
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