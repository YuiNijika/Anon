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
    │   ├── code.php      # 主题自定义代码，钩子、路由等
    │   └── setup.php     # 主题设置项，return 数组
    ├── index.php         # 首页模板
    ├── about.php         # 关于页模板
    ├── post.php          # 文章模板
    ├── category.php      # 分类页模板
    ├── components/
    │   ├── head.php      # 头部组件
    │   ├── foot.php      # 底部组件
    │   └── comments.php  # 评论区组件（可选，见 [评论功能](./comments.md)）
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

**模板中统一通过 `$this` 调用（类似 Typecho）：**

- **$this->post()** / **$this->page()** — 当前文章/页面对象（`Anon_Cms_Post`），无则 null
- **$this->user()** — 当前登录用户（`Anon_Cms_User`），未登录为 null  
  - `$this->user()->uid()`、`name()`、`email()`、`displayName()`、`avatar()`、`url()`（作者页链接）、`get($key, $default)`
- **$this->theme()** — 主题辅助（`Anon_Cms_Theme_Helper`）  
  - `$this->theme()->name()` 当前主题名  
  - `$this->theme()->get('key', $default)` 仅读主题选项  
  - `$this->theme()->siteUrl($suffix)` 站点根 URL，带参为拼接路径  
  - `$this->theme()->themeUrl($path)` / `$this->theme()->url($path)` 主题资源 URL  
  - `$this->theme()->index()` 站点首页 URL  
  - `$this->theme()->options()` 统一选项代理
- **$this->options()** — 统一选项代理（theme > plugin > system），`$this->options()->get('name', $default)`
- **$this->header($overrides)** — 输出 HTML 头部（同 headMeta），类似 Typecho header()
- **$this->archiveTitle()** — 当前归档标题（文章/页面标题或站点标题）
- **$this->keywords($separator, $default, $output)** / **$this->description($default, $output)** — 关键词、描述，可输出或仅返回
- **$this->permalink($post)**、**$this->posts()**、**$this->assets()**、**$this->components()**、**$this->escape()** 等见下文
- **$this->siteUrl($suffix = '')** — 获取站点 URL，可选拼接路径
  - `$this->siteUrl()` 返回站点根 URL（如 `https://example.com`）
  - `$this->siteUrl('/about')` 返回拼接后的完整 URL（如 `https://example.com/about`）
  - 自动从配置中读取站点 URL 或根据当前请求生成

插件基类中同样支持 **$this->options()**、**$this->user()**、**$this->theme()**。

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

- 设置值存储在 options 表
- 键名为 theme:主题名，小写，例如 theme:default
- 值为 JSON 对象
- 写入逻辑：有该 name 则更新 value，无则插入，与 Anon_Cms_Options::set 同款。主题层封装了 Anon_Theme_Options::setStorage，单键设置用 set 或 setMany 时内部会调用 setStorage 保证持久化。

### 注册设置项

在主题 app/code.php 中也可单条注册设置项，推荐使用 app/setup.php 统一定义：

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

在主题目录下创建 app/setup.php，return 一个数组，按 tab 分组。加载主题或管理端拉取设置项时会自动注册，无需再写其它代码：

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

**setup.php 中的 $this 上下文**

从 v1.1.0 开始，`app/setup.php` 支持使用 `$this` 上下文调用辅助方法，方便在配置中使用动态值：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    '基础设置' => [
        'navbar_links' => [
            'type' => 'text_list',
            'label' => '顶部导航',
            'description' => '网站顶部导航, 格式为 title|url',
            'default' => [
                '首页|' . $this->siteUrl(),
                '关于|' . $this->siteUrl('/about'),
            ],
            'listPlaceholder' => 'GitHub|https://github.com/YuiNijika/Anon',
        ],
        'logo_url' => [
            'type' => 'upload',
            'label' => 'Logo 图片',
            'default' => $this->themeUrl('logo.png'),
        ],
    ],
];
```

**setup.php 中可用的 $this 方法：**

- **$this->siteUrl($suffix = '')** - 获取站点 URL，可选拼接路径
  - `$this->siteUrl()` 返回 `https://example.com`
  - `$this->siteUrl('/about')` 返回 `https://example.com/about`
