# 工具类

HTML转义、数据清理、文本处理、数组操作等常用工具函数。

::: tip 代码示例说明
本文档中的代码示例使用了简化的类名。在实际使用时，需要在文件顶部添加相应的 `use` 语句：

```php
use Anon\Modules\Security\Escape;
use Anon\Modules\Utils\Helper;
use Anon\Modules\Utils\Validate;
```
:::

## 辅助函数

### HTML转义

```php
<?php
use Anon\Modules\Security\Escape;

// HTML转义
$escaped = Escape::html('<script>alert("xss")</script>');
// 返回: &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;

$url = Escape::url('https://example.com');
$attr = Escape::attr('value with "quotes"');
$js = Escape::js('alert("test")');
```

### 数据清理

```php
<?php
use Anon\Modules\Security\Sanitize;

// 数据清理
$clean = Sanitize::text('<p>HTML</p>');
$email = Sanitize::email('user@example.com');
$url = Sanitize::url('https://example.com');
```

### 验证

```php
<?php
use Anon\Modules\Security\Validate;

// 验证
if (Validate::email('user@example.com')) {
    // 有效邮箱
}
if (Validate::url('https://example.com')) {
    // 有效URL
}
```

### 文本处理

```php
<?php
use Anon\Modules\Utils\Text;

// 文本处理
$truncated = Text::truncate('很长的文本', 10);
// 返回: '很长的文本...'

$slug = Text::slugify('Hello World!');
// 返回: 'hello-world'

$timeAgo = Text::timeAgo(time() - 3600);
// 返回: '1小时前'
```

### 格式化

```php
<?php
use Anon\Modules\Utils\Format;
use Anon\Modules\Utils\Random;

// 格式化
$size = Format::bytes(1048576);
// 返回: '1.00 MB'

$random = Random::string(32);
// 返回: 32位随机字符串
```

### 数组操作

```php
<?php
use Anon\Modules\Utils\ArrayHelper;

// 数组操作
$value = ArrayHelper::get($array, 'user.profile.name', 'default');
// 支持点号分隔的嵌套键

ArrayHelper::set($array, 'user.profile.name', 'value');
// 设置嵌套键值

$merged = ArrayHelper::merge($array1, $array2);
// 深度合并数组
```
