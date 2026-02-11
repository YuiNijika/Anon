# 插件系统

一句话：类似 WordPress 的灵活插件系统，支持插件扫描、加载、激活/停用，通过钩子执行方法。

插件系统允许开发者在不修改核心代码的情况下扩展框架功能，插件位于 `server/app/Plugin/` 目录，每个插件都是一个独立的目录。

## 插件结构

### 基本结构

插件目录与主入口文件名不区分大小写，系统按 slug 解析实际目录名，并查找 Index.php 或 index.php 等。

```text
server/app/Plugin/
└── HelloWorld/
    ├── Index.php       # 主入口，必须，文件名不区分大小写
    └── package.json    # 可选，用于元数据，无则从 Index.php 头部注释读取
```

### 插件文件示例

```php
<?php
/**
 * Name: HelloWorld
 * Description: Hello World 插件
 * Mode: auto
 * Version: 1.0.0
 * Author: YuiNijika
 * URI: https://github.com/YuiNijika
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 插件主类，类名不区分大小写
class Anon_Plugin_HelloWorld
{
    /**
     * 插件初始化方法
     */
    public static function init()
    {
        // 判断当前应用模式
        if (Anon_System_Plugin::isApiMode()) {
            // API 模式下的初始化逻辑
            Anon::route('/hello', function () {
                Anon::success([
                    self::index()
                ], 'Hello World from Plugin (API Mode)');
            }, [
                'header' => true,
                'requireLogin' => false,
                'method' => ['GET'],
                'token' => false,
                'cache' => [
                    'enabled' => true,
                    'time' => 3600,
                ],
            ]);
        } elseif (Anon_System_Plugin::isCmsMode()) {
            // CMS 模式下的初始化逻辑
            Anon::route('/hello', function () {
                Anon::success([
                    self::index()
                ], 'Hello World from Plugin (CMS Mode)');
            }, [
                'header' => true,
                'requireLogin' => false,
                'method' => ['GET'],
                'token' => false,
                'cache' => [
                    'enabled' => true,
                    'time' => 3600,
                ],
            ]);
        }
    }

    /**
     * 插件激活时调用
     */
    public static function activate()
    {
        Anon_Debug::info('HelloWorld 插件已激活');
    }

    /**
     * 插件停用时调用
     */
    public static function deactivate()
    {
        Anon_Debug::info('HelloWorld 插件已停用');
    }

    /**
     * 自定义方法
     */
    public static function index()
    {
        return 'Hello World';
    }
}
```

### 1.9 高级插件结构（推荐）

为了更好地组织大型插件，框架支持一种基于模式（Mode）和配置分离的高级插件结构。这种结构能自动根据运行模式加载代码，并支持独立的配置文件。

**目录结构：**

```text
server/app/Plugin/
└── AppList/
    ├── app/
    │   ├── mode/
    │   │   ├── api.php      # 仅在 API 模式下加载
    │   │   └── cms.php      # 仅在 CMS 模式下加载
    │   ├── pages.php        # 自定义管理后台页面配置
    │   └── setup.php        # 插件设置项配置
    ├── index.php            # 极简入口文件
    └── package.json         # 元数据
```

**入口文件 (`index.php`)：**

只需继承 `Anon_Plugin_Base`，无需手动编写 `init` 加载逻辑：

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_AppList extends Anon_Plugin_Base
{
    // 基类会自动加载 app/mode/ 下的对应模式文件
    
    public static function activate()
    {
        // 激活逻辑
    }

    public static function deactivate()
    {
        // 停用逻辑
    }
}
```

**业务逻辑 (`app/mode/api.php`)：**

在此文件中直接编写 API 模式下的路由和逻辑，$this 指向插件实例：

```php
<?php
// 获取插件实例
$plugin = $this;

Anon::route('/app-list/v1/list', function () use ($plugin) {
    // 获取配置
    $options = $plugin->options()->get();
    // ...
});
```

**配置文件 (`app/setup.php`)：**

支持按模式分组配置，系统会自动合并：

```php
<?php
return [
    'api' => [
        'api_key' => [
            'type' => 'text',
            'label' => 'API 密钥'
        ]
    ],
    'cms' => [
        'enable_feature' => [
            'type' => 'boolean',
            'label' => '启用功能'
        ]
    ]
];
```

**优势：**

*   **职责分离**：API 和 CMS 逻辑物理隔离，互不干扰。
*   **配置清晰**：设置项独立管理，支持分组。
*   **开发高效**：基类自动处理加载，减少样板代码。

## 插件自定义页面 (Plugin Pages)

插件可以在管理后台注册自定义页面，支持使用 **Vue 3 + Element Plus** 进行开发，系统采用了混合渲染架构（React Host + Vue Guest）。

### 页面配置 (`app/pages.php`)

在插件的 `app` 目录下创建 `pages.php`，返回一个数组，键为页面标识符（slug），值为配置数组。

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'my-page-slug' => [
        'title' => '页面标题',
        'content' => 'HTML 内容',
        'handler' => function($action, $data) { ... }
    ]
];
```

