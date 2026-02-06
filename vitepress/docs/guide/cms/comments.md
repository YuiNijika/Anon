# 评论功能

本节说明CMS评论的数据结构、主题接口、主题内调用方式及管理后台，仅适用于CMS模式。

评论支持文章下的二级嵌套回复，类似B站回复用户名功能，登录用户评论默认通过审核，游客评论进入待审核状态。

## 评论表与状态

评论表 `{prefix}comments` 包含字段：`id`、`post_id`、`parent_id`、`uid`、`type`、`name`、`email`、`url`、`ip`、`user_agent`、`content`、`status`、`created_at`。

评论状态包括待审核、已通过、垃圾和已删除。支持二级嵌套回复，`parent_id` 指向顶级评论，回复时接口返回 `reply_to_name` 用于前端展示回复用户名。

## 主题评论接口（前端/主题调用）

统一入口：`/anon/cms/comments`，无需登录。

### GET：获取评论列表

- **请求**：`GET /anon/cms/comments?post_id={文章ID}`
- **响应**：`data.comments` 为**树形结构**的已通过评论；已登录时附带 `data.currentUser`（`displayName`、`name`、`avatar`，不含 email）。

**树形结构说明**：`data.comments` 为根评论数组，每条根评论带有 `children` 数组（其回复列表），便于前端直接渲染层级。

单条评论结构（供主题/Vue 使用）：

| 字段 | 说明 |
|------|------|
| id | 评论 ID |
| post_id | 文章 ID |
| parent_id | 父评论 ID，顶级为 null |
| children | 子回复数组（仅根评论有此字段） |
| reply_to_name | 回复时被回复人名称（展示 @ 用） |
| type | user / guest |
| name | 显示名称 |
| avatar | 头像 URL（用户表或 Gravatar） |
| url | 评论者网址（可选） |
| content | 内容 |
| created_at | Unix 时间戳（秒） |

主题接口**不返回** email，避免泄露。

### POST：提交评论

- **请求**：`POST /anon/cms/comments`，`Content-Type: application/x-www-form-urlencoded`，建议带 `X-Requested-With: XMLHttpRequest` 以收到 JSON 响应。
- **Body**：`post_id`、`content` 必填；未登录时需 `name`、`email`，可选 `url`；回复时传 `parent_id`（父评论 ID，仅支持回复顶级评论）。
- 登录用户只需传 `post_id`、`content`（及可选 `url`、`parent_id`），name/email 由后端从当前用户补齐；登录用户评论默认 `approved`，游客默认 `pending`。
- **响应文案**：登录用户提交成功返回「评论发表成功」并立即展示；游客返回「评论已提交，待审核通过后会显示。」。

#### 验证码保护（防刷机制）

**重要**：为防止广告机和恶意刷评论，系统为**游客评论**提供了验证码保护机制。

**启用验证码**：

在 `server/app/useApp.php` 中配置：

```php
'captcha' => [
    'enabled' => true,  // 启用验证码
],
```

**验证码行为**：

- **仅对游客生效**：登录用户提交评论时**不需要**验证码，只有未登录的游客评论才需要验证码
- **自动验证**：当验证码功能启用时，游客提交评论必须提供正确的验证码，否则提交失败
- **验证码获取**：前端可通过 `GET /anon/auth/captcha` 获取验证码图片（Base64 格式）
- **验证码有效期**：验证码有效期为 5 分钟，过期后需要重新获取
- **一次性使用**：验证码验证成功后会自动清除，防止重复使用

**前端集成示例**：

```javascript
// 获取验证码
async function loadCaptcha() {
    const response = await fetch('/anon/auth/captcha');
    const data = await response.json();
    if (data.success) {
        // data.data.image 为 Base64 图片
        // data.data.code 为验证码字符串（仅用于调试，生产环境不应返回）
        return data.data.image;
    }
}

// 提交评论时包含验证码
const formData = new FormData();
formData.append('post_id', postId);
formData.append('content', content);
formData.append('name', name);
formData.append('email', email);
formData.append('captcha', captchaCode); // 验证码字段

const response = await fetch('/anon/cms/comments', {
    method: 'POST',
    body: formData,
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
});
```

**错误响应**：

- `comment=captcha_required`：未提供验证码
- `comment=captcha_error`：验证码错误
- 错误消息：`"请输入验证码"` 或 `"验证码错误，请重新输入"`

**安全特性**：