- **$this->themeUrl($path = '')** - 获取主题资源 URL
  - `$this->themeUrl('logo.png')` 返回主题资源完整 URL
- **$this->options()** - 获取选项代理对象
  - `$this->options()->get('title')` 读取站点选项
- **$this->theme()** - 获取主题辅助对象
  - `$this->theme()->name()` 获取主题名称

**设置项类型：**

| 类型 | 说明 |
|------|------|
| `text` | 单行文本输入框 |
| `textarea` | 多行文本域 |
| `select` | 下拉选择，需提供 `options` 键值对 |
| `checkbox` | 开关，布尔值 |
| `number` | 数字输入框，可设 `min`、`max`、`step` |
| `color` | 颜色选择器 |
| `date` / `time` / `datetime` | 日期、时间、日期时间 |
| `radio` | 单选组，需提供 `options` |
| `button_group` | 按钮组（多选一），需提供 `options` |
| `slider` | 滑块，可设 `min`、`max`、`step` |
| `badge` | 徽标展示（不保存） |
| `divider` | 分隔线（不保存） |
| `alert` | 警报块，可带 `actions` 打开对话框（不保存） |
| `notice` | 可关闭的提示（不保存） |
| `alert_dialog` | 警报 +「查看详情」按钮弹出对话框（不保存） |
| `content` | 说明文字块（不保存） |
| `heading` | 分组标题，可设 `level` 为 2/3/4（不保存） |
| `accordion` | 手风琴折叠块（不保存） |
| `result` | 结果空状态（不保存） |
| `card` | 卡片展示（不保存） |
| `tree_select` | 树形选择，需提供 `treeData` 或 `options` |
| `transfer` | 穿梭框，左右列表勾选，需 `transferOptions` 或 `options`，值为 key 数组 |
| `upload` | 输入框 + 本地上传，可设 `uploadAccept`、`uploadMultiple` |
| `description_list` | 描述列表展示，需 `descItems`（label/value 数组）（不保存） |
| `virtual_select` | 虚拟化选择器，大量选项时使用，需 `options` |
| `table` | 表格展示，需 `tableColumns`、`tableRows`（不保存） |
| `tooltip` | 文字提示，悬停显示，需 `tooltipContent`（不保存） |
| `tag` | 标签展示，需 `tags` 数组或 `text`（不保存） |
| `autocomplete` | 自动补全输入框，需 `options` 作为候选 |
| `text_list` | 动态文本列表：多行文本框，每行左侧输入、右侧可删；最后一行左侧输入 + 右侧「+1」按钮新增一项，值为字符串数组 |

**参数说明：**

- `type`: 设置项类型
- `label`: 显示标签
- `description`: 描述信息
- `default`: 默认值
- `options`: 选择项键值对，`select` / `radio` / `button_group` 需要
- `min` / `max` / `step`: `number`、`slider` 使用
- `variant`: 展示型组件样式，如 `default`、`destructive`、`success`、`warning`
- `actions`: `alert` 使用，数组 `[{ label, dialog?, dialogTitle?, dialogMessage? }]`，带 `dialog: true` 的项会弹出确认对话框
- `dismissible`: `notice` 是否可关闭
- `buttonText` / `dialogTitle` / `dialogDescription` / `dialogConfirmText`: `alert_dialog` 使用
- `level`: `heading` 标题级别 2|3|4
- `message` / `title` / `text`: 各展示型组件的正文
- `treeData`: `tree_select` 树节点数组，每项 `{ value, label, children? }`
- `transferOptions`: `transfer` 选项键值对，值为选中 key 数组
- `uploadAccept`: `upload` 接受类型，如 `image/*`
- `uploadMultiple`: `upload` 是否多选
- `descItems`: `description_list` 描述项 `[{ label, value }]`
- `tableColumns`: `table` 列定义 `[{ key, title }]`
- `tableRows`: `table` 行数据对象数组
- `tooltipContent`: `tooltip` 悬停提示内容
- `tags`: `tag` 标签文案数组
- `listPlaceholder`: `text_list` 每行输入框的占位文案
- `sanitize_callback`: 数据清理回调
- `validate_callback`: 数据验证回调

