<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

Anon_Cms_Theme::components('head'); 
?>

<main>
    <?php 
    if (isset($content) && !empty($content)) {
        echo $content;
    } else {
        echo '<h1>欢迎使用 Anon CMS</h1>';
        echo '<p>这是默认主题的首页模板。</p>';
        echo '<p>您可以在 <code>app/Theme/Default/Index.php</code> 中自定义此页面。</p>';
        echo '<div class="card mt-2">';
        echo '<div class="card-title">快速开始</div>';
        echo '<div class="card-content">';
        echo '<p>使用 <code>const Anon_PageMeta</code> 来定义 SEO 信息。</p>';
        echo '<p>使用 <code>Anon_Cms_Theme::components(\'head\')</code> 和 <code>Anon_Cms_Theme::components(\'foot\')</code> 来加载头部和底部。</p>';
        echo '</div>';
        echo '</div>';
    }
    ?>
</main>

<?php Anon_Cms_Theme::components('foot'); ?>
