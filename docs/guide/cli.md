# Anon Framework 命令行工具

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

### Composer 脚本

```bash
# 启动开发服务器
composer serve

# 启动开发服务器 (监听所有接口)
composer serve:dev

# 查看版本
composer anon:version
```

## 可用命令

| 命令 | 说明 | 示例 |
|------|------|------|
| `run` | 启动内置开发服务器 | `php anon run` |
| `version` | 显示版本信息 | `php anon version` |
| `help` | 显示帮助信息 | `php anon help` |

## 运行选项

`run` 命令支持以下选项:

- `--host=<hostname>` - 指定服务器主机 (默认: localhost)
- `--port=<port>` - 指定服务器端口 (默认: 8000)
- `--root=<path>` - 指定网站根目录 (默认: ./run)

## 使用场景

### 本地开发

```bash
php anon run
```
访问: http://localhost:8000

### 局域网测试

```bash
php anon run --host=0.0.0.0 --port=8080
```
其他设备可以通过你的 IP 地址访问,例如: http://192.168.1.100:8080

### 自定义端口

```bash
php anon run --port=3000
```
访问: http://localhost:3000

## 注意事项

1. **PHP 版本要求**: PHP 7.4 或更高版本
2. **内置服务器**: 使用 PHP 内置的发展服务器,不适合生产环境
3. **停止服务器**: 按 `Ctrl+C` 停止服务器
4. **生产环境**: 请使用 Nginx 或 Apache 等专业的 Web 服务器

## 故障排除

### 端口被占用

如果端口已被占用,请更换端口:
```bash
php anon run --port=8081
```

### 权限问题

在 Linux/Mac 上,如果使用 1024 以下的端口可能需要 root 权限:
```bash
sudo php anon run --port=80
```

### 找不到命令

确保在项目根目录下执行命令,并且 `php` 已在系统 PATH 中。
