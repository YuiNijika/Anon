# 附件系统

Anon Framework 提供统一的附件处理系统 `Anon_System_Attachment`，支持文件上传、下载、删除、图片处理等功能。

## 快速开始

### 基础使用

```php
// 上传文件
$result = Anon_System_Attachment::upload('file', 'image');
if ($result['success']) {
    echo "文件 ID: " . $result['id'];
    echo "访问 URL: " . $result['url'];
}

// 获取附件信息
$attachment = Anon_System_Attachment::getAttachment(123);

// 删除附件
Anon_System_Attachment::delete(123);
```

### 访问附件

```
GET /anon/attachment?filetype=image&filename=abc.jpg
```

**参数说明：**
- `filetype` - 文件类型：image, video, audio, document, other
- `filename` - 文件名（不含扩展名）
- `imgtype` - 可选，图片处理参数

## API 参考

### 上传文件

```php
/**
 * 上传附件
 * @param string $field 表单字段名，默认 'file'
 * @param string $fileType 文件类型：image/video/audio/document/other
 * @return array ['success'=>bool, 'id'=>int, 'filename'=>string, 'url'=>string]
 */
Anon_System_Attachment::upload(string $field = 'file', string $fileType = 'other'): array
```

**示例：**

```php
// 上传图片
$result = Anon_System_Attachment::upload('avatar', 'image');

// 返回
{
    "success": true,
    "id": 123,
    "filename": "abc123def456.jpg",
    "url": "/anon/attachment/image/abc123def456"
}
```

### 获取附件信息

```php
/**
 * 获取附件信息
 * @param int $id 附件 ID
 * @return array|null
 */
Anon_System_Attachment::getAttachment(int $id): ?array
```

**示例：**

```php
$attachment = Anon_System_Attachment::getAttachment(123);
// 返回：
// [
//     'id' => 123,
//     'filename' => 'abc.jpg',
//     'original_name' => 'photo.jpg',
//     'file_size' => 102400,
//     'mime_type' => 'image/jpeg',
//     'created_at' => '2024-01-01 12:00:00'
// ]
```

### 删除附件

```php
/**
 * 删除附件（包括文件和数据库记录）
 * @param int $id 附件 ID
 * @return bool
 */
Anon_System_Attachment::delete(int $id): bool
```

**示例：**

```php
if (Anon_System_Attachment::delete(123)) {
    echo "删除成功";
}
```

### 获取附件列表

```php
/**
 * 获取附件列表
 * @param int $page 页码
 * @param int $pageSize 每页数量
 * @param string|null $mimeType MIME 类型筛选
 * @param string $sort 排序：new=新到老，old=老到新
 * @return array ['list'=>[], 'total'=>int]
 */
Anon_System_Attachment::getAttachmentList(int $page = 1, int $pageSize = 20, ?string $mimeType = null, string $sort = 'new'): array
```

**示例：**

```php
// 获取图片列表
$result = Anon_System_Attachment::getAttachmentList(1, 20, 'image');
foreach ($result['list'] as $attachment) {
    echo $attachment['filename'];
}
```

### 生成访问 URL

```php
/**
 * 获取附件 URL
 * @param int $id 附件 ID
 * @param string $fileType 文件类型
 * @return string
 */
Anon_System_Attachment::getUrl(int $id, string $fileType = 'other'): string
```

**示例：**

```php
$url = Anon_System_Attachment::getUrl(123, 'image');
// 返回：/anon/attachment/image/abc123def456
```

## 图片处理

### Discuz 风格参数

支持在 URL 中添加 `imgtype` 参数进行图片处理：

**格式转换：**
```
/anon/attachment/image/abc?imgtype=webp
```

**等比缩放：**
```
/anon/attachment/image/abc?imgtype=672x378
```

**转换并缩放：**
```
/anon/attachment/image/abc?imgtype=webp_672x378
```

### 支持的格式

- **输入格式：** jpg, jpeg, png, gif, webp, avif
- **输出格式：** jpg, jpeg, png, gif, webp, avif

### 代码示例

```php
// 模板中访问
<img src="/anon/attachment/image/abc?imgtype=webp" alt="WebP 格式">
<img src="/anon/attachment/image/abc?imgtype=300x200" alt="缩略图">
<img src="/anon/attachment/image/abc?imgtype=jpg_800x600" alt="转换并缩放">
```

## 工具方法

### 检测文件 MIME 类型

```php
$mimeType = Anon_System_Attachment::detectMimeTypeFromFile('/path/to/file.jpg');
// 返回：image/jpeg
```

### 验证扩展名匹配

```php
if (Anon_System_Attachment::isExtensionMatchMimeType('jpg', 'image/jpeg')) {
    // 匹配成功
}
```

### 生成随机文件名

```php
$built = Anon_System_Attachment::buildRandomFilename('png');
// 返回：['base' => 'abc123-1234567890', 'filename' => 'abc123-1234567890.png']
```

### 根据 MIME 获取文件类型

```php
$fileType = Anon_System_Attachment::getFileTypeByMime('image/jpeg');
// 返回：'image'
```

## 管理后台接口

