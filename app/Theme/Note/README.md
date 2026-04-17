# Note 主题

简洁的日志主题，使用 Tailwind CSS + daisyUI 构建。

## 快速开始

### 生产模式

```bash
cd app/Theme/Note
pnpm install
pnpm build
```

在管理后台「主题」页面选择 "Note" 并激活。

### 开发模式

```bash
pnpm dev
```

Vite 开发服务器将在 `http://localhost:5173` 启动。

**框架支持两种方式定义主题信息（包括开发模式配置）：**

#### 方式一：package.json（推荐）

```json
{
  "name": "anon-theme-note",
  "version": "1.0.0",
  "description": "简洁日志主题",
  "author": "YuiNijika",
  "anon": {
    "displayName": "Note",
    "devMode": {
      "enabled": true,
      "vitePort": 5173
    }
  }
}
```

#### 方式二：PHP 文件头注释

在 `index.php` 或 `theme.php` 顶部添加：

```php
<?php
/**
 * Theme Name: Note
 * Description: 简洁日志主题
 * Version: 1.0.0
 * Author: YuiNijika
 * @anon devMode.enabled=true
 * @anon devMode.vitePort=5173
 */
```

**支持的字段：**
- `Theme Name` → `displayName`
- `Description` → `description`
- `Version` → `version`
- `Author` → `author`
- `Author URI` → `homepage`
- `Theme URI` → `url`
- `@anon key=value` → 自定义字段（支持嵌套，如 `devMode.enabled`）

**优先级：** PHP 文件头注释 > package.json

**框架会自动检测 Vite 是否运行并启用热更新。**

## 特性

- 🎨 **40+ daisyUI 主题**：light, dark, cupcake, synthwave, dracula, nord 等
- ⚡ **PJAX 无刷新**：页面切换流畅，带 NProgress 进度条
- 🌓 **主题切换**：点击按钮即时切换，自动保存偏好
- 📱 **响应式**：完美适配手机、平板、桌面
- 🔍 **悬停预加载**：鼠标悬停链接时自动预加载
- 📝 **简洁布局**：卡片式日志列表，专注内容阅读

## 配置项

### 基础设置

- **配色方案**：选择 daisyUI 主题（40+ 可选）
- **首页日志数**：每页显示数量（5-50，默认 15）
- **显示日期徽章**：是否在卡片显示日期（格式：月-日）
- **顶部导航**：自定义导航，格式 `标题|URL`，每行一个

示例：
```
首页|/
关于|/about
GitHub|https://github.com
```

### 自定义代码

- **头部代码**：插入到 `<head>`（统计代码、自定义 CSS 等）
- **底部代码**：插入到 `</body>` 前（脚本、组件等）

## 开发

### 修改样式

编辑 `src/input.css`，添加自定义 Tailwind 类：

```css
@layer components {
  .my-class {
    @apply text-primary font-bold;
  }
}
```

重新构建：
```bash
pnpm build
```

### 监听模式

```bash
npx tailwindcss -i ./src/input.css -o ./assets/style.css --watch
```

### 修改 JS

编辑 `src/main.ts`，然后 `pnpm build`。

## 技术栈

- Tailwind CSS + daisyUI
- Vite + TypeScript
- NProgress

## 常见问题

**Q: 样式没生效？**  
A: 运行 `pnpm build` 构建资源。

**Q: 如何换主题色？**  
A: 管理后台「主题设置」→「配色方案」选择。

**Q: 如何加自定义字体？**  
A: 「自定义代码」→「头部代码」添加：
```html
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC&display=swap" rel="stylesheet">
<style>body { font-family: 'Noto Sans SC', sans-serif; }</style>
```

## 许可证

MIT
