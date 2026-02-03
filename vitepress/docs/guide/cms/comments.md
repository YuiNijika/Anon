# 评论功能

**本节说明**：CMS 评论的数据结构、主题接口、主题内调用方式及管理后台。**适用**：仅 CMS 模式。

评论支持文章下的二级嵌套回复（类似 B 站「回复 @用户名」），登录用户评论默认通过审核，游客评论进入待审核。

## 评论表与状态

- 表：`{prefix}comments`，字段含 `id`、`post_id`、`parent_id`、`uid`、`type`（`user`/`guest`）、`name`、`email`、`url`、`ip`、`user_agent`、`content`、`status`、`created_at`。
- 状态：`pending`（待审核）、`approved`（已通过）、`spam`（垃圾）、`trash`（已删除）。
- 二级嵌套：`parent_id` 指向顶级评论；回复时接口会返回 `reply_to_name` 用于前端展示「回复 @xxx」。

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
