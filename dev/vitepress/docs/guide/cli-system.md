# CLI 命令行系统

CLI（Command Line Interface）命令行系统提供了便捷的开发工具和命令执行功能，包括内置开发服务器、版本查看等。

## 快速开始

### 启动开发服务器

```bash
# 基本用法 (localhost:8000)
php anon run

# 指定端口
php anon run --port=8080

# 指定主机和端口
php anon run --host=0.0.0.0 --port=8080

# Windows 用户可以直接使用
anon run
```

### 其他命令

```bash
# 查看版本
php anon version

# 查看帮助
php anon help
```

### 使用 Composer 脚本

```bash
# 启动开发服务器
composer serve

# 启动开发服务器 (监听所有接口)
composer serve:dev

# 查看版本
composer anon:version
```

::: tip 提示
本文档介绍的是 Anon Framework 的命令行工具。如果你需要自定义 CLI 命令，请参考下方的「自定义命令」章节。
:::

## 开发服务器

### 基本用法

最简单的启动方式：

```bash
php anon run
```

这将在 `localhost:8000` 启动开发服务器。

### 自定义配置

#### 指定端口

```bash
php anon run --port=8080
```

#### 监听所有接口（局域网访问）

```bash
php anon run --host=0.0.0.0 --port=8080
```

其他设备可以通过你的 IP 地址访问，例如：`http://192.168.1.100:8080`

#### 自定义根目录

```bash
php anon run --root=./public
```

### 停止服务器

按 `Ctrl+C` 即可停止服务器。

### 使用场景

#### 本地开发

```bash
php anon run
# 访问: http://localhost:8000
```

#### 局域网测试

```bash
php anon run --host=0.0.0.0 --port=8080
# 其他设备可通过你的 IP 访问
```

#### 多项目同时运行

```bash
# 项目 1
php anon run --port=8000

# 项目 2
php anon run --port=8001
```

## 命令参考

| 命令 | 说明 | 示例 |
|------|------|------|
| `run` | 启动内置开发服务器 | `php anon run` |
| `version` | 显示版本信息 | `php anon version` |
| `help` | 显示帮助信息 | `php anon help` |

## 运行选项

`run` 命令支持以下选项：

| 选项 | 说明 | 默认值 |
|------|------|--------|
| `--host=<hostname>` | 指定服务器主机 | `localhost` |
| `--port=<port>` | 指定服务器端口 | `8000` |
| `--root=<path>` | 指定网站根目录 | `./run` |

## 注意事项

### PHP 版本要求

确保你的 PHP 版本 >= 7.4：

```bash
php -v
```

### 内置服务器限制

`php anon run` 使用的是 PHP 内置的发展服务器，它有以下特点：

- ✅ 适合本地开发和测试
- ✅ 支持热重载（修改代码后刷新即可）
- ❌ **不适合生产环境**
- ❌ 单线程，不支持高并发
- ❌ 性能不如 Nginx/Apache

**生产环境请使用专业的 Web 服务器（Nginx 或 Apache）**。

### 端口占用

如果端口已被占用，你会看到类似这样的错误：

```
Address already in use
```

解决方法：更换端口

```bash
php anon run --port=8081
```

### 权限问题

在 Linux/Mac 上，如果使用 1024 以下的端口可能需要 root 权限：

```bash
sudo php anon run --port=80
```

### 防火墙设置

如果需要从其他设备访问，请确保防火墙允许该端口的连接：

```bash
# Linux (ufw)
sudo ufw allow 8080/tcp

# Windows
# 在 Windows 防火墙中添加入站规则
```

## 故障排除

### 找不到命令

**问题**: 执行 `php anon run` 时提示找不到文件

**解决**: 确保在项目根目录下执行命令

```bash
cd /path/to/your/project
php anon run
```

### PHP 不在 PATH 中

**问题**: 提示 `php` 不是内部或外部命令

**解决**: 
- Windows: 将 PHP 安装目录添加到系统 PATH
- Linux/Mac: 确保 PHP 已正确安装

验证 PHP 是否可用：

```bash
php -v
```

### 服务器启动后立即退出

**问题**: 服务器启动后立即退出，没有错误信息

**解决**: 检查端口是否被占用

```bash
# Windows
netstat -ano | findstr :8000

# Linux/Mac
lsof -i :8000
```

### 访问返回 500 错误

**问题**: 浏览器访问显示 500 Internal Server Error

**解决**: 
1. 检查日志文件 `logs/php_error.log`
2. 确认数据库配置正确
3. 确认所有依赖已安装

```bash
# 查看最新日志
tail -f logs/php_error.log
```

### Composer 脚本不工作

**问题**: 执行 `composer serve` 无响应

**解决**: 确保在项目根目录下，并且 `composer.json` 存在

```bash
# 检查 composer.json 是否存在
ls composer.json

# 重新安装依赖
composer install
```

---

# 高级用法：自定义 CLI 命令

::: warning 注意
以下章节介绍如何创建自定义的 CLI 命令，这与 `php anon run` 开发服务器是不同的功能。
:::

CLI 命令行系统允许开发者通过终端运行自定义命令，执行批处理任务、维护操作等。

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