### 设置项 Demo 示例

以下为可在 `app/setup.php` 中直接使用的**完整示例**，覆盖所有设置项类型，便于在管理后台「主题设置」中查看各组件效果：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    '基本输入' => [
        'intro' => [
            'type' => 'content',
            'label' => '说明',
            'message' => '以下为所有类型的演示，修改后点击保存即可生效。',
        ],
        'site_title' => [
            'type' => 'text',
            'label' => '网站标题',
            'description' => '显示在页面中的标题',
            'default' => '我的网站',
        ],
        'site_description' => [
            'type' => 'textarea',
            'label' => '网站描述',
            'description' => '多行文本',
            'default' => '',
        ],
        'post_count' => [
            'type' => 'number',
            'label' => '首页文章数',
            'default' => 10,
            'min' => 1,
            'max' => 50,
        ],
        'show_sidebar' => [
            'type' => 'checkbox',
            'label' => '显示侧边栏',
            'default' => true,
        ],
        'custom_links' => [
            'type' => 'text_list',
            'label' => '自定义链接列表',
            'description' => '每行一个链接或文案，左侧输入、右侧 +1 新增一项',
            'default' => [],
            'listPlaceholder' => '输入链接或名称，点击右侧 +1 新增',
        ],
    ],
    '选择类' => [
        'color_scheme' => [
            'type' => 'select',
            'label' => '配色方案',
            'default' => 'light',
            'options' => ['light' => '浅色', 'dark' => '深色', 'auto' => '自动'],
        ],
        'list_style' => [
            'type' => 'radio',
            'label' => '列表样式',
            'default' => 'list',
            'options' => ['list' => '列表', 'card' => '卡片', 'grid' => '网格'],
        ],
        'sidebar_pos' => [
            'type' => 'button_group',
            'label' => '侧边栏位置',
            'default' => 'right',
            'options' => ['left' => '左侧', 'right' => '右侧', 'none' => '无'],
        ],
        'category_pick' => [
            'type' => 'tree_select',
            'label' => '分类（树形）',
            'default' => 'tech',
            'treeData' => [
                ['value' => 'news', 'label' => '新闻', 'children' => [
                    ['value' => 'tech', 'label' => '科技'],
                    ['value' => 'sport', 'label' => '体育'],
                ]],
                ['value' => 'blog', 'label' => '博客'],
            ],
        ],
        'enabled_features' => [
            'type' => 'transfer',
            'label' => '启用功能（穿梭框）',
            'default' => ['search', 'comment'],
            'transferOptions' => [
                'search' => '站内搜索',
                'comment' => '评论',
                'rss' => 'RSS',
                'sitemap' => '站点地图',
            ],
        ],
        'virtual_demo' => [
            'type' => 'virtual_select',
            'label' => '虚拟化选择（大量选项）',
            'default' => 'opt1',
            'options' => [
                'opt1' => '选项 1', 'opt2' => '选项 2', 'opt3' => '选项 3',
                'opt4' => '选项 4', 'opt5' => '选项 5', 'opt6' => '选项 6',
                'opt7' => '选项 7', 'opt8' => '选项 8', 'opt9' => '选项 9', 'opt10' => '选项 10',
            ],
        ],
        'keyword_hint' => [
            'type' => 'autocomplete',
            'label' => '关键词提示（自动补全）',
            'default' => '',
            'options' => ['主题' => '主题', '插件' => '插件', '设置' => '设置', '文章' => '文章', '评论' => '评论'],
        ],
    ],
    '其它输入' => [
        'theme_color' => [
            'type' => 'color',
            'label' => '主题色',
            'default' => '#3b82f6',
        ],
        'publish_date' => [
            'type' => 'date',
            'label' => '发布日期',
            'default' => '',
        ],
        'open_time' => [
            'type' => 'time',
            'label' => '开放时间',
            'default' => '09:00',
        ],
        'event_at' => [
            'type' => 'datetime',
            'label' => '活动时间',
            'default' => '',
        ],
        'opacity' => [
            'type' => 'slider',
            'label' => '透明度',
            'default' => 80,
            'min' => 0,
            'max' => 100,
            'step' => 5,
        ],
    ],
    '上传' => [
        'logo_url' => [
            'type' => 'upload',
            'label' => 'Logo 图片',
            'description' => '输入 URL 或点击选择本地上传',
            'default' => '',
            'uploadAccept' => 'image/*',
            'uploadMultiple' => false,
        ],
    ],
    '展示组件（不保存）' => [
        'h2_demo' => [
            'type' => 'heading',
            'label' => '展示类组件示例',
            'level' => 2,
        ],
        'alert_with_dialog' => [
            'type' => 'alert',
            'label' => '重要提示',
            'message' => '请先阅读文档再修改高级选项。',
            'variant' => 'warning',
            'actions' => [
                ['label' => '查看说明', 'dialog' => true, 'dialogTitle' => '说明', 'dialogMessage' => '此处为对话框内容。'],
                ['label' => '仅按钮'],
            ],
        ],
        'danger_zone' => [
            'type' => 'alert_dialog',
            'label' => '危险操作',
            'message' => '执行前请确认已备份数据。',
            'variant' => 'destructive',
            'buttonText' => '查看详情',
            'dialogTitle' => '危险操作说明',
            'dialogDescription' => '此操作不可恢复，请谨慎执行。',
            'dialogConfirmText' => '我知道了',
        ],
        'dismissible_notice' => [
            'type' => 'notice',
            'label' => '新版本可用',
            'message' => '请到后台检查更新。',
            'variant' => 'success',
            'dismissible' => true,
        ],
        'faq_block' => [
            'type' => 'accordion',
            'label' => '常见问题',
            'description' => '如何修改主题？在「主题设置」中修改并保存。如何恢复默认？删除对应选项后保存即可。',
        ],
        'divider_demo' => ['type' => 'divider'],
        'badge_demo' => [
            'type' => 'badge',
            'label' => '徽标',
            'variant' => 'secondary',
            'text' => '仅展示',
        ],
        'result_demo' => [
            'type' => 'result',
            'label' => '结果空状态',
            'status' => 'empty',
            'title' => '暂无数据',
            'description' => '可设置 status 为 empty / success / info / error。',
        ],
        'card_demo' => [
            'type' => 'card',
            'label' => '卡片',
            'title' => '卡片标题',
            'description' => '卡片描述内容。',
        ],
        'desc_list_demo' => [
            'type' => 'description_list',
            'label' => '描述列表',
            'descItems' => [
                ['label' => '主题版本', 'value' => '1.0.0'],
                ['label' => '框架', 'value' => 'Anon'],
                ['label' => 'PHP', 'value' => '8.0+'],
            ],
        ],
        'table_demo' => [
            'type' => 'table',
            'label' => '表格展示',
            'tableColumns' => [
                ['key' => 'name', 'title' => '名称'],
                ['key' => 'value', 'title' => '值'],
            ],
            'tableRows' => [
                ['name' => '站点名', 'value' => '示例站'],
                ['name' => '语言', 'value' => 'zh-CN'],
            ],
        ],
        'tooltip_demo' => [
            'type' => 'tooltip',
            'label' => '悬停看提示',
            'tooltipContent' => '这里是文字提示内容，鼠标悬停在标签上即可看到。',
        ],
        'tag_demo' => [
            'type' => 'tag',
            'label' => '标签',
            'tags' => ['PHP', '主题', 'CMS', 'Anon'],
            'variant' => 'secondary',
        ],
    ],
];
```

将上述内容放入主题的 `app/setup.php` 后，在管理后台进入「主题 → 主题设置」即可看到全部类型的展示效果：

- **基本输入**：content、text、textarea、number（支持 min/max/step）、checkbox、text_list（动态列表 +1 新增）  
- **选择类**：select、radio、button_group、tree_select、transfer、virtual_select、autocomplete  
- **其它输入**：color、date、time、datetime、slider  
- **上传**：upload（输入框 + 本地上传）  
- **展示组件**：heading、alert、alert_dialog、notice、accordion、divider、badge、result、card、description_list、table、tooltip、tag（以上展示型不参与保存）

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

`$this->options()` 返回统一选项代理 `Anon_Cms_Options_Proxy`。主题内默认优先级：theme > plugin > system。

若只需读**当前主题**的选项（不合并插件/系统），可用 **$this->theme()->get('key', $default)**。

**方法：**

- **get(string $name, $default = null, bool $output = false, ?string $priority = null)**  
  - `$name` 选项名，`$default` 默认值  
  - `$output`：true 先 echo 再返回，false 仅返回  
  - `$priority`：plugin、theme、system 之一，null 时按 theme > plugin > system  
- **set(string $name, $value)**：写入系统 options 表

**示例：**

```php
<!-- 获取站点标题，默认主题 > 插件 > 系统 -->
<h1><?php echo $this->escape($this->options()->get('title', '我的网站')); ?></h1>