### 获取附件列表

```http
GET /api/cms/admin/attachments
```

**请求参数：**
- `page` - 页码，默认 1
- `page_size` - 每页数量，默认 20，最大 100
- `mime_type` - MIME 类型筛选：image, video, audio, document
- `sort` - 排序：new（新到老）, old（老到新）

**响应示例：**

```json
{
    "code": 0,
    "data": {
        "list": [
            {
                "id": 123,
                "filename": "abc.jpg",
                "name": "原始文件名.jpg",
                "url": "/anon/attachment/image/abc",
                "mime_type": "image/jpeg",
                "size": 102400,
                "created_at": 1704067200
            }
        ],
        "total": 100,
        "page": 1,
        "page_size": 20
    },
    "message": "获取附件列表成功"
}
```

### 上传附件

```http
POST /api/cms/admin/attachments
Content-Type: multipart/form-data

file: [二进制文件]
```

**响应示例：**

```json
{
    "code": 0,
    "data": {
        "id": 123,
        "filename": "abc123.jpg",
        "original_name": "原始文件名.jpg",
        "url": "/anon/attachment/image/abc123",
        "mime_type": "image/jpeg",
        "file_size": 102400
    },
    "message": "上传成功"
}
```

### 删除附件

```http
DELETE /api/cms/admin/attachments
Content-Type: application/json

{
    "id": 123
}
```

**响应示例：**

```json
{
    "code": 0,
    "data": null,
    "message": "删除成功"
}
```

## 安全特性

### 文件类型验证

```php
// 自动检测真实 MIME 类型
$realMimeType = Anon_System_Attachment::detectMimeTypeFromFile($tmpPath);

// 验证扩展名与 MIME 类型匹配
if (!Anon_System_Attachment::isExtensionMatchMimeType($ext, $realMimeType)) {
    Anon::error("文件类型不匹配");
}
```

### 文件名安全

- 自动移除危险字符
- 只保留字母、数字、点、连字符、下划线
- 限制长度 255 字符

### 允许的文件类型

**图片：** jpg, jpeg, png, gif, webp, bmp, svg, tiff, ico  
**视频：** mp4, avi, mov, wmv, flv, mkv, webm, m4v  
**音频：** mp3, wav, ogg, m4a, aac, flac, wma  
**文档：** pdf, doc, docx, xls, xlsx, ppt, pptx, txt  

## 强缓存机制

附件系统自动添加以下响应头实现强缓存：

```http
Cache-Control: public, max-age=315360000
Expires: [10 年后日期]
ETag: "md5 值"
Last-Modified: [文件修改时间]
```

**304 协商缓存：**
```http
If-None-Match: "etag 值"
If-Modified-Since: [日期]
```

## 最佳实践

### 1. 前端上传示例

```html
<!-- HTML 表单 -->
<form id="uploadForm">
    <input type="file" name="file" accept="image/*">
    <button type="submit">上传</button>
</form>

<script>
document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    const response = await fetch('/api/cms/admin/attachments', {
        method: 'POST',
        body: formData
    });
    
    const result = await response.json();
    if (result.code === 0) {
        console.log('上传成功:', result.data.url);
    }
});
</script>
```

### 2. 批量上传

```php
$files = $_FILES['images'];
$results = [];

foreach ($files['name'] as $key => $name) {
    $_FILES['single'] = [
        'name' => $files['name'][$key],
        'type' => $files['type'][$key],
        'tmp_name' => $files['tmp_name'][$key],
        'error' => $files['error'][$key],
        'size' => $files['size'][$key],
    ];
    
    $results[] = Anon_System_Attachment::upload('single', 'image');
}
```

### 3. 图片展示优化

```html
<!-- 原图 -->
<img src="/anon/attachment/image/abc" alt="原图">

<!-- 缩略图 -->
<img src="/anon/attachment/image/abc?imgtype=200x200" alt="缩略图">

<!-- WebP 格式（更小体积） -->
<img src="/anon/attachment/image/abc?imgtype=webp" alt="WebP">

<!-- 响应式图片 -->
<picture>
    <source srcset="/anon/attachment/image/abc?imgtype=webp" type="image/webp">
    <img src="/anon/attachment/image/abc" alt="兼容模式">
</picture>
```

## 常见问题

### Q1. 上传失败如何处理？

检查文件类型是否允许，文件大小是否超限：

```php
$result = Anon_System_Attachment::upload('file', 'image');
if (!$result['success']) {
    echo "错误：" . $result['message'];
}
```

### Q2. 如何自定义允许的文件类型？

修改配置文件或数据库中的 `upload_allowed_types` 选项。

### Q3. 图片处理失败怎么办？

确保服务器已安装 GD 扩展：

```php
if (!extension_loaded('gd')) {
    echo "需要安装 GD 扩展";
}
```

### Q4. 如何迁移旧附件数据？

直接操作数据库 `attachments` 表，确保文件路径正确即可。

## 相关文档

- [API 端点](/api/endpoints) - 完整的 API 接口列表
- [安全管理](/guide/security) - 安全配置指南
- [性能优化](/guide/performance) - 缓存和性能优化
