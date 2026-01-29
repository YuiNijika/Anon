<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

const Anon_PageMeta = [
    'title' => '测试页',
    'description' => '这是一个测试页面，用于演示自动路由功能。',
    'keywords' => ['测试', '路由', 'Anon'],
];
?>
<?php $this->components('head'); ?>

<main>
    <h1>这是测试页面</h1>
    <p>这个页面位于 <code>Pages/Test.php</code>，会自动注册为 <code>/test</code> 路由。</p>
    
    <div class="card mt-2">
        <div class="card-title">自动路由说明</div>
        <div class="card-content">
            <p>Pages 目录下的 PHP 文件会自动注册为路由：</p>
            <ul>
                <li><code>Pages/Test.php</code> → <code>/test</code></li>
                <li><code>Pages/About/Index.php</code> → <code>/about</code> 和 <code>/about/index</code></li>
                <li><code>Pages/Contact.php</code> → <code>/contact</code></li>
            </ul>
            <p class="mt-2">使用 <code>const Anon_PageMeta</code> 来定义页面的 SEO 信息。</p>
        </div>
    </div>
</main>

<?php $this->components('foot'); ?>