<!-- 仅从系统 options 读 -->
<?php $siteTitle = $this->options()->get('title', '', false, 'system'); ?>

<!-- 先 echo 再返回 -->
<?php $this->options()->get('title', '我的网站', true, null); ?>

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

### 整表写入 setStorage

若需将主题选项整体写入 options 表，有则更新、无则插入，可直接调用：

```php
$ok = Anon_Theme_Options::setStorage([
    'site_title' => '新标题',
    'site_description' => '新描述',
], $themeName);
```

set 与 setMany 内部会调用 setStorage，一般无需直接使用。

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

### siteUrl() 方法

获取站点根 URL 或拼接相对路径，生成完整的 URL：

```php
$this->siteUrl(string $suffix = ''): string
```

**参数：**

- `$suffix`: 可选，相对路径后缀，如 `/about`、`/post/1`

**返回值：**

- 返回完整的 URL 字符串

**功能特点：**

1. **自动获取站点 URL**：优先从 `options` 表的 `site_url` 配置读取
2. **自动生成**：如果未配置，根据当前请求自动生成（包括协议和主机名）
3. **路径拼接**：自动处理路径分隔符，确保 URL 格式正确

**使用示例：**

```php
<!-- 获取站点根 URL -->
<a href="<?php echo $this->siteUrl(); ?>">首页</a>
<!-- 输出: <a href="https://example.com">首页</a> -->

<!-- 拼接相对路径 -->
<a href="<?php echo $this->siteUrl('/about'); ?>">关于</a>
<!-- 输出: <a href="https://example.com/about">关于</a> -->

<a href="<?php echo $this->siteUrl('/post/123'); ?>">文章</a>
<!-- 输出: <a href="https://example.com/post/123">文章</a> -->

<!-- 在导航菜单中使用 -->
<nav>
  <a href="<?php echo $this->siteUrl(); ?>">首页</a>
  <a href="<?php echo $this->siteUrl('/blog'); ?>">博客</a>
  <a href="<?php echo $this->siteUrl('/about'); ?>">关于</a>
</nav>

<!-- 生成 canonical URL -->
<link rel="canonical" href="<?php echo $this->siteUrl('/post/' . $post->id()); ?>">

<!-- 生成 Open Graph URL -->
<meta property="og:url" content="<?php echo $this->siteUrl('/post/' . $post->id()); ?>">

<!-- RSS Feed URL -->
<link rel="alternate" type="application/rss+xml" 
      href="<?php echo $this->siteUrl('/feed'); ?>">
```

