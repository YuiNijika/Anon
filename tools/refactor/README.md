# Anon 框架重构工具

## 概述

自动化工具用于重构 `core/Modules/` 目录结构，将文件按功能分类到子目录。

## 使用方法

### 一键执行重构

```bash
cd server

# 演练模式，查看将要执行的操作
python tools/refactor/run.py --dry-run

# 实际执行重构
python tools/refactor/run.py
```

### 分步执行

```bash
# 只整理目录结构
python tools/refactor/organize_modules.py

# 只更新路径引用
python tools/refactor/update_paths.py
```

## 重构内容

将 `core/Modules/` 下的文件按功能整理到子目录：

```
core/Modules/
├── Auth/              # 认证模块
│   ├── Token.php
│   ├── Csrf.php
│   ├── Captcha.php
│   ├── RateLimit.php
│   └── Capability.php
│
├── Http/              # HTTP 模块
│   ├── Router.php
│   ├── RequestHelper.php
│   ├── ResponseHelper.php
│   └── Middleware.php
│
├── Database/          # 数据库模块
│   ├── QueryBuilder.php
│   ├── QueryOptimizer.php
│   ├── Sharding.php
│   └── SqlConfig.php
│
├── Security/          # 安全模块
│   └── Security.php
│
└── System/            # 系统模块
    ├── Config.php
    ├── Env.php
    ├── Container.php
    ├── Hook.php
    ├── Plugin.php
    ├── Exception.php
    ├── Install.php
    ├── Widget.php
    ├── Console.php
    └── Cache.php
```

## 工具说明

- `organize_modules.py` - 移动文件到功能子目录
- `update_paths.py` - 更新 require_once 路径引用
- `rename_classes.py` - 重命名类并创建兼容别名
- `update_docs.py` - 更新 VitePress 文档中的类名引用
- `run.py` - 一键执行全部操作
- `test_refactor.py` - 重构后功能测试

## 类名变更说明

重构后会创建新的类名，同时保留旧类名作为兼容别名：

### 新类名（推荐使用）

```php
// Http 模块
Anon_Http_Request::validate([...]);
Anon_Http_Response::success($data);
Anon_Http_Router::handle();

// Auth 模块
Anon_Auth_Token::generate([...]);
Anon_Auth_Csrf::generate();

// Database 模块
Anon_Database_QueryBuilder::select();
```

### 旧类名（仍然可用）

```php
// 旧代码仍然可以正常工作
Anon_RequestHelper::validate([...]);
Anon_ResponseHelper::success($data);
Anon_Token::generate([...]);
Anon_Cache::get('key');
```

### 兼容机制

通过 `core/Compatibility.php` 文件自动创建类别名：

```php
class_alias('Anon_Http_Request', 'Anon_RequestHelper');
class_alias('Anon_Http_Response', 'Anon_ResponseHelper');
// ... 更多别名
```

这样旧代码无需修改即可继续工作，新代码可以使用更清晰的新类名。

## 测试工具

### 运行测试

```bash
cd server
python tools/refactor/test_refactor.py
```

### 测试内容

1. **文件检查** - 验证所有文件是否在正确位置
2. **PHP 语法检查** - 验证 PHP 文件语法是否正确
3. **类加载测试** - 验证所有类是否能正常加载
4. **基本功能测试** - 验证核心功能是否正常
5. **旧文件检查** - 检查是否还有旧文件在根目录

### 测试结果

- ✓ 通过 - 测试项正常
- ✗ 失败 - 需要修复问题

## 重构完成状态

### 测试结果

- ✅ **文件检查** - 18 个文件都在正确位置
- ✅ **PHP 语法检查** - 所有文件语法正确
- ✅ **类加载测试** - 所有类都能正常加载
- ✅ **基本功能测试** - 核心功能正常
- ✅ **旧文件检查** - 没有遗留旧文件

### 已完成的工作

1. ✅ 文件移动到功能子目录
2. ✅ 更新 Main.php 中的 require_once 路径
3. ✅ 更新所有代码中的路径引用
4. ✅ 通过所有自动化测试

### 重要说明

- 类名**没有改变**，只是文件位置改变
- 所有功能**完全兼容**，无需修改应用代码
- 建议运行 `composer dump-autoload` 更新自动加载

## 后续步骤

重构完成后：

1. 运行 `composer dump-autoload` 更新自动加载
2. 测试功能是否正常
3. 预览文档：`cd vitepress && npm run docs:dev`
4. 构建文档：`cd vitepress && npm run docs:build`
5. 确认无误后，可删除旧文件（如果还在根目录）

## 文档更新

重构工具会自动更新 VitePress 文档中的类名引用：

- 代码块中的类名会自动更新为新类名
- 行内代码中的类名会自动更新
- 在 `api/reference.md` 中添加类名变更说明

也可以单独运行文档更新：

```bash
# 演练模式
python tools/refactor/update_docs.py --dry-run

# 实际执行
python tools/refactor/update_docs.py
```