### 前端开发 (Vue 3 + Element Plus)

页面内容通过 `content` 字段返回 HTML 字符串。你可以在其中编写 Element Plus 组件，并通过 `<script>` 标签定义 Vue 组件逻辑。

**关键机制：**
1.  **挂载点**：无需手动挂载，系统会自动查找 `window.AnonPluginPage` 对象并挂载 Vue 应用。
2.  **组件定义**：将 Vue 组件选项对象（data, methods, mounted 等）赋值给全局变量 `window.AnonPluginPage`。
3.  **Element Plus**：环境已内置 Vue 3 和 Element Plus，可直接使用 `<el-button>`、`<el-table>` 等组件，无需额外引入。

**完整示例 (`app/pages.php`)：**

```php
<?php
return [
    'demo-page' => [
        'title' => '示例页面',
        'content' => <<<HTML
<!-- 模板部分：直接写 Element Plus 组件 -->
<el-card>
    <template #header>
        <div class="card-header">
            <span>用户列表</span>
            <el-button class="button" text @click="fetchData">刷新</el-button>
        </div>
    </template>
    
    <el-table :data="tableData" style="width: 100%" v-loading="loading">
        <el-table-column prop="date" label="日期" width="180"></el-table-column>
        <el-table-column prop="name" label="姓名" width="180"></el-table-column>
        <el-table-column prop="address" label="地址"></el-table-column>
        <el-table-column label="操作">
            <template #default="scope">
                <el-button size="small" @click="handleEdit(scope.row)">编辑</el-button>
            </template>
        </el-table-column>
    </el-table>
</el-card>

<!-- 逻辑部分：定义 Vue 组件 -->
<script>
window.AnonPluginPage = {
    data() {
        return {
            tableData: [],
            loading: false
        };
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        fetchData() {
            this.loading = true;
            setTimeout(() => {
                this.tableData = [
                    { date: '2024-05-01', name: 'Tom', address: 'No. 189, Grove St, Los Angeles' },
                    { date: '2024-05-02', name: 'Jerry', address: 'No. 189, Grove St, Los Angeles' },
                ];
                this.loading = false;
            }, 1000);
        },
        handleEdit(row) {
            console.log('Edit', row);
            this.$message.success('编辑: ' + row.name);
        }
    }
};
</script>
HTML,
        // 后端处理逻辑 (可选)
        'handler' => function ($action, $data) {
            if ($action === 'get_data') {
                return ['data' => 'Server Data'];
            }
            return null;
        }
    ]
];
```

### 访问页面

页面注册后，可以通过 URL 访问：
`/#/pages?plugin={插件Slug}:{页面Slug}`

例如插件名为 `AppList`，页面名为 `demo-page`，则路径为 `/pages?plugin=applist:demo-page`。

## 扩展管理后台菜单 (Admin Navbar)

插件可以通过钩子 `admin_navbar_sidebar` 向管理后台侧边栏添加菜单项。支持多级嵌套和挂载到现有分组。

### 添加顶级菜单与子菜单

在 `app/mode/cms.php` 或 `init()` 中注册钩子：

```php
Anon::filter('admin_navbar_sidebar', function ($items) {
    $items[] = [
        'key' => 'miosoft', // 唯一标识
        'icon' => 'AppstoreOutlined', // 支持的图标 key
        'label' => 'MioSoft',
        'children' => [
            [
                'key' => '/pages?plugin=applist:add-app', // 链接到插件页面
                'label' => '应用列表',
                'icon' => 'AppstoreOutlined',
            ],
            [
                'key' => '/pages?plugin=applist:manage-types',
                'label' => '分类管理',
                'icon' => 'FolderOutlined',
            ],
        ],
    ];
    return $items;
});
```

### 挂载到现有菜单组

使用 `Anon_Cms_Admin_UI_Navbar::mount` 辅助方法，可以将菜单项插入到现有的分组中（如 `manage` 管理, `settings` 设置）。