**与 permalink() 的区别：**

- **siteUrl()**: 简单的 URL 拼接，直接将路径附加到站点根 URL
- **permalink()**: 智能生成链接，根据路由配置和内容类型自动匹配模板

```php
<!-- siteUrl() - 简单拼接 -->
<?php echo $this->siteUrl('/about'); ?>
<!-- 输出: https://example.com/about -->

<!-- permalink() - 根据路由配置生成 -->
<?php echo $this->permalink($post); ?>
<!-- 如果路由配置为 /article/{slug}，输出: https://example.com/article/hello-world -->
```

**配置站点 URL：**

在管理后台的系统设置中配置 `site_url`，或在 `options` 表中设置：

```php
Anon_Cms_Options::set('site_url', 'https://example.com');
```

如果未配置，系统会根据当前请求自动检测：

```php
// 自动检测示例
// 如果当前访问 https://example.com/about
$this->siteUrl()  // 返回: https://example.com
```

### themeUrl() 方法

获取主题资源的 URL，自动处理资源路径：

```php
$this->themeUrl(string $path = ''): string
// 别名方法
$this->url(string $path = ''): string
```

**参数：**

- `$path`: 资源文件相对于主题目录的路径，如 `style.css`、`js/main.js`

**返回值：**

- 返回完整的资源 URL 字符串

