# API 端点

一句话：系统提供的所有API端点列表。

## 系统端点

- `GET /anon/common/license` - 获取许可证信息
- `GET /anon/common/system` - 获取系统信息
- `GET /anon/common/client-ip` - 获取客户端IP
- `GET /anon/common/config` - 获取配置信息
- `GET /anon/ciallo` - 恰喽~
- `GET /anon/install` - 安装页面
- `GET /anon` - 系统根路径

## 调试端点

- `GET /anon/debug/login` - 调试控制台登录页
- `GET /anon/debug/console` - Web调试控制台
- `GET /anon/debug/api/info` - 获取调试信息
- `GET /anon/debug/api/performance` - 获取性能数据
- `GET /anon/debug/api/logs` - 获取日志
- `GET /anon/debug/api/errors` - 获取错误日志
- `GET /anon/debug/api/hooks` - 获取钩子信息
- `GET /anon/debug/api/tools` - 获取工具信息
- `POST /anon/debug/api/clear` - 清空调试数据

## 认证端点

- `POST /auth/login` - 用户登录
- `POST /auth/register` - 用户注册（支持防刷限制）
- `POST /auth/logout` - 用户注销
- `GET /auth/check-login` - 检查登录状态
- `GET /auth/token` - 获取Token
- `GET /auth/captcha` - 获取验证码

## 用户端点

- `GET /user/info` - 获取用户信息

## 静态文件

- `GET /anon/static/debug/css` - 调试控制台样式文件
- `GET /anon/static/debug/js` - 调试控制台脚本文件
- `GET /anon/static/vue` - Vue.js 生产版本

**说明：** 静态文件路由通过 `Anon_Config::addStaticRoute()` 方法注册，支持自动缓存和压缩。详见 [路由处理文档](./routing.md#静态文件路由)。