```php
Anon::filter('admin_navbar_sidebar', function ($items) {
    // 将菜单挂载到 "manage" (管理) 组下
    Anon_Cms_Admin_UI_Navbar::mount($items, 'manage', [
        'key' => '/pages?plugin=my-plugin:config',
        'label' => '插件配置',
        'icon' => 'SettingOutlined',
    ]);
    return $items;
});
```

## 插件元数据

插件元数据可来自 **package.json** 或 **主入口文件头部注释**，优先读取 `package.json`。

### 使用 package.json

在插件目录下放置 `package.json`，字段与 Node 惯例一致，模式与扩展配置放在 `anon` 下：

```json
{
  "name": "HelloWorld",
  "description": "Hello World",
  "version": "1.0.0",
  "author": "YuiNijika",
  "url": "https://github.com/YuiNijika",
  "anon": {
    "mode": "auto"
  }
}
```

- `name`：插件名称，必需
- `description`、`version`、`author`、`url` 或 `homepage`：选填
- `anon.mode`：api、cms 或 auto，默认 api

### 使用文件头注释

在 `Index.php` 顶部用注释定义元数据：

- `Name`: 插件名称，必需
- `Description`: 插件描述
- `Version`: 插件版本
- `Author`: 作者名称
- `URI`: 插件主页或作者主页
- `Mode`: 插件模式，默认为 `api`

**Mode 模式说明：**

- `api`: 仅在 API 模式下加载
- `cms`: 仅在 CMS 模式下加载
- `auto`: 在所有模式下都加载

**示例：**

```php
/**
 * Name: HelloWorld
 * Description: Hello World 插件
 * Mode: auto
 * Version: 1.0.0
 * Author: YuiNijika
 * URI: https://github.com/YuiNijika
 */
```

## 插件类命名规则

插件类名格式：`Anon_Plugin_{插件名称}`

插件名称从目录名获取，类名不区分大小写，系统自动匹配。目录 HelloWorld、helloworld 均对应类名 Anon_Plugin_HelloWorld。主入口文件名 Index.php 或 index.php 等均可识别。

## 配置插件系统

在 `server/app/useApp.php` 中配置：

```php
return [
    'app' => [
        'plugins' => [
            'enabled' => true,
            'active' => [],
        ],
        // ... 其他配置
    ]
];
```

### 激活特定插件

```php
'plugins' => [
    'enabled' => true,
    'active' => [
        'HelloWorld',
        'AnotherPlugin',
    ],
],
```

### 激活所有插件

```php
'plugins' => [
    'enabled' => true,
    'active' => [], // 空数组表示激活所有插件
],
```

## 路由注册

插件可以通过 `Anon::route()` 方法注册路由，支持完整的路由元数据配置：

```php
Anon::route('/hello', function () {
    // 路由处理逻辑
}, [
    'header' => true,
    'requireLogin' => false,
    'method' => ['GET'],
    'token' => false,
    'cache' => [
        'enabled' => true,
        'time' => 3600,
    ],
]);
```

### 路由元数据说明

- `header`: 是否设置响应头，默认 `true`
- `requireLogin`: 是否需要登录，默认 `false`
- `method`: 允许的 HTTP 方法，字符串或数组
- `token`: 是否需要 Token 验证，`null` 时使用全局配置
- `cache`: 缓存配置
  - `enabled`: 是否启用缓存
  - `time`: 缓存时间，单位秒

## 插件生命周期

### 1. 扫描阶段

系统扫描 `server/app/Plugin/` 目录，查找所有插件目录。

### 2. 加载阶段

- 读取插件元数据
- 查找插件主类
- 检查插件是否在激活列表中

### 3. 初始化阶段

- 调用插件的 `init()` 方法
- 在 `init()` 方法内可通过 `Anon_System_Plugin::isApiMode()` 或 `Anon_System_Plugin::isCmsMode()` 判断当前模式
- 执行插件注册的路由和钩子

### 4. 激活/停用

- `activate()`: 插件激活时调用
- `deactivate()`: 插件停用时调用

## 钩子系统集成

插件可以使用框架的钩子系统：

```php
public static function init()
{
    // 添加动作钩子
    Anon_System_Hook::add_action('some_action', function() {
        // 执行逻辑
    });

    // 添加过滤器钩子
    Anon_System_Hook::add_filter('some_filter', function($value) {
        return $value . ' modified';
    });
}
```

## 插件系统钩子

框架为插件系统提供了以下钩子：