**功能特点：**

1. **自动定位**：自动定位到当前主题目录
2. **资源映射**：支持自动映射 `assets` 目录下的文件（如 `css` -> `assets/css`）
3. **缓存控制**：自动添加版本号或开发模式下的 `nocache` 参数

**使用示例：**

```php
<!-- 引用主题下的 CSS 文件 -->
<link rel="stylesheet" href="<?php echo $this->themeUrl('style.css'); ?>">

<!-- 引用图片 -->
<img src="<?php echo $this->url('images/logo.png'); ?>" alt="Logo">
```

### server() 方法

获取服务器环境信息：

```php
$this->server(string $type): string
```

**参数：**

- `$type`: 信息类型
  - `name`: 服务器软件名称 (e.g. Apache/Nginx)
  - `version`: 服务器版本
  - `os`: 操作系统
  - `php`: PHP 版本
  - `ip`: 服务器 IP
  - `port`: 端口
  - `domain`: 域名
  - `protocol`: 协议
  - `url`: 当前完整 URL
  - `isHttps`: 是否 HTTPS (返回 "true"/"false")

**使用示例：**

```php
<footer>
  <p>Server: <?php echo $this->server('name'); ?> / PHP: <?php echo $this->server('php'); ?></p>
</footer>
```

### framework() 方法

获取框架信息：

```php
$this->framework(string $type): string
```

**参数：**

- `$type`: 信息类型
  - `name`: 框架名称
  - `version`: 版本号
  - `author`: 作者
  - `github`: GitHub 地址
  - `license`: 许可协议

**使用示例：**

```php
<div class="copyright">
  Powered by <a href="<?php echo $this->framework('github'); ?>"><?php echo $this->framework('name'); ?></a>
</div>
```

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

### getPageType() 方法

获取页面类型：

```php
$this->getPageType(string $templateName): string
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
$pageType = $this->getPageType('post'); // 返回 'post'
$pageType = $this->getPageType('pages/Test'); // 返回 'other'
$pageType = $this->getPageType('error'); // 返回 'error'
```

### partial() 方法

包含模板片段：

```php
$this->partial(string $partialName, array $data = []): void
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

### assets() 方法

获取主题资源 URL 或自动输出 HTML 标签：

```php
$this->assets(string $path, ?string $type = null, array $attributes = []): string
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

