<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$page = $this->page();
$this->components('head');
?>

<article class="card bg-base-100 border border-base-300/50">
    <div class="card-body p-6">
        <header class=" pb-4 border-b border-base-300/50">
            <h1 class="text-2xl font-bold"><?php echo $this->escape($page->title()); ?></h1>
            <time class="text-sm text-base-content/50">
                更新于 <?php echo date('Y-m-d H:i', $page->modified()); ?>
            </time>
        </header>

        <div class="prose prose-lg max-w-none 
      prose-headings:font-bold prose-headings:text-base-content
      prose-h1:text-2xl prose-h1:mt-8 prose-h1:mb-4
      prose-h2:text-xl prose-h2:mt-6 prose-h2:mb-3
      prose-h3:text-lg prose-h3:mt-5 prose-h3:mb-2
      prose-p:text-base-content/80 prose-p:leading-relaxed prose-p:my-4
      prose-a:text-primary prose-a:no-underline hover:prose-a:underline
      prose-strong:text-base-content prose-strong:font-semibold
      prose-code:text-primary prose-code:bg-base-200 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:text-sm
      prose-pre:bg-base-200 prose-pre:border prose-pre:border-base-300 prose-pre:rounded-lg
      prose-blockquote:border-l-4 prose-blockquote:border-primary prose-blockquote:bg-base-200/50 prose-blockquote:py-2 prose-blockquote:px-4 prose-blockquote:italic
      prose-ul:my-4 prose-ol:my-4 prose-li:my-2
      prose-img:rounded-lg prose-img:shadow-md prose-img:my-6
      prose-table:w-full prose-table:border-collapse
      prose-th:bg-base-200 prose-th:border prose-th:border-base-300 prose-th:px-4 prose-th:py-2 prose-th:font-semibold
      prose-td:border prose-td:border-base-300 prose-td:px-4 prose-td:py-2
      dark:prose-invert">
            <?php echo $this->markdown($page->content()); ?>
        </div>
    </div>
</article>

<?php $this->components('foot'); ?>