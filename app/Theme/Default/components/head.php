<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
$seo = Anon_Cms_PageMeta::getSeo();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <?php
    Anon_Cms_Theme::head($seo);
    Anon_Cms_Theme::assets('style.css');
    Anon_System_Hook::do_action('theme_head');
    ?>
</head>
<body>

<header class="site-header">
    <h1><?php echo Anon_Cms_Theme::escape($siteTitle ?? Anon_Common::NAME); ?></h1>
    <nav>
        <a href="/" class="<?php echo ($_SERVER['REQUEST_URI'] ?? '/') === '/' ? 'active' : ''; ?>">首页</a>
        <a href="/about">关于</a>
        <a href="/contact">联系</a>
    </nav>
</header>

<div class="container">