- `plugin_system_before_init`: 插件系统初始化前
- `plugin_system_after_init`: 插件系统初始化后
- `plugin_before_scan`: 插件扫描前
- `plugin_after_scan`: 插件扫描后
- `plugin_before_load`: 插件加载前
- `plugin_after_load`: 插件加载后
- `plugin_load_error`: 插件加载错误时触发

## 插件设置

插件可提供“设置页”，供用户在管理后台配置选项。设置项**在插件入口文件中**通过静态方法 `getSettingsSchema()` 定义，不放在 package.json 中。

### 定义设置 schema

在插件主类中实现静态方法 `getSettingsSchema()`，返回字段名到定义的映射，含类型、标签、默认值等。继承 `Anon_Plugin_Base` 时，实例方法 `$this->options()` 为选项代理，与静态 schema 方法同名不冲突。

```php
/**
     * 设置页 schema，键为字段名，值为 type、label、default 等，供管理端读取
     * @return array
     */
    public static function getSettingsSchema(): array
    {
        return [
            'greeting' => [
                'type' => 'text',
                'label' => '问候语',
                'default' => 'Hello, World!',
            ],
            'show_count' => [
                'type' => 'checkbox',
                'label' => '显示计数',
                'default' => false,
            ],
        ];
    }
```

支持的 `type`：`text`、`textarea`、`select`、`checkbox`、`number`、`color`。`select` 需提供 `options` 数组。

### 存储方式

- 设置值存储在 **options 表**
- 键名为 plugin:插件名，小写，例如 plugin:helloworld
- 值为 **JSON 对象**

### 管理后台

- 插件列表操作菜单中有 **「设置」**，点击后进入 `/plugins?options=插件名` 的设置页
- 设置页仅显示表单与保存/重置按钮，不显示下方插件列表
- 后端接口：`GET /anon/cms/admin/plugins/options?slug=xxx` 获取 schema 与当前值，`POST /anon/cms/admin/plugins/options` 提交 `{ slug, values }` 保存

### 在代码中读取设置

若需在插件或主题中读取某插件的设置，可从 options 表读取 plugin:插件名 的 JSON 值，或通过 CMS Options 封装获取。

## 插件基类与 $this->options()

插件可继承 `Anon_Plugin_Base`，使用实例方法 `$this->options()` 参与统一选项优先级。

### 继承基类

继承后框架会实例化插件并调用实例方法 `init()`，在 `init()` 或其它实例方法中通过 `$this->options()` 获取选项代理。

- 插件内默认优先级：plugin > theme > system
- 主题内默认优先级：theme > plugin > system

### options() 代理方法

`$this->options()` 返回 `Anon_Cms_Options_Proxy`：

- **get(string $name, $default = null, bool $output = false, ?string $priority = null)**  
  - `$name` 选项名，`$default` 默认值  
  - `$output`：true 先 echo 再返回，false 仅返回  
  - `$priority`：plugin、theme、system 之一，null 时按上下文  
- **set(string $name, $value)**：写入系统 options 表

### 优先级含义

- **plugin**：仅从插件选项 options 表 `plugin:插件名` 取值
- **theme**：仅从当前主题选项 options 表 `theme:主题名` 取值
- **system**：仅从系统 options 表顶层键取值

不传 `$priority` 时，插件内按 plugin > theme > system，主题内按 theme > plugin > system。

### 示例：插件内使用选项代理

```php
<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_HelloWorld extends Anon_Plugin_Base
{
    public function init()
    {
        $plugin = $this;
        Anon::route('/hello', function () use ($plugin) {
            Anon::success(['message' => $plugin->index()], 'OK');
        }, ['method' => ['GET']]);
    }

    /** 管理端设置 schema */
    public static function options(): array
    {
        return [
            'greeting' => ['type' => 'text', 'label' => '问候语', 'default' => 'Hello, World!'],
        ];
    }

    /** 实例方法：读取选项，默认插件>主题>系统 */
    public function index()
    {
        $proxy = $this->options();
        if ($proxy === null) {
            return 'Hello, World!';
        }
        return $proxy->get('greeting', 'Hello, World!', false, null);
    }
}
```

### 指定优先级与输出方式

```php
// 仅返回值，按默认优先级
$val = $this->options()->get('greeting', 'Hi', false, null);

// 仅从系统 options 表读
$val = $this->options()->get('title', '', false, 'system');

// 仅从主题选项读
$val = $this->options()->get('title', '', false, 'theme');

// 先 echo 再返回
$this->options()->get('greeting', 'Hi', true, null);
```

## 插件管理

### 管理后台

在 CMS 模式下，可以通过管理后台的插件管理页面管理插件：