1. **防重复使用**：验证码验证成功后立即清除，无法重复使用
2. **时效性保护**：验证码 5 分钟后自动过期
3. **区分大小写**：默认不区分大小写（可通过参数配置）
4. **Session 存储**：验证码存储在 Session 中，确保安全性

## 主题内 $this 调用

在主题模板/组件中可直接使用以下方法（由 `Anon_Cms_Theme_View` 提供）：

| 方法 | 说明 |
|------|------|
| `$this->getCommentsByPostId($postId)` | 获取某文章已通过评论列表（与 GET 接口一致，供服务端渲染时用） |
| `$this->getPostComments($postId)` | 同上，别名 |
| `$this->getCommentDisplayName()` | 当前登录用户显示名，未登录或取不到时为空 |
| `$this->isLoggedIn()` | 是否已登录 |

默认主题评论区组件（`app/components/comments.php`）用法示例：

```php
$post = $post ?? $this->post();
if (!$post || $post->type() !== 'post' || $post->get('comment_status', 'open') !== 'open') {
    return;
}
$postId = (int) $post->id();
$commentLoggedIn = $this->isLoggedIn();
$commentDisplayName = $this->getCommentDisplayName() ?: '登录用户';
```

评论列表通常由前端通过 GET `/anon/cms/comments?post_id=...` 拉取并渲染；若需服务端直接输出列表，可 `$this->getCommentsByPostId($postId)` 得到相同**树形**结构。

## 评论区脚本与 PJAX

- 评论 Vue 脚本仅在**文章页（post）或页面（page）**输出，其他页面不注入，避免多余请求。
- 使用 PJAX 进入内页时，主题需在内容替换后重新初始化评论区：调用全局重载方法 `window.__anonInitComments()`（若存在）。默认主题在 `initPage()` 中已调用，进入文章页后评论列表会正常加载。

## 管理后台评论管理

- **菜单位置**：管理后台侧栏「管理」→「评论」。
- **端点**：见 [API 端点 - 评论管理](/api/endpoints#评论管理接口)。
- **能力**：列表（分页）、**高级筛选**（状态、类型 登录/游客、根评论/仅回复、内容关键词、日期范围）、状态改为通过/待审核/垃圾/回收站、编辑评论内容、永久删除；列表展示 IP、**解析后的 UA**（浏览器·系统）、回复关系（回复 @xxx）、子评论行高亮。

## 头像与 Gravatar

- 登录用户：头像来自用户表 `avatar`，为空时由用户表逻辑按 email 生成 Gravatar（配置 `app.avatar`，默认 cravatar.cn）。
- 游客：无 `avatar` 时按评论中的 email 调用用户库的 `getAvatarByEmail` 生成 Gravatar，与用户表策略一致。

## 安全防护措施

### 1. 验证码保护

- **作用**：防止广告机和恶意刷评论
- **范围**：仅对游客评论生效，登录用户无需验证码
- **配置**：通过 `app.captcha.enabled` 控制
- **详情**：见上方「验证码保护（防刷机制）」章节

### 2. 评论审核机制

- **登录用户**：评论默认状态为 `approved`（已通过），立即显示
- **游客评论**：评论默认状态为 `pending`（待审核），需管理员审核后显示
- **审核流程**：管理员可在后台「管理」→「评论」中审核、通过、标记垃圾或删除评论

### 3. IP 和 User-Agent 记录

- 系统自动记录评论者的 IP 地址和 User-Agent
- 便于识别异常评论和追踪恶意行为
- 管理员可在后台查看评论的 IP 和解析后的 UA 信息

### 4. 评论状态管理

- `pending`：待审核（游客评论默认状态）
- `approved`：已通过（登录用户默认状态，或管理员审核后）
- `spam`：垃圾评论（管理员标记）
- `trash`：已删除（软删除，可恢复）

### 5. 文章评论开关

- 每篇文章可单独控制是否允许评论（`comment_status` 字段）
- 值为 `open` 时允许评论，其他值（如 `closed`）时禁止评论
- 主题可通过 `$post->get('comment_status', 'open')` 检查评论状态

## 访问日志与评论统计

**注意**：评论功能与访问日志（`AccessLog`）是**独立的系统**：

- **评论阅读量**：文章阅读量（`posts.views`）独立于访问日志，不受 `access_log_enabled` 开关影响
- **访问日志**：`access_log_enabled` 开关仅控制访问日志记录，不影响评论功能
- **评论统计**：评论数量统计基于 `comments` 表，与访问日志无关

如需查看访问日志相关配置，请参考 [管理后台 - 访问日志](../admin.md#访问日志)。