### components() 方法

导入组件，类似 Vue 的组件导入机制：

```php
$this->components(string $componentPath, array $data = []): void
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

### getCurrentTheme() 方法

获取当前主题名称：

```php
$themeName = $this->theme()->name(); // 返回 'default'
```

### info() 方法

获取主题信息，从主题目录的 `package.json` 文件中读取：

```php
$this->theme()->info(?string $key = null): mixed
```

**参数：**

- `$key`: 信息键名，可选，如果为 null 则返回所有信息

**返回值：**

- 如果指定了 `$key`，返回对应的值
- 如果 `$key` 为 null，返回包含所有主题信息的数组

**示例：**

```php
// 获取所有主题信息
$themeInfo = $this->theme()->info();
// 返回: ['name' => 'Default', 'description' => '默认主题', 'author' => 'YuiNijika', ...]

// 获取特定信息
$themeName = $this->theme()->info('name'); // 返回 'Default'
$author = $this->theme()->info('author'); // 返回 'YuiNijika'
$version = $this->theme()->info('version'); // 返回 '1.0.0'
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

### isActive() 方法

检查当前主题是否为激活状态：

```php
$this->theme()->isActive(): bool
```

### dir() 方法

获取当前主题的绝对路径：

```php
$this->theme()->dir(): string
```

## 用户与权限 API

### user() 方法

获取当前登录用户对象：

```php
$this->user(): ?Anon_Cms_User
```

**返回值：**

- 已登录返回 `Anon_Cms_User` 对象
- 未登录返回 `null`

**Anon_Cms_User 对象方法：**

- `uid(): int` - 用户 ID
- `name(): string` - 用户名
- `email(): string` - 邮箱
- `displayName(): string` - 显示名称（昵称或用户名）
- `avatar(): string` - 头像 URL
- `url(): string` - 用户主页链接
- `group(): string` - 用户组
- `get(string $key, $default = null)` - 获取自定义字段

**示例：**

```php
<?php $user = $this->user(); ?>
<?php if ($user): ?>
    <div class="user-panel">
        <img src="<?php echo $user->avatar(); ?>" alt="<?php echo $user->displayName(); ?>">
        <span>你好，<?php echo $user->displayName(); ?></span>
    </div>
<?php else: ?>
    <a href="/login">登录</a>
<?php endif; ?>
```

### profileUser() 方法

在用户主页（如 `/author/1` 或 `/user/admin`）获取当前被访问的用户对象：

```php
$this->profileUser(): ?Anon_Cms_User
```

**返回值：**

- 成功解析用户返回 `Anon_Cms_User` 对象
- 否则返回 `null`

### isLoggedIn() 方法

检查当前用户是否已登录：

```php
$this->isLoggedIn(): bool
```

## 数据与逻辑 API

### is() 方法

判断当前页面类型：

```php
$this->is(string $type): bool
```

**参数：**

- `$type`: 页面类型，如 `index`, `post`, `page`, `category`, `tag`, `archive`, `search`, `404`

**示例：**

```php
<?php if ($this->is('post')): ?>
    <h1>这是文章页</h1>
<?php endif; ?>
```

### archiveTitle() 方法

获取当前归档标题（文章/页面标题或站点标题）：

```php
$this->archiveTitle(): string
```

### keywords() 方法

获取或输出站点关键词：

```php
$this->keywords(string $separator = ',', string $default = '', bool $output = true): string
```

### description() 方法

获取或输出站点描述：

```php
$this->description(string $default = '', bool $output = true): string
```

### getPostComments() 方法

获取文章的评论列表：

```php
$this->getPostComments(int $postId): array
```

**参数：**

- `$postId`: 文章 ID

**返回值：**

- 评论数组，每项包含评论详细信息

### getPostIfExists() 方法

尝试获取文章数据，如果不存在返回 null：

