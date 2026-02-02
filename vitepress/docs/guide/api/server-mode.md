# 服务端模式 (Swoole)

Anon Framework 内置了基于 Swoole 的服务端模式，支持 HTTP、TCP、WebSocket 等高性能服务，以及进程管理和定时任务功能。

::: warning 注意
服务端模式依赖 [Swoole](https://www.swoole.com/) 扩展。请确保你的 PHP 环境（仅限 Linux/macOS）已安装并启用 Swoole 扩展。Windows 环境下暂不支持（可使用 WSL 或 Docker）。
:::

## 快速开始

所有的服务启动都通过统一的命令行入口 `index.php` 进行管理。

```bash
# 基本语法
php server/index.php swoole [服务类型] [操作] [选项]
```

### 参数说明

- **服务类型**:
  - `http`: 启动 HTTP 服务器 (默认)
  - `tcp`: 启动 TCP 服务器
  - `websocket`: 启动 WebSocket 服务器
- **操作**:
  - `start`: 启动服务
  - `stop`: 停止服务
  - `reload`: 重载服务
- **选项**:
  - `--port=端口号`: 指定监听端口
  - `--host=主机名`: 指定监听地址 (默认 0.0.0.0)

## HTTP 服务

基于 Swoole 的高性能 HTTP 服务器，可以替代 Nginx+PHP-FPM 的传统架构。

```bash
# 启动 HTTP 服务 (默认端口 9501)
php server/index.php swoole http start

# 指定端口启动
php server/index.php swoole http start --port=8080
```

### 框架集成

HTTP 服务已通过输出缓冲 (Output Buffering) 技术与 Anon Framework 核心集成。

- 请求参数 (`$_GET`, `$_POST`, `$_SERVER` 等) 会自动从 Swoole 请求对象中注入。
- 框架的响应输出会被捕获并返回给客户端。

## TCP 服务

提供基础的 TCP 通信服务。

```bash
# 启动 TCP 服务 (默认端口 9502)
php server/index.php swoole tcp start
```

### 交互示例

```php
// 连接成功
客户端: 连接成功。
// 发送数据
服务端: 您发送的数据
// 连接断开
客户端: 连接关闭。
```

## WebSocket 服务

提供全双工 WebSocket 通信服务。

```bash
# 启动 WebSocket 服务 (默认端口 9503)
php server/index.php swoole websocket start
```

### 交互逻辑

- **Open**: 连接建立时会在控制台输出 `WebSocket 连接开启: {fd}`。
- **Message**: 收到消息时会回复 `服务端已收到: {消息内容}`。
- **Close**: 连接关闭时输出日志。

## 高级功能

### 进程管理 (Process)

模块内部封装了 `Swoole\Process`，支持创建和管理自定义子进程。

```php
// 使用示例 (在自定义代码中)
$manager = new Anon_Server_Driver_Swoole_Process();
$manager->add(function($process) {
    while(true) {
        // 执行后台任务
        sleep(1);
    }
});
$manager->startAll();
```

### 定时任务 (Crontab)

模块内部封装了 `Swoole\Timer`，支持毫秒级定时任务。

```php
// 使用示例
$crontab = new Anon_Server_Driver_Swoole_Crontab();
// 每秒执行一次
$crontab->add(1000, function() {
    echo "定时任务执行中...\n";
});
$crontab->start();
```

## 部署建议

1. **守护进程**: 生产环境建议结合 `Supervisor` 或 `Systemd` 管理服务进程。
2. **反向代理**: 建议在 Swoole 服务前部署 Nginx 作为反向代理，处理静态资源和 SSL 终端。
3. **开发环境**: Windows 用户请使用 WSL2 或 Docker 容器进行开发调试。
