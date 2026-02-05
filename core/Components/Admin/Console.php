<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

function meta()
{
  $dev = false;
  if ($dev) {
    return '?nocache=1';
  }
  return '?ver=' . Anon_Common::VERSION;
}
?>
<!doctype html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AnonEcho</title>
  <script type="module" crossorigin src="/anon/static/admin/js<?php echo meta(); ?>"></script>
  <link rel="stylesheet" crossorigin href="/anon/static/admin/css<?php echo meta(); ?>">
</head>

<body>
  <div id="root"></div>
</body>

</html>