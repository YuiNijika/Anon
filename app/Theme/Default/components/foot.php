<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;
?>
</div>

<footer class="site-footer">
    <p>Powered by <?php echo Anon_Common::NAME; ?> v<?php echo Anon_Common::VERSION; ?></p>
    <p><?php echo date('Y'); ?> &copy; <?php echo Anon_Cms_Theme::escape($siteTitle ?? Anon_Common::NAME); ?>. All rights reserved.</p>
</footer>

<?php 
    Anon_Cms_Theme::assets('main.js');
    Anon_Cms_Theme::footMeta();
?>
</body>
</html>

