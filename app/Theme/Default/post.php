<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$post = Anon_Cms::getPost();

Anon_Cms_Theme::components('head');
?>

<main>
    <article>
        <h1><?php echo Anon_Cms_Theme::escape($post['title'] ?? ''); ?></h1>
        <div class="meta">
            <span>发布时间：<?php echo date('Y-m-d H:i:s', strtotime($post['created_at'] ?? 'now')); ?></span>
        </div>
        <div class="content">
            <?php echo $post['content'] ?? ''; ?>
        </div>
    </article>
</main>

<?php Anon_Cms_Theme::components('foot'); ?>
