<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_PageMeta = [
    'title' => '页面 - ' . ($slug ?? ''),
    'description' => '这是页面详情页，通过路由指向配置加载。',
    'keywords' => ['页面', 'Anon'],
];
?>
<?php Anon_Cms_Theme::components('head'); ?>

<main>
    <article>
        <h1>页面 Slug: <?php echo Anon_Cms_Theme::escape($slug ?? ''); ?></h1>
        <div class="meta">
            <span>更新时间：<?php echo date('Y-m-d H:i:s'); ?></span>
        </div>
        <div class="content">
            <p>这是页面详情页，通过路由指向配置加载。</p>
            <p>您可以在 <code>app/Theme/Default/Page.php</code> 中自定义此页面。</p>
        </div>
    </article>
</main>

<?php Anon_Cms_Theme::components('foot'); ?>
