<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$post_count = (int)$this->options()->get('post_count', 15, false);
$posts = $this->posts($post_count);
$nav = $this->pageNav();
$showDateBadge = $this->theme()->get('show_date_badge', true);

$this->components('head');
?>

<div class="space-y-4">
    <?php if (empty($posts)) { ?>
        <div class="alert bg-base-100 shadow-sm">
            <span>暂无日志</span>
        </div>
    <?php } else { ?>
        <div class="space-y-3">
            <?php foreach ($posts as $p) { ?>
                <a href="<?php echo $this->permalink($p); ?>" class="block group">
                    <article class="card bg-base-100 hover:bg-base-200 transition-colors duration-200 border border-base-300/50 hover:border-primary/30">
                        <div class="card-body p-4">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="card-title text-base font-medium group-hover:text-primary transition-colors line-clamp-2">
                                    <?php echo $this->escape($p->title()); ?>
                                </h2>
                                <?php if ($showDateBadge) { ?>
                                    <time class="text-xs text-base-content/50 whitespace-nowrap flex-shrink-0">
                                        <?php echo $p->date('m-d'); ?>
                                    </time>
                                <?php } ?>
                            </div>
                            <p class="text-sm text-base-content/60 line-clamp-2 mt-1">
                                <?php echo $this->escape($p->excerpt(120)); ?>
                            </p>
                        </div>
                    </article>
                </a>
            <?php } ?>
        </div>

        <?php if ($nav) { ?>
            <div class="mt-8 flex justify-center gap-2">
                <?php if ($nav['prev']) { ?>
                    <a href="<?php echo $this->escape($nav['prev']['link']); ?>" class="btn btn-sm btn-outline">上一页</a>
                    <?php }
                foreach ($nav['pages'] as $page) {
                    if ($page['current']) { ?>
                        <span class="btn btn-sm btn-active"><?php echo $page['page']; ?></span>
                    <?php } else { ?>
                        <a href="<?php echo $this->escape($page['link']); ?>" class="btn btn-sm btn-ghost"><?php echo $page['page']; ?></a>
                    <?php }
                }
                if ($nav['next']) { ?>
                    <a href="<?php echo $this->escape($nav['next']['link']); ?>" class="btn btn-sm btn-outline">下一页</a>
                <?php } ?>
            </div>
    <?php }
    } ?>
</div>

<?php $this->components('foot'); ?>