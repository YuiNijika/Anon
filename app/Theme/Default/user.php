<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$profile = $this->profileUser();
$this->components('head');
?>

<?php if ($profile === null) { ?>
  <div class="card bg-base-100 shadow-md">
    <div class="card-body text-center py-16">
      <p class="text-base-content/70">用户不存在</p>
      <a href="/" class="btn btn-primary mt-4">返回首页</a>
    </div>
  </div>
<?php } else { ?>
  <div class="card bg-base-100 shadow-md">
    <div class="card-body flex flex-col sm:flex-row items-center gap-6">
      <?php if ($profile->avatar() !== '') { ?>
        <img src="<?php echo $this->escape($profile->avatar()); ?>" alt="<?php echo $this->escape($profile->displayName()); ?>" class="w-24 h-24 rounded-full object-cover" />
      <?php } ?>
      <div class="flex-1 text-center sm:text-left">
        <h1 class="text-2xl font-bold"><?php echo $this->escape($profile->displayName()); ?></h1>
        <p class="text-base-content/60 text-sm mt-1">@<?php echo $this->escape($profile->name()); ?></p>
        <?php if ($profile->isCurrentUser()) { ?>
          <span class="badge badge-primary badge-sm mt-2">本人</span>
        <?php } ?>
      </div>
    </div>
  </div>
<?php } ?>

<?php $this->components('foot'); ?>