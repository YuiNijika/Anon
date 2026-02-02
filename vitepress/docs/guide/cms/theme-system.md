# 主题系统与开发

**本节说明**：主题目录结构、自定义代码、模板与资源约定。**适用**：仅 CMS 模式。

Anon Framework 的主题系统提供了类似 Typecho 的模板机制，但支持不区分大小写的文件查找，让主题开发更加灵活。与 [CMS 模式概述](./overview.md)、[路由与页面](./routes.md) 配合使用。

## 主题目录结构

主题文件位于 `server/app/Theme/{themeName}/` 目录：

**重要提示：** 为了兼容 Linux 系统，所有目录和文件名应使用**小写**。系统支持不区分大小写查找，但建议统一使用小写以避免跨平台问题。

```
app/Theme/
└── default/
    ├── app/
    │   ├── code.php      # 主题自定义代码（钩子、路由等）
    │   └── setup.php     # 主题设置项（return 数组）
    ├── index.php         # 首页模板
    ├── about.php         # 关于页模板
    ├── post.php          # 文章模板
    ├── category.php      # 分类页模板
    ├── components/
    │   ├── head.php      # 头部组件
    │   └── foot.php      # 底部组件
    ├── pages/
    │   └── test.php      # 自动注册为 /test
    ├── partials/
    │   ├── header.php    # 头部片段
    │   ├── footer.php    # 底部片段
    │   └── sidebar.php   # 侧边栏片段
    ├── assets/
    │   ├── style.css     # 自动注册为 /assets/css/style
    │   ├── script.js     # 自动注册为 /assets/js/script
    │   └── logo.png      # 自动注册为 /assets/images/logo
    └── package.json
```

## 主题自定义代码

主题目录下的 `app/code.php` 会在主题初始化时自动加载。

**文件位置：** `server/app/Theme/{themeName}/app/code.php`

**功能：**

- 注册钩子
- 注册自定义路由
- 注册错误处理器
- 定义主题辅助函数和类

主题设置项在 `app/setup.php` 中通过 return 数组定义，见下文。

**示例文件位置：** `server/app/Theme/Default/app/code.php`

**完整示例：**

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册主题设置项
Anon_Theme_Options::register('site_title', [
    'type' => 'text',
    'label' => '网站标题',
    'description' => '显示在网站首页的标题',
    'default' => '我的网站',
    'sanitize_callback' => function($value) {
        return trim(strip_tags($value));
    },
    'validate_callback' => function($value) {
        return strlen($value) <= 100;
    },
]);

// 注册动作钩子
Anon_System_Hook::add_action('theme_foot', function () {
    echo '<script>console.log("Theme loaded");</script>';
});

// 注册过滤器钩子
Anon_System_Hook::add_filter('theme_page_title', function ($title) {
    $siteTitle = Anon_Theme_Options::get('site_title', '');
    return $siteTitle ? "{$title} - {$siteTitle}" : $title;
});

// 注册自定义路由
Anon_System_Config::addRoute('/theme/custom', function () {
    Anon_Common::Header();
    $setting = Anon_Theme_Options::get('custom_setting', 'default');
    Anon_Http_Response::success(['setting' => $setting], '获取主题设置成功');
});
```

## 主题设置项

主题设置项用于保存主题的可配置内容，例如站点标题、配色方案、是否显示侧边栏。

### 存储方式

- 设置值存储在 `options` 表
- 键名为 `theme:主题名`
- 值为 JSON 对象

### 注册设置项

在主题 `app/code.php` 中也可单条注册设置项（推荐使用 app/setup.php 统一定义）：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

Anon_Theme_Options::register('site_title', [
    'type' => 'text',
    'label' => '网站标题',
    'description' => '显示在页面中的标题',
    'default' => '我的网站',
    'sanitize_callback' => function($value) {
        return trim(strip_tags($value));
    },
    'validate_callback' => function($value) {
        return strlen($value) <= 100;
    },
]);
```

**app/setup.php 定义设置项**

在主题目录下创建 `app/setup.php`，**return 一个数组**（按 tab 分组），加载主题或管理端拉取设置项时会自动注册，无需再写其它代码：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'tab1' => [
        'site_title' => [
            'type' => 'text',
            'label' => '网站标题',
            'description' => '显示在页面中的标题',
            'default' => '我的网站',
        ],
        'site_description' => [
            'type' => 'textarea',
            'label' => '网站描述',
            'default' => '',
        ],
    ],
    'tab2' => [
        'color_scheme' => [
            'type' => 'select',
            'label' => '配色方案',
            'default' => 'light',
            'options' => ['light' => '浅色', 'dark' => '深色', 'auto' => '自动'],
        ],
        'show_sidebar' => [
            'type' => 'checkbox',
            'label' => '显示侧边栏',
            'default' => true,
        ],
    ],
];
```

**设置项类型：**

- `text`: 文本输入框
- `textarea`: 多行文本域
- `select`: 下拉选择框，需要提供 `options` 数组
- `checkbox`: 复选框，布尔值

**参数说明：**

- `type`: 设置项类型
- `label`: 显示标签
- `description`: 描述信息
- `default`: 默认值
- `sanitize_callback`: 数据清理回调函数
- `validate_callback`: 数据验证回调函数
- `options`: 选择项数组，仅 `select` 类型需要

### 读取设置

**使用静态方法**

```php
$siteTitle = Anon_Theme_Options::get('site_title', '默认标题');
```

**在模板中使用 `$this->options()`**

```php
<!-- 在模板中 -->
<?php echo $this->escape($this->options()->get('title', '默认标题')); ?>
```

`$this->options()` 返回一个代理对象，提供以下方法：

- `get(string $name, $default = null)`: 获取选项值
- `set(string $name, $value)`: 设置选项值

**示例：**

```php
<!-- 获取站点标题 -->
<h1><?php echo $this->escape($this->options()->get('title', '我的网站')); ?></h1>