```php
$this->getPostIfExists(?int $id = null): ?array
```

### getPageIfExists() 方法

尝试获取页面数据，如果不存在返回 null：

```php
$this->getPageIfExists(?string $slug = null): ?array
```

### getPageLoadTime() 方法

获取页面加载时间（秒）：

```php
$this->getPageLoadTime(): float
```

### getCommentDisplayName() 方法

获取当前用户的评论显示名称：

```php
$this->getCommentDisplayName(): string
```

### getTemplateExtensions() 方法

获取支持的模板文件扩展名：

```php
$this->getTemplateExtensions(): array
```

## HTML 辅助方法

### headMeta() 方法

输出页面 head 部分的 meta 标签，包括 title 和 SEO 信息：

```php
$this->headMeta(array $overrides = []): void
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

### footMeta() 方法

输出页面底部 meta 信息，触发 `theme_foot` 钩子：

```php
$this->footMeta(): void
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

### title() 方法

输出页面标题标签：

```php
$this->title(?string $title = null, string $separator = ' - ', bool $reverse = false): void
```

**参数：**

- `$title`: 页面标题，如果为 null 则使用站点名称
- `$separator`: 标题和站点名称之间的分隔符
- `$reverse`: 是否反转顺序，站点名称在前

**示例：**

```php
<?php $this->title('文章标题'); ?>
<!-- 输出: <title>文章标题 - 站点名称</title> -->

<?php $this->title('文章标题', ' | ', true); ?>
<!-- 输出: <title>站点名称 | 文章标题</title> -->
```

### meta() 方法

输出 SEO meta 标签：

```php
$this->meta(array $meta = []): void
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
<?php $this->meta([
    'description' => '这是页面描述',
    'keywords' => ['关键词1', '关键词2'],
    'canonical' => '/post/123',
    'og' => [
        'title' => '文章标题',
        'type' => 'article',
    ],
]); ?>
```

### stylesheet() 方法

输出样式表链接：

```php
$this->stylesheet(string|array $styles, array $attributes = []): void
```

**参数：**

- `$styles`: 样式文件路径，可以是字符串或数组
- `$attributes`: 额外属性数组

**示例：**

```php
<?php $this->stylesheet('style.css'); ?>
<!-- 输出: <link rel="stylesheet" href="/assets/css/style"> -->

<?php $this->stylesheet(['style.css', 'custom.css']); ?>
<!-- 输出多个样式表链接 -->

<?php $this->stylesheet('style.css', ['media' => 'print']); ?>
<!-- 输出带额外属性的样式表链接 -->
```

### script() 方法

输出脚本标签：

```php
$this->script(string|array $scripts, array $attributes = []): void
```

**参数：**

- `$scripts`: 脚本文件路径，可以是字符串或数组
- `$attributes`: 额外属性数组

**示例：**

```php
<?php $this->script('main.js'); ?>
<!-- 输出: <script src="/assets/js/main"></script> -->

<?php $this->script(['main.js', 'custom.js']); ?>
<!-- 输出多个脚本标签 -->

<?php $this->script('main.js', ['defer' => 'defer']); ?>
<!-- 输出带额外属性的脚本标签 -->
```

### favicon() 方法

输出 favicon 链接：

```php
$this->favicon(string $path = 'favicon.ico'): void
```

**示例：**

```php
<?php $this->favicon('favicon.ico'); ?>
<!-- 输出: <link rel="icon" href="/assets/images/favicon"> -->
```

### escape() 方法

转义 HTML 输出，防止 XSS 攻击：

```php
$this->escape(string $text, int $flags = ENT_QUOTES): string
```

**示例：**

```php
<h1><?php echo $this->escape($title ?? ''); ?></h1>
```

### jsonLd() 方法

输出 JSON-LD 结构化数据：

```php
$this->jsonLd(array $data): void
```

**示例：**

```php
<?php $this->jsonLd([
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
