# Anon Framework 文档

这是 Anon Framework 的官方文档，使用 VitePress 构建。

## 目录结构

```
vitepress/
├── docs/                    # 文档源文件
│   ├── .vitepress/         # VitePress 配置
│   │   ├── config.mts      # 站点配置
│   │   └── theme/          # 自定义主题
│   ├── guide/              # 指南文档
│   │   ├── api/           # API 模式文档
│   │   └── cms/           # CMS 模式文档
│   ├── api/                # API 参考文档
│   ├── public/             # 静态资源
│   └── index.md            # 首页
├── package.json
└── pnpm-lock.yaml
```

## 开发

### 安装依赖

```bash
pnpm install
```

### 启动开发服务器

```bash
pnpm dev
```

开发服务器将在 `http://localhost:5173` 启动，支持热更新。

### 构建生产版本

```bash
pnpm build
```

构建后的文件将输出到 `../../docs` 目录（项目根目录的 `docs` 文件夹）。

### 预览生产构建

```bash
pnpm preview
```

## 文档规范

### 文件命名

- 使用小写字母和连字符：`quick-start.md`
- 避免使用空格和特殊字符
- 文件名应简洁明了，反映内容主题

### 文档结构

每个文档应包含：

1. **标题**：使用一级标题 `#`
2. **简介**：简要说明文档内容
3. **目录**：VitePress 会自动生成
4. **正文**：使用清晰的层级结构
5. **代码示例**：提供实际可用的示例
6. **相关链接**：指向相关文档

### Markdown 语法

#### 代码块

```php
// PHP 代码示例
Anon_System_Hook::add_action('theme_head', function() {
    echo '<meta name="custom" content="value">';
});
```

```javascript
// JavaScript 代码示例
fetch('/api/posts')
  .then(res => res.json())
  .then(data => console.log(data));
```

#### 提示块

```markdown
::: tip 提示
这是一个提示信息
:::

::: warning 注意
这是一个警告信息
:::

::: danger 危险
这是一个危险信息
:::
```

#### 内部链接

```markdown
[快速开始](./quick-start.md)
[API 参考](../api/reference.md)
```

### 图片资源

将图片放在 `docs/public/assets/` 目录下，然后使用绝对路径引用：

```markdown
![描述](/assets/image.png)
```

## 添加新文档

### 1. 创建文档文件

在适当的目录下创建 `.md` 文件：

```bash
touch docs/guide/my-feature.md
```

### 2. 编写内容

按照文档规范编写内容。

### 3. 更新侧边栏配置

编辑 `docs/.vitepress/config.mts`，在相应的侧边栏组中添加新文档：

```typescript
{
  text: '新功能',
  link: 'my-feature'
}
```

### 4. 添加导航链接（可选）

如果需要在顶部导航中添加链接，编辑 `config.mts` 的 `nav` 配置。

## 文档分类

### 入门指南 (`/guide/`)

- 快速开始
- 安装指南
- 模式对比

### 通用功能 (`/guide/`)

- 路由系统
- 插件系统
- 钩子系统
- Widget 组件
- CLI 命令
- 短代码系统

### API 模式 (`/guide/api/`)

- API 概述
- 请求与响应
- 认证与安全
- 数据库操作
- 性能优化

### CMS 模式 (`/guide/cms/`)

- CMS 概述
- 主题系统
- 评论功能
- 管理后台

### API 参考 (`/api/`)

- API 参考文档
- API 端点列表

## 维护建议

### 定期检查

1. **链接检查**：确保所有内部链接有效
2. **代码示例**：验证代码示例是否可运行
3. **截图更新**：UI 变更后更新相关截图
4. **版本同步**：确保文档与代码版本一致

### 贡献流程

1. Fork 仓库
2. 创建分支：`git checkout -b docs/feature-name`
3. 修改文档
4. 本地测试：`pnpm dev`
5. 提交 PR

## 相关资源

- [VitePress 官方文档](https://vitepress.dev/)
- [Markdown 指南](https://www.markdownguide.org/)
- [Anon Framework GitHub](https://github.com/YuiNijika/Anon)

## 许可证

本文档遵循 MIT 许可证。
