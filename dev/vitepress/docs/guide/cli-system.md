# CLI 命令行系统

CLI（Command Line Interface）命令行系统允许开发者通过终端运行自定义命令，执行批处理任务、维护操作等。

::: tip 代码示例说明
本文档中的代码示例使用了简化的类名。在实际使用时，需要在文件顶部添加相应的 `use` 语句：

```php
use Anon\Modules\System\Console;
use Anon\Modules\Database\Database;
use Anon\Modules\System\Debug;
```
:::

## 快速开始

### 注册第一个命令

```php
<?php
use Anon\Modules\System\Console;

Console::command('hello', function($args) {
    Console::info('Hello, World!');
    return 0;
}, '输出问候语');
```

### 运行命令

```bash
php index.php hello
```

## 命令注册

### 基本用法

```php
<?php
use Anon\Modules\System\Console;

Console::command(
    'command:name',           // 命令名
    $handler,                 // 处理器
    '命令描述'                // 描述（可选）
);
```

### 处理器类型

#### 1. 闭包函数

```php
<?php
use Anon\Modules\System\Console;

Console::command('cache:clear', function($args) {
    Console::info('清除缓存...');
    
    // 清除缓存逻辑
    Cache::clear();
    
    Console::success('缓存已清除');
    return 0;
}, '清除所有缓存');
```

#### 2. 类方法

```php
class CacheClearCommand
{
    public function handle($args)
    {
        Console::info('清除缓存...');
        Cache::clear();
        Console::success('缓存已清除');
        return 0;
    }
}

Console::command('cache:clear', CacheClearCommand::class, '清除缓存');
```

#### 3. 可调用类

```php
class SendEmailCommand
{
    public function __invoke($args)
    {
        // 发送邮件逻辑
        return 0;
    }
}

Console::command('mail:send', SendEmailCommand::class, '发送邮件');
```

## 参数处理

### 获取参数

```php
<?php
use Anon\Modules\System\Console;

Console::command('user:create', function($args) {
    // php index.php user:create username email
    // $args = ['username', 'email']
    
    if (count($args) < 2) {
        Console::error('请提供用户名和邮箱');
        return 1;
    }
    
    $username = $args[0];
    $email = $args[1];
    
    // 创建用户逻辑
    
    Console::success("用户 {$username} 创建成功");
    return 0;
}, '创建新用户');
```

### 参数验证

```php
<?php
use Anon\Modules\System\Console;

Console::command('post:delete', function($args) {
    if (empty($args[0])) {
        Console::error('请提供文章 ID');
        return 1;
    }
    
    $postId = (int)$args[0];
    if ($postId <= 0) {
        Console::error('无效的文章 ID');
        return 1;
    }
    
    // 删除文章逻辑
    
    return 0;
}, '删除文章');
```

## 输出方法

### info() - 普通信息

```php
<?php
use Anon\Modules\System\Console;

Console::info('这是一条普通信息');
// 输出：这是一条普通信息
```

### success() - 成功信息（绿色）

```php
<?php
use Anon\Modules\System\Console;

Console::success('操作成功完成');
// 输出：绿色的"操作成功完成"
```

### error() - 错误信息（红色）

```php
<?php
use Anon\Modules\System\Console;

Console::error('发生错误');
// 输出：红色的"发生错误"
```

### warning() - 警告信息（黄色）

```php
Console::warning('这是一个警告');
// 输出：黄色的"这是一个警告"
```

### line() - 自定义输出

```php
Console::line('自定义输出内容');
Console::line(''); // 空行
```

## 完整示例

### 示例 1：数据库备份命令

```php
Console::command('db:backup', function($args) {
    Console::info('开始备份数据库...');
    
    try {
        $db = Database::getInstance();
        $config = $db->getConfig();
        
        $backupDir = ANON_ROOT . 'backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        // 执行 mysqldump
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $size = round(filesize($filepath) / 1024 / 1024, 2);
            Console::success("数据库备份成功：{$filename} ({$size}MB)");
            return 0;
        } else {
            Console::error('数据库备份失败');
            return 1;
        }
    } catch (Exception $e) {
        Console::error('备份失败：' . $e->getMessage());
        return 1;
    }
}, '备份数据库到 backups 目录');
```

### 示例 2：清理日志命令

```php
Console::command('logs:clean', function($args) {
    $days = isset($args[0]) ? (int)$args[0] : 30;
    
    Console::info("清理 {$days} 天前的日志...");
    
    $logDir = ANON_ROOT . 'logs/';
    if (!is_dir($logDir)) {
        Console::warning('日志目录不存在');
        return 0;
    }
    
    $count = 0;
    $files = scandir($logDir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $filepath = $logDir . $file;
        if (!is_file($filepath)) {
            continue;
        }
        
        $fileTime = filemtime($filepath);
        $daysOld = (time() - $fileTime) / 86400;
        
        if ($daysOld > $days) {
            unlink($filepath);
            $count++;
            Console::line("  已删除：{$file}");
        }
    }
    
    Console::success("清理完成，共删除 {$count} 个文件");
    return 0;
}, '清理旧日志文件 [days=30]');
```

### 示例 3：发送批量邮件命令

