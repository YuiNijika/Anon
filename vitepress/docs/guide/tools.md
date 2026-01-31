# 工具类

一句话：HTML转义、数据清理、文本处理、数组操作等常用工具函数。

## 辅助函数 (Anon_Helper)

### HTML转义

```php
// HTML转义
$escaped = Anon_Helper::escHtml('<script>alert("xss")</script>');
// 返回: &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;

$url = Anon_Helper::escUrl('https://example.com');
$attr = Anon_Helper::escAttr('value with "quotes"');
$js = Anon_Helper::escJs('alert("test")');
```

### 数据清理

```php
// 数据清理
$clean = Anon_Helper::sanitizeText('<p>HTML</p>');
$email = Anon_Helper::sanitizeEmail('user@example.com');
$url = Anon_Helper::sanitizeUrl('https://example.com');
```

### 验证

```php
// 验证
if (Anon_Helper::isValidEmail('user@example.com')) {
    // 有效邮箱
}
if (Anon_Helper::isValidUrl('https://example.com')) {
    // 有效URL
}
```

### 文本处理

```php
// 文本处理
$truncated = Anon_Helper::truncate('很长的文本', 10);
// 返回: '很长的文本...'

$slug = Anon_Helper::slugify('Hello World!');
// 返回: 'hello-world'

$timeAgo = Anon_Helper::timeAgo(time() - 3600);
// 返回: '1小时前'
```

### 格式化

```php
// 格式化
$size = Anon_Helper::formatBytes(1048576);
// 返回: '1.00 MB'

$random = Anon_Helper::randomString(32);
// 返回: 32位随机字符串
```

### 数组操作

```php
// 数组操作
$value = Anon_Helper::get($array, 'user.profile.name', 'default');
// 支持点号分隔的嵌套键

Anon_Helper::set($array, 'user.profile.name', 'value');
// 设置嵌套键值

$merged = Anon_Helper::merge($array1, $array2);
// 深度合并数组
```

## Utils工具集

工具类位于 `server/core/Widget/Utils/`，可直接使用：

```php
// 转义工具
Anon_Utils_Escape::html($text);
Anon_Utils_Escape::url($url);
Anon_Utils_Escape::attr($text);
Anon_Utils_Escape::js($text);

// 清理工具
Anon_Security_Sanitize::text($text);
Anon_Security_Sanitize::email($email);
Anon_Security_Sanitize::url($url);

// 验证工具
Anon_Utils_Validate::email($email);
Anon_Utils_Validate::url($url);

// 文本工具
Anon_Utils_Text::truncate($text, 10);
Anon_Utils_Text::slugify($text);
Anon_Utils_Text::timeAgo($timestamp);

// 格式化工具
Anon_Utils_Format::bytes(1048576);

// 数组工具
Anon_Utils_Array::get($array, 'key', 'default');
Anon_Utils_Array::set($array, 'key', 'value');
Anon_Utils_Array::merge($array1, $array2);

// 随机工具
Anon_Utils_Random::string(32);
```