- **插件列表**：查看所有已安装的插件
- **上传插件**：上传 ZIP 格式的插件包
- **启用/停用**：切换插件的激活状态
- **设置**：进入插件设置页，仅当插件实现静态 `getSettingsSchema()` 时有表单
- **删除插件**：删除不需要的插件，需先停用

### 插件上传

插件必须以 ZIP 格式上传，ZIP 文件结构：

```
plugin-name.zip
└── PluginName/
    └── Index.php
```

上传后系统自动执行：

1. 解压 ZIP 文件
2. 验证插件结构，必须包含 `Index.php`
3. 读取插件元数据
4. 移动到插件目录

## 插件初始化方法

插件必须实现 `init()` 方法，在方法内可通过以下辅助方法判断当前应用模式：

### 判断应用模式

```php
public static function init()
{
    // 判断是否为 API 模式
    if (Anon_System_Plugin::isApiMode()) {
        // API 模式下的初始化逻辑
    }
    
    // 判断是否为 CMS 模式
    if (Anon_System_Plugin::isCmsMode()) {
        // CMS 模式下的初始化逻辑
    }
    
    // 获取当前模式字符串
    $mode = Anon_System_Plugin::getAppMode(); // 返回 'api' 或 'cms'
}
```

### 辅助方法说明

- `Anon_System_Plugin::isApiMode()`: 判断是否为 API 模式，返回 `bool`
- `Anon_System_Plugin::isCmsMode()`: 判断是否为 CMS 模式，返回 `bool`
- `Anon_System_Plugin::getAppMode()`: 获取当前应用模式，返回 `'api'` 或 `'cms'`

## 注意事项

1. 插件目录名与主入口文件名 Index.php 不区分大小写，类名也不区分大小写，系统自动匹配
2. 插件必须实现 `init()` 方法
3. 插件必须放在 `server/app/Plugin/` 目录下
4. 主入口文件名为 `Index.php` 或 `index.php` 等均可，系统按不区分大小写查找
5. 所有插件文件必须包含 `if (!defined('ANON_ALLOWED_ACCESS')) exit;`
6. 插件设置 schema 在入口文件中通过 `getSettingsSchema()` 定义，不写在 package.json

## 调试

启用调试模式后，可以在调试控制台查看插件加载信息：

- 插件扫描日志
- 插件加载状态
- 插件元数据信息

## 示例：完整插件

```php
<?php
/**
 * Name: UserStats
 * Description: 用户统计插件
 * Mode: auto
 * Version: 1.0.0
 * Author: Your Name
 * URI: https://example.com
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_UserStats
{
    public static function init()
    {
        // 注册用户统计路由
        Anon::route('/api/user/stats', function () {
            $user = Anon_Http_Request::requireAuth();
            $stats = self::getUserStats($user['uid']);
            Anon::success($stats, '获取统计数据成功');
        }, [
            'requireLogin' => true,
            'method' => ['GET'],
        ]);

        // 注册钩子
        Anon_System_Hook::add_action('user_login', [self::class, 'onUserLogin']);
    }

    public static function activate()
    {
        // 创建统计表
        $db = Anon_Database::getInstance();
        
        // 检查表是否存在
        if (!$db->tableExists('user_stats')) {
            $db->createTable('user_stats', [
                'user_id' => [
                    'type' => 'INT',
                    'null' => false,
                    'primary' => true,
                    'key' => 'PRI',
                ],
                'login_count' => [
                    'type' => 'INT',
                    'null' => false,
                    'default' => 0,
                ],
                'last_login' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }
    }

    public static function deactivate()
    {
        Anon_Debug::info('UserStats 插件已停用');
    }

    private static function getUserStats($userId)
    {
        $db = Anon_Database::getInstance();
        return $db->db('user_stats')
            ->where('user_id', '=', $userId)
            ->first();
    }

    public static function onUserLogin($userId)
    {
        $db = Anon_Database::getInstance();
        
        // 检查用户统计是否存在
        $exists = $db->db('user_stats')
            ->where('user_id', '=', $userId)
            ->exists();
        
        if ($exists) {
            // 更新现有记录
            $current = $db->db('user_stats')
                ->where('user_id', '=', $userId)
                ->first();
            
            $db->db('user_stats')
                ->where('user_id', '=', $userId)
                ->update([
                    'login_count' => ($current['login_count'] ?? 0) + 1,
                    'last_login' => date('Y-m-d H:i:s')
                ]);
        } else {
            // 插入新记录
            $db->db('user_stats')->insert([
                'user_id' => $userId,
                'login_count' => 1,
                'last_login' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
```