```php
class SendBulkEmailCommand
{
    public function handle($args)
    {
        if (empty($args[0])) {
            Console::error('请提供模板 ID');
            return 1;
        }
        
        $templateId = (int)$args[0];
        
        Console::info("开始发送模板 {$templateId} 的邮件...");
        
        // 获取需要发送的用户列表
        $users = $this->getUsersToSend();
        $total = count($users);
        $sent = 0;
        $failed = 0;
        
        foreach ($users as $index => $user) {
            try {
                $this->sendEmail($user, $templateId);
                $sent++;
                
                // 显示进度
                $percent = round(($index + 1) / $total * 100, 2);
                Console::line("\r进度：{$percent}% - 已发送：{$sent}/{$total}");
            } catch (Exception $e) {
                $failed++;
                Console::error("发送失败给 {$user['email']}: " . $e->getMessage());
            }
        }
        
        Console::line("");
        Console::success("发送完成");
        Console::line("成功：{$sent}, 失败：{$failed}");
        
        return $failed > 0 ? 1 : 0;
    }
    
    private function getUsersToSend()
    {
        // 获取用户逻辑
        return [];
    }
    
    private function sendEmail($user, $templateId)
    {
        // 发送邮件逻辑
    }
}

Console::command('mail:bulk', SendBulkEmailCommand::class, '批量发送邮件');
```

### 示例 4：生成站点地图命令

```php
Console::command('sitemap:generate', function($args) {
    Console::info('生成站点地图...');
    
    $db = Database::getInstance();
    
    // 获取所有文章
    $posts = $db->db('posts')
        ->where('status', '=', 'published')
        ->all();
    
    $xml = new DOMDocument('1.0', 'UTF-8');
    $urlset = $xml->appendChild($xml->createElement('urlset'));
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    
    // 添加首页
    $url = $urlset->appendChild($xml->createElement('url'));
    $loc = $url->appendChild($xml->createElement('loc'));
    $loc->nodeValue = Env::get('app.url', 'https://example.com');
    
    // 添加文章页
    foreach ($posts as $post) {
        $url = $urlset->appendChild($xml->createElement('url'));
        $loc = $url->appendChild($xml->createElement('loc'));
        $loc->nodeValue = Env::get('app.url') . '/post/' . $post['id'];
        
        $lastmod = $url->appendChild($xml->createElement('lastmod'));
        $lastmod->nodeValue = date('Y-m-d', strtotime($post['updated_at']));
    }
    
    $xml->formatOutput = true;
    $filepath = ANON_ROOT . 'sitemap.xml';
    $xml->save($filepath);
    
    Console::success("站点地图已生成：{$filepath}");
    return 0;
}, '生成 XML 站点地图');
```

## 别名注册

为常用命令注册简短的别名：

```php
Console::command('database:backup', $handler, '备份数据库');
Console::alias('db:backup', 'database:backup');

Console::command('cache:clear', $handler, '清除缓存');
Console::alias('cc', 'cache:clear');
```

使用：

```bash
php index.php db:backup
php index.php cc
```

## 退出码

| 退出码 | 含义 |
|--------|------|
| 0 | 成功执行 |
| 1 | 一般错误 |
| 2 | 配置错误 |
| 3+ | 自定义错误 |

### 使用示例

```php
Console::command('critical-task', function($args) {
    try {
        // 关键任务
        return 0; // 成功
    } catch (ConfigException $e) {
        Console::error('配置错误：' . $e->getMessage());
        return 2;
    } catch (Exception $e) {
        Console::error('执行错误：' . $e->getMessage());
        return 1;
    }
});
```

## 帮助信息

运行不带参数的命令查看帮助：

```bash
php index.php
```

输出：

```
Anon Framework CLI

可用命令:
  hello - 输出问候语
  cache:clear - 清除所有缓存
  db:backup - 备份数据库
  logs:clean - 清理旧日志文件
```

## 最佳实践

### 1. 命令命名规范

使用 `:` 分隔命名空间：

```php
// ✅ 正确
'db:backup'
'cache:clear'
'mail:send'

// ❌ 错误
'backup_db'
'clearcache'
```

### 2. 错误处理

始终捕获并报告错误：

```php
Console::command('important-task', function($args) {
    try {
        // 业务逻辑
        Console::success('任务完成');
        return 0;
    } catch (Throwable $e) {
        Console::error('任务失败：' . $e->getMessage());
        Debug::error('CLI command failed', ['error' => $e->getMessage()]);
        return 1;
    }
});
```

### 3. 进度反馈

对耗时操作显示进度：

```php
$total = 100;
for ($i = 0; $i < $total; $i++) {
    // 处理逻辑
    
    $percent = round(($i + 1) / $total * 100, 2);
    Console::line("\r进度：{$percent}%");
}
```

### 4. 日志记录

记录命令执行情况：

```php
Console::command('daily-report', function($args) {
    Debug::info('Daily report started');
    
    try {
        // 执行报告生成
        Console::success('Report generated');
        Debug::info('Daily report completed');
        return 0;
    } catch (Exception $e) {
        Debug::error('Daily report failed', ['error' => $e->getMessage()]);
        return 1;
    }
});
```

## 相关文档

- [调试系统](debugging.md) - 调试与日志
- [数据库操作](api/database.md) - 数据库访问
- [缓存系统](cache-redis-guide.md) - Redis 缓存使用
