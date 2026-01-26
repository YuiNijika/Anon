<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$page = Anon_Cms::getPage();

Anon_Cms_Theme::components('head');
?>

<main>
    <article>
        <h1><?php echo Anon_Cms_Theme::escape($page['title'] ?? ''); ?></h1>
        <div class="meta">
            <span>更新时间：<?php echo date('Y-m-d H:i:s', strtotime($page['updated_at'] ?? 'now')); ?></span>
        </div>
        <div class="content">
            <?php echo $page['content'] ?? ''; ?>
        </div>
    </article>
</main>

<?php Anon_Cms_Theme::components('foot'); ?>