<!-- 获取站点描述 -->
<?php $description = $this->options()->get('description', ''); ?>
<?php if (!empty($description)): ?>
    <p><?php echo $this->escape($description); ?></p>
<?php endif; ?>
```

### 写入设置

```php
$ok = Anon_Theme_Options::set('site_title', '新标题');
```

### 批量设置

```php
$ok = Anon_Theme_Options::setMany([
    'site_title' => '新标题',
    'site_description' => '新描述',
]);
```

### 获取所有设置

```php
$allSettings = Anon_Theme_Options::all();
```

### 获取设置定义

```php
$schema = Anon_Theme_Options::schema();
```

**兼容性别名：**

- `Anon_Theme_Options` 可以使用 `Anon_ThemeOptions` 别名
- `Anon_Cms_Theme` 可以使用 `Anon_Theme` 别名

## 基本使用

### 1. 创建主题

在 `app/Theme/` 目录下创建主题文件夹：

```bash
mkdir -p app/Theme/mytheme
```

### 2. 创建模板文件

创建 `index.php` 作为首页模板：

```php
<!-- app/Theme/mytheme/index.php -->
<?php
const Anon_PageMeta = [
    'title' => '首页',
    'description' => '这是首页的描述',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $this->headMeta(); ?>
    <?php $this->assets('style.css'); ?>
</head>
<body>
    <?php $this->components('head'); ?>
    
    <main>
        <h1><?php echo $this->escape($this->options()->get('title', '我的网站')); ?></h1>
        
        <!-- 文章列表 -->
        <?php $posts = $this->posts(10); ?>
        <?php foreach ($posts as $post): ?>
          <article>
            <h2>
              <a href="/post/<?php echo $post->id(); ?>">
                <?php echo $this->escape($post->title()); ?>
              </a>
            </h2>
            <p><?php echo $this->escape($post->excerpt(150)); ?></p>
            <time><?php echo $post->date('Y-m-d'); ?></time>
          </article>
        <?php endforeach; ?>
    </main>
    
    <?php $this->components('foot'); ?>
    
    <?php $this->assets('script.js'); ?>
    <?php $this->footMeta(); ?>
</body>
</html>
```

### 3. 配置主题

在 `useApp.php` 中设置主题：

```php
'cms' => [
    'theme' => 'mytheme',  // 使用 mytheme 主题
    'routes' => [
        '/' => 'index',
        '/post/{id}' => 'post',
    ],
],
```

## 模板渲染模型

主题模板文件会在一个“视图对象”上下文中执行，你可以像 Typecho 一样在模板里直接使用 `$this->` 调用方法。

### 模板内可用的 `$this` 方法

- `$this->components('head')` / `$this->components('foot')` - 引入组件
- `$this->partial('name', ['key' => 'value'])` - 引入模板片段
- `$this->assets('style.css')` - 引入静态资源
- `$this->headMeta()` / `$this->footMeta()` - 输出 SEO meta 标签
- `$this->escape($text)` - 转义 HTML 输出
- `$this->markdown($content)` - 渲染 Markdown 内容
- `$this->post()` - 获取当前文章对象
- `$this->page()` - 获取当前页面对象
- `$this->posts($limit)` - 获取文章列表，返回数组
- `$this->permalink($post)` - 生成永久链接
- `$this->options()->get($name, $default)` / `$this->options()->set($name, $value)` - 访问选项
- `$this->get('key')` - 读取 `render()` 传入的数据

### permalink() 方法

生成文章、页面、分类或标签的永久链接：

```php
$this->permalink($post = null): string
```

**参数：**

- `$post`: 可选，可以是：
  - `Anon_Cms_Post` 对象
  - 文章/页面数据数组
  - `null` 时使用当前文章或页面

**返回值：**

- 返回永久链接 URL 字符串

**功能特点：**

1. **自动读取路由配置**：从 `options` 表的 `routes` 字段读取路由规则
2. 智能匹配路由，根据文章或页面类型匹配对应的路由模板
3. **参数替换**：支持替换路由中的参数：
   - `{id}` - 文章/页面 ID
   - `{slug}` - 文章或页面 slug，自动 URL 编码
   - `{year}`、`{month}`、`{day}` - 发布日期
4. **默认回退**：如果没有找到匹配的路由，使用默认规则：
   - 文章：`/post/{id}`
   - 页面：`/{slug}`

**使用示例：**

```php
<!-- 在 post.php 或 page.php 中，使用当前文章/页面 -->
<a href="<?php echo $this->permalink(); ?>">
  <?php echo $this->escape($this->post()->title()); ?>
</a>

<!-- 在 index.php 中，遍历文章列表 -->
<?php foreach ($this->posts(10) as $post): ?>
  <article>
    <h2>
      <a href="<?php echo $this->permalink($post); ?>">
        <?php echo $this->escape($post->title()); ?>
      </a>
    </h2>
    <p><?php echo $this->escape($post->excerpt(150)); ?></p>
  </article>
<?php endforeach; ?>

<!-- 传入文章对象 -->
<?php $post = $this->post(); ?>
<?php if ($post): ?>
  <a href="<?php echo $this->permalink($post); ?>">查看文章</a>
<?php endif; ?>

<!-- 传入数组数据 -->
<?php
$postData = [
  'id' => 1,
  'slug' => 'hello-world',
  'type' => 'post',
];
?>
<a href="<?php echo $this->permalink($postData); ?>">链接</a>
```

**路由规则示例：**

假设在管理后台配置了以下路由规则：

- `/post/{id}` → `post` 文章
- `/{slug}` → `page` 独立页面
- `/category/{slug}` → `category` 分类
- `/tag/{slug}` → `tag` 标签

当调用 `$this->permalink($post)` 时，系统会：

1. 检查文章类型
2. 查找匹配的路由模板
3. 替换参数
4. 返回生成的 URL

### posts() 方法 - 分页支持

`posts()` 方法支持分页功能，可以自动处理文章列表的分页：

```php
$this->posts(int $pageSize = 10, ?int $page = null): array
```

**参数：**

- `$pageSize`: 每页显示的文章数量，默认 10
- `$page`: 当前页码，如果为 `null` 则自动从 URL 的 `?page=` 参数获取

**返回值：**

- 返回当前页的文章数组

**分页逻辑：**

- 如果 `$pageSize <= 0`，则不分页，返回最新的文章列表
- 自动从 URL 查询参数 `?page=2` 获取当前页码
- 自动计算总页数和分页信息

**使用示例：**

```php
<!-- 获取分页的文章列表，每页 12 条 -->
<?php $posts = $this->posts(12); ?>

<!-- 遍历文章 -->
<?php foreach ($posts as $post): ?>
  <article>
    <h2>
      <a href="<?php echo $this->permalink($post); ?>">
        <?php echo $this->escape($post->title()); ?>
      </a>
    </h2>
    <p><?php echo $this->escape($post->excerpt(150)); ?></p>
  </article>
<?php endforeach; ?>
```

### pageNav() 方法 - 分页导航

获取分页导航数据，返回结构化的分页信息：

```php
$this->pageNav(): ?array
```

**返回值：**

- 如果有分页且总页数大于 1，返回分页数据数组
- 如果没有分页或只有一页，返回 `null`

**返回数据结构：**

```php
[
    'prev' => [
        'page' => 1,           // 上一页页码
        'link' => '/?page=1'   // 上一页链接
    ] | null,                  // 如果没有上一页则为 null
    'next' => [
        'page' => 3,           // 下一页页码
        'link' => '/?page=3'   // 下一页链接
    ] | null,                  // 如果没有下一页则为 null
    'pages' => [               // 页码数组
        [
            'page' => 1,       // 页码
            'link' => '/',     // 链接
            'current' => false // 是否为当前页
        ],
        [
            'page' => 2,
            'link' => '/?page=2',
            'current' => true  // 当前页
        ],
        // ...
    ],
    'current' => 2,            // 当前页码
    'total' => 5               // 总页数
]
```

**使用示例：**

```php
<!-- 获取分页导航 -->
<?php $nav = $this->pageNav(); ?>
<?php if ($nav): ?>
  <div class="pagination">
    <!-- 上一页 -->
    <?php if ($nav['prev']): ?>
      <a href="<?php echo $this->escape($nav['prev']['link']); ?>" class="prev">上一页</a>
    <?php endif; ?>
    
    <!-- 页码 -->
    <?php foreach ($nav['pages'] as $page): ?>
      <?php if ($page['current']): ?>
        <span class="current"><?php echo $page['page']; ?></span>
      <?php else: ?>
        <a href="<?php echo $this->escape($page['link']); ?>"><?php echo $page['page']; ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- 下一页 -->
    <?php if ($nav['next']): ?>
      <a href="<?php echo $this->escape($nav['next']['link']); ?>" class="next">下一页</a>
    <?php endif; ?>
  </div>
<?php endif; ?>
```

**完整示例 - 带分页的文章列表页：**

```php
<!-- app/Theme/default/index.php -->
<?php $this->components('head'); ?>

<div class="posts">
  <?php $posts = $this->posts(12); ?>
  
  <?php if (empty($posts)): ?>
    <p>暂无文章</p>
  <?php else: ?>
    <?php foreach ($posts as $post): ?>
      <article>
        <h2>
          <a href="<?php echo $this->permalink($post); ?>">
            <?php echo $this->escape($post->title()); ?>
          </a>
        </h2>
        <p><?php echo $this->escape($post->excerpt(150)); ?></p>
        <time><?php echo $post->date('Y-m-d'); ?></time>
      </article>
    <?php endforeach; ?>
    
    <!-- 分页导航 -->
    <?php $nav = $this->pageNav(); ?>
    <?php if ($nav): ?>
      <nav class="pagination">
        <?php if ($nav['prev']): ?>
          <a href="<?php echo $this->escape($nav['prev']['link']); ?>">上一页</a>
        <?php endif; ?>
        
        <?php foreach ($nav['pages'] as $page): ?>
          <?php if ($page['current']): ?>
            <span class="current"><?php echo $page['page']; ?></span>
          <?php else: ?>
            <a href="<?php echo $this->escape($page['link']); ?>"><?php echo $page['page']; ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if ($nav['next']): ?>
          <a href="<?php echo $this->escape($nav['next']['link']); ?>">下一页</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php $this->components('foot'); ?>
```

**URL 格式：**

分页链接会自动添加到当前 URL 的查询参数中：

- 首页：`/`
- 第 2 页：`/?page=2`
- 第 3 页：`/?page=3`
- 如果当前 URL 已有其他参数：`/category/tech?page=2`

## 模板 API

### Anon_Cms_Theme::render()

渲染模板文件：

```php
Anon_Cms_Theme::render(string $templateName, array $data = []): void
```

**参数：**

- `$templateName`: 模板名称，不包含扩展名
- `$data`: 传递给模板的数据数组

**模板回退机制：**

- 如果找不到 `post`、`page`、`error` 模板，系统会自动回退到 `index` 模板
- 这样可以确保即使没有创建专门的模板文件，也能正常显示页面

**示例：**

```php
Anon_Cms_Theme::render('post', [
    'title' => '文章标题',
    'content' => '<p>文章内容</p>',
    'author' => '作者名',
]);
```

### Anon_Cms::getPageType()

获取页面类型：

```php
Anon_Cms::getPageType(string $templateName): string
```

**参数：**

- `$templateName`: 模板名称

**返回值：**

- `'index'`: 首页类型
- `'post'`: 文章类型
- `'page'`: 页面类型
- `'error'`: 错误页类型
- `'other'`: 其他类型

**示例：**

```php
$pageType = Anon_Cms::getPageType('post'); // 返回 'post'
$pageType = Anon_Cms::getPageType('pages/Test'); // 返回 'other'
$pageType = Anon_Cms::getPageType('error'); // 返回 'error'
```

### Anon_Cms_Theme::partial()

包含模板片段：

```php
Anon_Cms_Theme::partial(string $partialName, array $data = []): void
```

**参数：**

- `$partialName`: 片段名称，不包含扩展名
- `$data`: 传递给片段的数据数组

**示例：**

```php
<!-- 在模板中 -->
<?php $this->partial('header', ['title' => '页面标题']); ?>
```

片段文件应放在 `partials/` 目录：

```php
<!-- app/Theme/default/partials/header.php -->
<header>
    <h1><?php echo $title ?? '网站标题'; ?></h1>
    <nav>
        <a href="/">首页</a>
        <a href="/about">关于</a>
    </nav>
</header>
```

### Anon_Cms_Theme::assets()

获取主题资源 URL 或自动输出 HTML 标签：

```php
Anon_Cms_Theme::assets(string $path, ?string $type = null, array $attributes = []): string
```

**参数：**

- `$path`: 资源路径，相对于主题目录的 assets 目录
- `$type`: 资源类型，可选，通常自动检测
- `$attributes`: 额外属性数组，可选

**自动输出 HTML：**

对于 CSS 和 JS 文件，如果不提供 `$attributes` 参数，方法会自动输出相应的 HTML 标签：

```php
<!-- CSS 文件自动输出 <link> 标签 -->
<?php $this->assets('style.css'); ?>
<!-- 输出: <link rel="stylesheet" href="/assets/css/style"> -->

<!-- JS 文件自动输出 <script> 标签 -->
<?php $this->assets('main.js'); ?>
<!-- 输出: <script src="/assets/js/main"></script> -->
```

**返回 URL：**

对于其他文件类型，方法会返回资源 URL：

```php
<!-- 图片文件返回 URL -->
<img src="<?php echo $this->assets('logo.png'); ?>" alt="Logo">
<!-- 返回: /assets/images/logo -->
```

**自定义属性：**

如果需要自定义属性，可以传入 `$attributes` 参数：

```php
<?php $this->assets('style.css', null, ['media' => 'print']); ?>
<!-- 输出: <link rel="stylesheet" href="/assets/css/style" media="print"> -->

<?php $this->assets('main.js', null, ['defer' => 'defer']); ?>
<!-- 输出: <script src="/assets/js/main" defer></script> -->
```

**路由规则：**

- `.css` 文件 → `/assets/css/{文件名}`
- `.js` 文件 → `/assets/js/{文件名}`
- `.json` 文件 → `/assets/json/{文件名}`
- 图片文件 → `/assets/images/{文件名}`
- 字体文件 → `/assets/fonts/{文件名}`
- 其他类型 → `/assets/files/{文件名}`

**注意：** 静态资源会自动注册路由，无需手动配置。系统会在启动时扫描 `assets/` 目录下的所有文件并自动注册。

### Anon_Cms_Theme::components()

导入组件，类似 Vue 的组件导入机制：

```php
Anon_Cms_Theme::components(string $componentPath, array $data = []): void
```

**参数：**

- `$componentPath`: 组件路径，支持点号或斜杠分隔，如 `'head'`、`'App.Header'` 或 `'App/Header'`
- `$data`: 传递给组件的数据数组

**组件查找规则：**

- 组件文件位于 `components/` 目录
- 方法会自动处理 `components/` 或 `components.` 前缀，直接写组件名即可
- 支持不区分大小写查找，但建议使用小写目录名
- 支持 `.php`、`.html`、`.htm` 扩展名

**示例：**

```php
<!-- 导入 components 目录下的组件 -->
<?php $this->components('head'); ?>
<?php $this->components('foot'); ?>

<!-- 导入嵌套组件 -->
<?php $this->components('App.Header', ['title' => '页面标题']); ?>
<?php $this->components('App/Footer', ['copyright' => '2024']); ?>
```

### Anon_Cms_Theme::getCurrentTheme()

获取当前主题名称：

```php
$themeName = Anon_Cms_Theme::getCurrentTheme(); // 返回 'default'
```

### Anon_Cms_Theme::info()

获取主题信息，从主题目录的 `package.json` 文件中读取：

```php
Anon_Cms_Theme::info(?string $key = null): mixed
```

**参数：**

- `$key`: 信息键名，可选，如果为 null 则返回所有信息

**返回值：**

- 如果指定了 `$key`，返回对应的值
- 如果 `$key` 为 null，返回包含所有主题信息的数组

**示例：**

```php
// 获取所有主题信息
$themeInfo = Anon_Cms_Theme::info();
// 返回: ['name' => 'Default', 'description' => '默认主题', 'author' => 'YuiNijika', ...]

// 获取特定信息
$themeName = Anon_Cms_Theme::info('name'); // 返回 'Default'
$author = Anon_Cms_Theme::info('author'); // 返回 'YuiNijika'
$version = Anon_Cms_Theme::info('version'); // 返回 '1.0.0'
```

**package.json 文件格式：**

主题目录下的 `package.json` 文件应包含以下字段：

```json
{
  "name": "anon-theme-default",
  "version": "1.0.0",
  "description": "默认主题",
  "author": "YuiNijika",
  "homepage": "https://github.com/YuiNijika/Anon",
  "anon": {
    "displayName": "Default",
    "screenshot": "screenshot.jpg"
  }
}
```

## HTML 辅助方法

### Anon_Cms_Theme::headMeta()

输出页面 head 部分的 meta 标签，包括 title 和 SEO 信息：

```php
Anon_Cms_Theme::headMeta(array $overrides = []): void
```

**参数：**

- `$overrides`: 覆盖 SEO 数据的数组，可选

**功能：**

- 自动从 `Anon_PageMeta` 常量获取 SEO 信息
- 输出 `<title>` 标签
- 输出 description、keywords、author、robots、canonical 等 meta 标签
- 输出 Open Graph 和 Twitter Card 标签

**示例：**

```php
<!-- 在模板文件顶部定义 SEO 信息 -->
<?php
const Anon_PageMeta = [
    'title' => '文章标题',
    'description' => '这是文章的描述',
    'keywords' => '文章, 博客, Anon',
    'canonical' => '/post/123',
];
?>

<!-- 在 head 组件中输出 -->
<head>
    <meta charset="UTF-8">
    <?php $this->headMeta(); ?>
</head>
```

**覆盖 SEO 信息：**

```php
<?php $this->headMeta([
    'title' => '覆盖的标题',
    'description' => '覆盖的描述',
]); ?>
```

### Anon_Cms_Theme::footMeta()

输出页面底部 meta 信息，触发 `theme_foot` 钩子：

```php
Anon_Cms_Theme::footMeta(): void
```

**功能：**

- 触发 `theme_foot` 动作钩子
- 允许注册的回调函数输出自定义脚本或内容

**示例：**

```php
<!-- 在 foot 组件中 -->
<?php $this->footMeta(); ?>
```

**注册 foot 钩子：**

```php
// 在 app/code.php 中
Anon_System_Hook::add_action('theme_foot', function () {
    echo '<script>console.log("Theme loaded");</script>';
});
```

### Anon_Cms_Theme::title()

输出页面标题标签：

```php
Anon_Cms_Theme::title(?string $title = null, string $separator = ' - ', bool $reverse = false): void
```

**参数：**

- `$title`: 页面标题，如果为 null 则使用站点名称
- `$separator`: 标题和站点名称之间的分隔符
- `$reverse`: 是否反转顺序，站点名称在前

**示例：**

```php
<?php Anon_Cms_Theme::title('文章标题'); ?>
<!-- 输出: <title>文章标题 - 站点名称</title> -->

<?php Anon_Cms_Theme::title('文章标题', ' | ', true); ?>
<!-- 输出: <title>站点名称 | 文章标题</title> -->
```

### Anon_Cms_Theme::meta()

输出 SEO meta 标签：

```php
Anon_Cms_Theme::meta(array $meta = []): void
```

**支持的键：**

- `description`: 页面描述
- `keywords`: 关键词，字符串或数组
- `author`: 作者
- `robots`: robots 标签
- `canonical`: canonical URL
- `og`: Open Graph 标签数组
- `twitter`: Twitter Card 标签数组

**示例：**

```php
<?php Anon_Cms_Theme::meta([
    'description' => '这是页面描述',
    'keywords' => ['关键词1', '关键词2'],
    'canonical' => '/post/123',
    'og' => [
        'title' => '文章标题',
        'type' => 'article',
    ],
]); ?>
```

### Anon_Cms_Theme::stylesheet()

输出样式表链接：

```php
Anon_Cms_Theme::stylesheet(string|array $styles, array $attributes = []): void
```

**参数：**

- `$styles`: 样式文件路径，可以是字符串或数组
- `$attributes`: 额外属性数组

**示例：**

```php
<?php Anon_Cms_Theme::stylesheet('style.css'); ?>
<!-- 输出: <link rel="stylesheet" href="/assets/css/style"> -->

<?php Anon_Cms_Theme::stylesheet(['style.css', 'custom.css']); ?>
<!-- 输出多个样式表链接 -->

<?php Anon_Cms_Theme::stylesheet('style.css', ['media' => 'print']); ?>
<!-- 输出带额外属性的样式表链接 -->
```

### Anon_Cms_Theme::script()

输出脚本标签：

```php
Anon_Cms_Theme::script(string|array $scripts, array $attributes = []): void
```

**参数：**

- `$scripts`: 脚本文件路径，可以是字符串或数组
- `$attributes`: 额外属性数组

**示例：**

```php
<?php Anon_Cms_Theme::script('main.js'); ?>
<!-- 输出: <script src="/assets/js/main"></script> -->

<?php Anon_Cms_Theme::script(['main.js', 'custom.js']); ?>
<!-- 输出多个脚本标签 -->

<?php Anon_Cms_Theme::script('main.js', ['defer' => 'defer']); ?>
<!-- 输出带额外属性的脚本标签 -->
```

### Anon_Cms_Theme::favicon()

输出 favicon 链接：

```php
Anon_Cms_Theme::favicon(string $path = 'favicon.ico'): void
```

**示例：**

```php
<?php Anon_Cms_Theme::favicon('favicon.ico'); ?>
<!-- 输出: <link rel="icon" href="/assets/images/favicon"> -->
```

### Anon_Cms_Theme::escape()

转义 HTML 输出，防止 XSS 攻击：

```php
Anon_Cms_Theme::escape(string $text, int $flags = ENT_QUOTES): string
```

**示例：**

```php
<h1><?php echo Anon_Cms_Theme::escape($title ?? ''); ?></h1>
```

### Anon_Cms_Theme::jsonLd()

输出 JSON-LD 结构化数据：

```php
Anon_Cms_Theme::jsonLd(array $data): void
```

**示例：**

```php
<?php Anon_Cms_Theme::jsonLd([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => '文章标题',
    'author' => [
        '@type' => 'Person',
        'name' => '作者名',
    ],
]); ?>
```

## 静态资源管理

### 自动路由注册

系统会自动扫描主题目录下的 `assets/` 文件夹，并为所有文件注册静态资源路由。文件会按类型分类到不同的目录：

```
assets/
├── style.css      → /assets/css/style
├── script.js      → /assets/js/script
├── logo.png       → /assets/images/logo
└── font.woff2     → /assets/fonts/font
```

### 文件类型映射

系统支持以下文件类型映射：

| 扩展名 | 路由目录 | MIME 类型 |
|--------|---------|-----------|
| `.css` | `css` | `text/css` |
| `.js` | `js` | `application/javascript` |
| `.json` | `json` | `application/json` |
| `.png`, `.jpg`, `.jpeg`, `.gif`, `.svg`, `.ico` | `images` | `image/*` |
| `.woff`, `.woff2`, `.ttf`, `.eot` | `fonts` | `font/*` |
| 其他 | `files` | `application/octet-stream` |

### 自定义文件类型映射

可以通过钩子自定义文件类型映射：

```php
// 在 useCode.php 或插件中

// 添加新的 MIME 类型
Anon_System_Hook::add_filter('cms_theme_mime_types', function($mimeTypes) {
    $mimeTypes['webp'] = 'image/webp';
    $mimeTypes['mp4'] = 'video/mp4';
    return $mimeTypes;
});

// 修改文件类型到目录的映射
Anon_System_Hook::add_filter('cms_theme_type_dirs', function($typeDirs) {
    $typeDirs['webp'] = 'images';
    $typeDirs['mp4'] = 'videos';
    $typeDirs['css'] = 'styles'; // 修改现有映射
    return $typeDirs;
});
```

**钩子说明：**

- `cms_theme_mime_types`: 修改 MIME 类型映射
- `cms_theme_type_dirs`: 修改文件类型到目录的映射

## 自动路由注册

### pages 目录

在主题目录下创建 `pages/` 目录，系统会自动为其中的 PHP 文件注册路由：

```
app/Theme/default/
└── pages/
    ├── index.php      → 自动注册为 / 和 /index
    ├── about.php      → 自动注册为 /about
    └── contact.php    → 自动注册为 /contact
```

**注意：** 目录名必须使用小写 `pages/`，文件名建议使用小写以避免跨平台问题。

**路由规则：**

- 文件名不含扩展名作为路由路径
- `Index.php` 会同时注册 `/` 和 `/index`
- 支持嵌套目录，如 `Pages/Blog/Index.php` → `/blog` 和 `/blog/index`

**示例：**

```php
<!-- app/Theme/default/pages/about.php -->
<?php
const Anon_PageMeta = [
    'title' => '关于我们',
    'description' => '这是关于页面的描述',
    'keywords' => '关于, 公司, 团队'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo Anon_PageMeta['title']; ?></title>
</head>
<body>
    <h1>关于我们</h1>
    <p>这是关于页面的内容</p>
</body>
</html>
```

## SEO 管理

### Anon_PageMeta

在模板文件中定义 `Anon_PageMeta` 常量来设置页面 SEO 信息：

```php
<?php
const Anon_PageMeta = [
    'title' => '文章标题',
    'description' => '文章描述',
    'keywords' => '关键词1, 关键词2'
];
?>
```

系统会自动生成以下 SEO 标签：

- `canonical` URL，基于当前请求路径
- `author`，从配置中获取
- Open Graph 标签
- Twitter Card 标签
- `robots` 标签，默认为 `index, follow`

### 使用 Anon_Cms_PageMeta

在模板中使用 `Anon_Cms_PageMeta` 类获取 SEO 信息：

```php
<?php
// 获取 SEO 信息
$seo = Anon_Cms_PageMeta::getSeo();

// 获取错误信息
$error = Anon_Cms_PageMeta::getError();
?>
```

**示例：**

```php
<!-- app/Theme/default/post.php -->
<?php
const Anon_PageMeta = [
    'title' => '文章标题',
    'description' => '这是文章的描述',
    'keywords' => '文章, 博客'
];

$seo = Anon_Cms_PageMeta::getSeo();
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($seo['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($seo['canonical']); ?>">
</head>
<body>
    <!-- 页面内容 -->
</body>
</html>
```

## 文件查找规则

### 不区分大小写

主题系统支持不区分大小写的文件查找：

- `index.php`、`Index.php`、`INDEX.php` 都会被识别
- `header.php`、`Header.php`、`HEADER.php` 都会被识别

### 支持的扩展名

系统会自动查找以下扩展名的文件：

- `.php` 优先
- `.html`
- `.htm`

### 查找顺序

1. 精确匹配，区分大小写
2. 不区分大小写匹配
3. 如果找不到，抛出异常

## 模板变量

### 传递变量

在渲染模板时传递变量：

```php
Anon_Cms_Theme::render('post', [
    'post' => [
        'id' => 1,
        'title' => '文章标题',
        'content' => '文章内容',
    ],
    'author' => '作者名',
]);
```

### 在模板中使用

模板不再通过 `extract()` 注入变量，推荐用 `$this->get()` 读取传入数据：

```php
<!-- app/Theme/default/post.php -->
<?php $post = $this->get('post', []); ?>
<h1><?php echo $this->escape($post['title'] ?? ''); ?></h1>
<div><?php echo $this->markdown($post['content'] ?? ''); ?></div>
<p>作者：<?php echo $this->escape($this->get('author', '')); ?></p>
```

## Markdown 渲染

系统可能会在 Markdown 内容前加 `<!--markdown-->` 标记用于识别内容类型。主题中渲染请统一使用 `$this->markdown()`：

```php
<?php $post = $this->post(); ?>
<?php if ($post): ?>
  <?php echo $this->markdown($post->content()); ?>
<?php endif; ?>
```

## 文章和页面对象

系统提供了类似 Typecho 的链式调用 API，通过 `Anon_Cms_Post` 对象访问文章和页面数据。

### 获取文章/页面对象

```php
// 获取当前文章对象
<?php $post = $this->post(); ?>

// 获取当前页面对象
<?php $page = $this->page(); ?>

// 获取文章列表，返回对象数组
<?php $posts = $this->posts(12); ?>
```

### 对象方法

`Anon_Cms_Post` 类提供以下方法：

#### 基本信息

- `id(): int` - 获取文章/页面 ID
- `title(): string` - 获取标题
- `content(): string` - 获取内容
- `excerpt(int $length = 150): string` - 获取摘要，自动提取
- `slug(): string` - 获取 Slug
- `type(): string` - 获取类型，`post` 或 `page`
- `status(): string` - 获取状态

#### 时间相关

- `created(): int` - 获取创建时间，时间戳
- `date(string $format = 'Y-m-d H:i:s'): string` - 获取创建时间，格式化字符串
- `modified(): int` - 获取更新时间，时间戳

#### 其他

- `category(): ?int` - 获取分类 ID
- `get(string $key, $default = null)` - 获取字段值
- `has(string $key): bool` - 检查字段是否存在
- `toArray(): array` - 获取原始数据数组

### 使用示例

#### 文章列表页

```php
<?php $posts = $this->posts(12); ?>
<?php foreach ($posts as $post): ?>
  <article>
    <h2>
      <a href="/post/<?php echo $post->id(); ?>">
        <?php echo $this->escape($post->title()); ?>
      </a>
    </h2>
    <p><?php echo $this->escape($post->excerpt(150)); ?></p>
    <time><?php echo $post->date('Y-m-d'); ?></time>
  </article>
<?php endforeach; ?>
```

#### 文章详情页

```php
<?php $post = $this->post(); ?>
<?php if ($post): ?>
  <article>
    <h1><?php echo $this->escape($post->title()); ?></h1>
    <div class="meta">
      <time>发布于 <?php echo $post->date('Y-m-d H:i'); ?></time>
      <?php if ($post->category()): ?>
        <span>分类：<?php echo $post->category(); ?></span>
      <?php endif; ?>
    </div>
    <div class="content">
      <?php echo $this->markdown($post->content()); ?>
    </div>
  </article>
<?php endif; ?>
```

#### 页面详情页

```php
<?php $page = $this->page(); ?>
<?php if ($page): ?>
  <article>
    <h1><?php echo $this->escape($page->title()); ?></h1>
    <div class="meta">
      <time>更新于 <?php echo date('Y-m-d H:i', $page->modified()); ?></time>
    </div>
    <div class="content">
      <?php echo $this->markdown($page->content()); ?>
    </div>
  </article>
<?php endif; ?>
```

#### 访问自定义字段

```php
<?php $post = $this->post(); ?>
<?php if ($post): ?>
  <!-- 使用 get() 方法访问自定义字段 -->
  <?php $customField = $post->get('custom_field', '默认值'); ?>
  <?php if ($post->has('featured_image')): ?>
    <img src="<?php echo $post->get('featured_image'); ?>" alt="">
  <?php endif; ?>
<?php endif; ?>
```

### 性能优化

系统在内部实现了请求级缓存，确保：

- 同一请求内多次调用 `$this->post()` 或 `$this->page()` 不会重复查询数据库
- `$this->posts($limit)` 的结果会被缓存，避免重复查询
- 所有缓存仅在当前请求内有效，不会跨请求

### 与数组访问的兼容性

如果需要访问原始数组数据，可以使用 `toArray()` 方法：

```php
<?php $post = $this->post(); ?>
<?php $postArray = $post->toArray(); ?>
<!-- 现在可以使用数组方式访问 -->
<?php echo $postArray['title']; ?>
```

### 全局变量

以下变量在所有模板中可用：

- `Anon_Common::NAME` - 框架名称
- `Anon_Common::VERSION` - 框架版本
- `Anon_Cms_Theme::getCurrentTheme()` - 当前主题名称

## 路由参数

当使用参数路由时，参数会自动传递给模板：

```php
// useApp.php
'routes' => [
    '/post/{id}' => 'post',
],
```

在模板中可以直接使用 `$id` 变量：

```php
<!-- app/Theme/default/post.php -->
<?php
$postId = $id ?? 0;
// 使用 $postId 获取文章数据
?>
<h1>文章 ID: <?php echo htmlspecialchars($postId); ?></h1>
```

## 模板片段

### 创建片段

在 `partials/` 目录下创建片段文件：

```php
<!-- app/Theme/default/partials/header.php -->
<header class="site-header">
    <div class="container">
        <h1><?php echo $siteTitle ?? '我的网站'; ?></h1>
        <nav>
            <a href="/">首页</a>
            <a href="/about">关于</a>
            <a href="/contact">联系</a>
        </nav>
    </div>
</header>
```

### 使用片段

在模板中包含片段：

```php
<?php $this->partial('header', ['siteTitle' => '我的网站']); ?>
```

### 片段嵌套

片段可以嵌套使用：

```php
<!-- app/Theme/default/partials/nav.php -->
<nav>
    <?php $this->partial('nav-item', ['text' => '首页', 'url' => '/']); ?>
    <?php $this->partial('nav-item', ['text' => '关于', 'url' => '/about']); ?>
</nav>
```

## 布局模板

### 创建布局

创建一个基础布局模板：

```php
<!-- app/Theme/default/layout.php -->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title ?? '页面标题'; ?></title>
    <link rel="stylesheet" href="<?php echo $this->assets('style.css'); ?>">
</head>
<body>
    <?php $this->partial('header'); ?>
    
    <main>
        <?php echo $content ?? ''; ?>
    </main>
    
    <?php $this->partial('footer'); ?>
</body>
</html>
```

### 使用布局

在具体页面模板中使用布局：

```php
<!-- app/Theme/default/index.php -->
<?php
$title = '首页';
$content = '<h1>欢迎访问</h1><p>这是首页内容</p>';
Anon_Cms_Theme::render('layout', compact('title', 'content'));
?>
```

## 最佳实践

### 1. 安全性

始终转义输出，防止 XSS 攻击：

```php
<!-- 正确：使用 $this->escape() -->
<?php $post = $this->post(); ?>
<h1><?php echo $this->escape($post->title()); ?></h1>

<!-- 错误：直接输出 -->
<h1><?php echo $post->title(); ?></h1>
```

### 2. 默认值

为变量提供默认值：

```php
<?php $post = $this->post(); ?>
<?php if ($post): ?>
  <h1><?php echo $this->escape($post->title()); ?></h1>
  <p><?php echo $this->escape($post->excerpt(150) ?: '暂无摘要'); ?></p>
<?php endif; ?>
```

### 3. 代码组织

- 将公共部分提取为片段
- 使用布局模板减少重复
- 保持模板简洁，复杂逻辑放在路由处理中

### 4. 性能

- 避免在模板中执行数据库查询
- 使用缓存提高性能
- 合理使用模板片段

## 完整示例

### 主题结构

```
app/Theme/default/
├── index.php
├── post.php
├── layout.php
├── components/      # 组件目录
│   ├── head.php
│   └── foot.php
├── pages/           # 自动路由页面目录
│   └── about.php
├── partials/        # 模板片段目录
│   ├── header.php
│   ├── footer.php
│   └── nav.php
└── assets/          # 静态资源目录
    ├── style.css
    └── main.js
```

### 布局模板

```php
<!-- app/Theme/default/layout.php -->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $this->headMeta(); ?>
    <?php $this->assets('style.css'); ?>
</head>
<body>
    <?php $this->components('head'); ?>
    
    <main class="container">
        <?php echo $content ?? ''; ?>
    </main>
    
    <?php $this->components('foot'); ?>
    
    <?php $this->assets('main.js'); ?>
    <?php $this->footMeta(); ?>
</body>
</html>
```

### 首页模板

```php
<!-- app/Theme/default/index.php -->
<?php
const Anon_PageMeta = [
    'title' => '首页',
    'description' => '这是首页的描述',
];

$this->components('head');
?>

<div class="space-y-6">
  <!-- 站点介绍 -->
  <div class="card">
    <h1><?php echo $this->escape($this->options()->get('title', '我的网站')); ?></h1>
    <p><?php echo $this->escape($this->options()->get('description', '')); ?></p>
  </div>

  <!-- 文章列表 -->
  <?php $posts = $this->posts(12); ?>
  <?php if (!empty($posts)): ?>
    <div class="grid gap-4">
      <?php foreach ($posts as $post): ?>
        <article class="card">
          <h2>
            <a href="/post/<?php echo $post->id(); ?>">
              <?php echo $this->escape($post->title()); ?>
            </a>
          </h2>
          <p><?php echo $this->escape($post->excerpt(150)); ?></p>
          <time><?php echo $post->date('Y-m-d'); ?></time>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php $this->components('foot'); ?>
```

### 文章页模板

```php
<!-- app/Theme/default/post.php -->
<?php
const Anon_PageMeta = [
    'title' => '文章标题',
    'description' => '这是文章的描述',
    'keywords' => '文章, 博客',
    'canonical' => '/post/' . ($id ?? 0),
];

$this->components('head');
?>

<?php $post = $this->post(); ?>
<?php if ($post): ?>
  <article>
    <h1><?php echo $this->escape($post->title()); ?></h1>
    <div class="meta">
      <time>发布于 <?php echo $post->date('Y-m-d H:i'); ?></time>
    </div>
    <div class="content">
      <?php echo $this->markdown($post->content()); ?>
    </div>
  </article>
<?php endif; ?>

<?php $this->components('foot'); ?>
```

## 调试技巧

### 检查模板路径

```php
<?php
$themeDir = Anon_Cms_Theme::getThemeDir();
echo "主题目录: {$themeDir}";
?>
```

### 检查当前主题

```php
<?php
echo "当前主题: " . Anon_Cms_Theme::getCurrentTheme();
?>
```

### 模板错误处理

**组件级错误：**

如果组件文件不存在或调用出错，系统会在调用位置直接输出 HTML 错误信息，不会中断页面渲染：

```php
<?php $this->components('nonexistent'); ?>
<!-- 如果组件不存在，会在该位置输出错误提示，但页面继续渲染 -->
```

**系统级错误：**

如果遇到系统级错误，如类不存在、方法未定义等，系统会停止主题加载并直接输出系统级错误页面，类似 WordPress 的严重错误页面。

**模板渲染错误：**

如果模板文件不存在，系统会抛出异常。可以在路由处理中捕获：

```php
try {
    Anon_Cms_Theme::render('nonexistent');
} catch (RuntimeException $e) {
    // 处理模板不存在的情况
    echo "模板未找到: " . $e->getMessage();
}
```

**错误类型判断：**

系统会自动判断错误类型：

- 组件调用错误：输出 HTML 错误信息，继续渲染
- 系统级错误，如 `Call to undefined method`、`Class not found`：停止主题加载，显示系统级错误页面
- 普通异常：按正常异常处理流程
