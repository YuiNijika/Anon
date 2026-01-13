<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 定义页面元数据
const Anon_PageMeta = [
    'title' => '文章详情 - ' . ($id ?? ''),
    'description' => '这是文章详情页，通过路由指向配置加载。',
    'keywords' => ['文章', '详情', 'Anon'],
];
?>
<?php Anon_Cms_Theme::components('head'); ?>

<main>
    <article>
        <h1>文章 ID: <?php echo Anon_Cms_Theme::escape($id ?? ''); ?></h1>
        <div class="meta">
            <span>发布时间：<?php echo date('Y-m-d H:i:s'); ?></span>
        </div>
        <div class="content">
            <p>这是文章详情页，通过路由指向配置加载。</p>
            <p>您可以在 <code>app/Theme/Default/Post.php</code> 中自定义此页面。</p>
        </div>
    </article>
</main>

<?php Anon_Cms_Theme::components('foot'); ?>
