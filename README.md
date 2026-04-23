# Anon Framework Next

> 一个简洁优雅的 PHP `API&CMS` 开发框架
>
> 当然你也可以直接安装`CMS`模式把它当作博客系统来使用。

大家好我是YuiNijika，这个框架的作者。

起初框架的定位为`一个简单快速的 PHP API 开发框架`，后面感觉有必要就扩展了`CMS系统`。一些地方还是下了些功夫的！在开发时借鉴了Nuxtjs&Typecho的一些思路所以开发体验和其他框架可能略有不同~

[快速开始](https://yuinijika.github.io/Anon/)

## 快速启动

### 使用内置开发服务器

```bash
# 启动开发服务器 (默认 localhost:8000)
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

📚 **更多详细信息请查看**：[CLI 命令行系统文档](https://yuinijika.github.io/Anon/guide/cli-system.html)

## PHP 版本要求

PHP 7.4 - 8+

> **注意**：当前分支代码为开发版，生产环境请使用[稳定版](https://github.com/YuiNijika/Anon/releases)

---

## 📄 许可证

MIT License

Copyright (c) 2024-2026 鼠子(YuiNijika)

