# 工具类

## 辅助函数

`Anon_Helper` 提供常用工具方法：

```php
// HTML 转义
$escaped = Anon_Helper::escHtml('<script>alert("xss")</script>');
$url = Anon_Helper::escUrl('https://example.com');
$attr = Anon_Helper::escAttr('value with "quotes"');
$js = Anon_Helper::escJs('alert("test")');

// 数据清理
$clean = Anon_Helper::sanitizeText('<p>HTML</p>');
$email = Anon_Helper::sanitizeEmail('user@example.com');
$url = Anon_Helper::sanitizeUrl('https://example.com');

// 验证
if (Anon_Helper::isValidEmail('user@example.com')) {
    // 有效邮箱
}
if (Anon_Helper::isValidUrl('https://example.com')) {
    // 有效 URL
}

// 文本处理
$truncated = Anon_Helper::truncate('很长的文本', 10);
$slug = Anon_Helper::slugify('Hello World!');
$timeAgo = Anon_Helper::timeAgo(time() - 3600);

// 格式化
$size = Anon_Helper::formatBytes(1048576);
$random = Anon_Helper::randomString(32);

// 数组操作
$value = Anon_Helper::get($array, 'user.profile.name', 'default');
Anon_Helper::set($array, 'user.profile.name', 'value');
$merged = Anon_Helper::merge($array1, $array2);
```

## Utils 工具集

工具类位于 `server/core/Widget/Utils/`，可直接使用：

```php
// 转义工具
Anon_Utils_Escape::html($text);
Anon_Utils_Escape::url($url);
Anon_Utils_Escape::attr($text);
Anon_Utils_Escape::js($text);

// 清理工具
Anon_Utils_Sanitize::text($text);
Anon_Utils_Sanitize::email($email);
Anon_Utils_Sanitize::url($url);

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

---

[← 返回文档首页](../README.md)

