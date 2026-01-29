</main>

<footer class="border-t border-base-300 bg-base-100 mt-auto">
  <div class="container mx-auto max-w-4xl px-4 py-6">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-sm text-base-content/60">
      <p>Powered by <?php echo Anon_Common::NAME; ?> v<?php echo Anon_Common::VERSION; ?></p>
      <p>&copy; <?php echo date('Y'); ?> <?php echo $this->escape($this->options()->get('title', Anon_Common::NAME)); ?></p>
    </div>
  </div>
</footer>

<?php 
    // JS 延迟加载
    $this->assets('main.js', null, ['defer' => 'defer']);
    $this->footMeta();
?>
</body>
</html>
