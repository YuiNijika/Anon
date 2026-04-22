# 短代码系统

短代码系统允许在 CMS 内容和主题模板中嵌入 React 组件，类似 WordPress 的 shortcode 功能。插件和主题都可以注册和使用短代码。

## 架构

```
PHP 后端                    前端 React
┌─────────────┐            ┌──────────────────┐
│ 短代码注册   │            │ 组件注册表        │
│ Shortcode   │──HTML────▶│ mountReactComp.  │
│ API         │  标记      │ useReactComp.    │
└─────────────┘            └──────────────────┘
```

## 内置短代码

系统默认提供以下短代码：

### `[Editor]` - Markdown 编辑器

**属性：**
- `placeholder` - 占位符文本（默认："开始写作..."）
- `height` - 编辑器高度（默认："400px"）
- `preview` - 是否显示预览标签页：true/false（默认：true）

**示例：**
```
[Editor placeholder="输入内容..." height="300px"]
```

### `[Gallery]` - 图片画廊

**属性：**
- `images` - 图片 URL 列表，逗号分隔（必需）
- `columns` - 列数：1-4（默认：3）
- `gap` - 间距（默认：16）

**示例：**
```
[Gallery images="url1,url2,url3" columns="2" gap="12"]
```

### `[Alert]` - 警告框

**属性：**
- `type` - 类型：info/success/warning/error（默认：info）
- `title` - 标题（可选）
- `closable` - 是否可关闭：true/false（默认：true）

**示例：**
```
[Alert type="success" title="操作成功" closable="true"]
```

## 使用方式

### 在文章内容中使用

直接在文章内容的 Markdown 或 HTML 中写入短代码：

```markdown
这是一段普通文本。

[Editor placeholder="输入内容..." height="400px"]

[Gallery images="url1,url2,url3" columns="3"]

这是另一段文本。
```

### 在主题模板中使用

在主题的 PHP 模板文件中调用：

```php
<?php
use Anon\Modules\System\Shortcode;

// 解析内容中的短代码
$content = get_post_content();
$content = Shortcode::do_shortcode($content);
echo $content;
?>
```

## 扩展短代码

插件和主题都可以通过钩子注册自定义短代码：

### PHP 端注册

```php
<?php
use Anon\Modules\System\Hook;
use Anon\Modules\System\Shortcode;

// 监听短代码注册钩子
Hook::add_action('anon_register_shortcodes', function() {
    // 注册自定义短代码
    Shortcode::add_shortcode('Code', function($attrs) {
        return Shortcode::render_react_component('CodeEditor', [
            'language' => $attrs['language'] ?? 'javascript',
            'height' => $attrs['height'] ?? '300px',
        ]);
    });
});
```

### 前端注册组件

在 `dev_cms_admin/src/components/ReactComponents/index.ts` 中注册对应的 React 组件：

```typescript
import { registerReactComponent } from '@/hooks/useReactComponents'
import { CodeEditor } from './CodeEditor'

export function registerAllComponents() {
  registerReactComponent('MarkdownEditor', MarkdownEditor)
  registerReactComponent('ImageGallery', ImageGallery)
  registerReactComponent('AlertBox', AlertBox)
  
  // 注册自定义组件
  registerReactComponent('CodeEditor', CodeEditor)
}
```

### 创建 React 组件

创建组件文件 `dev_cms_admin/src/components/ReactComponents/CodeEditor.tsx`：

```tsx
interface CodeEditorProps {
  language?: string
  height?: string
}

export function CodeEditor({ 
  language = 'javascript', 
  height = '300px' 
}: CodeEditorProps) {
  return (
    <div className="code-editor" style={{ height }}>
      {/* 编辑器实现 */}
    </div>
  )
}
```

## API 参考

### PHP API

#### `Shortcode::add_shortcode(string $tag, callable $callback)`

注册一个短代码。

**参数：**
- `$tag` - 短代码标签名称
- `$callback` - 回调函数，接收属性数组，返回 HTML 字符串

**示例：**
```php
<?php
use Anon\Modules\System\Shortcode;

Shortcode::add_shortcode('MyTag', function($attrs) {
    return '<div>Custom content</div>';
});
```

#### `Shortcode::do_shortcode(string $content): string`

解析内容中的短代码。

**参数：**
- `$content` - 包含短代码的内容

**返回：**
- 解析后的 HTML 内容

**示例：**
```php
<?php
use Anon\Modules\System\Shortcode;

$content = Shortcode::do_shortcode($post_content);
echo $content;
```

#### `Shortcode::render_react_component(string $component_name, array $props = []): string`

生成 React 组件挂载点 HTML。

**参数：**
- `$component_name` - React 组件名称
- `$props` - 传递给组件的属性

**返回：**
- HTML 标记字符串

**示例：**
```php
<?php
use Anon\Modules\System\Shortcode;

$html = Shortcode::render_react_component('MyComponent', [
    'title' => 'Hello',
    'count' => 42
]);
```

### React API

#### `registerReactComponent(name: string, component: React.ComponentType)`

注册一个 React 组件供短代码使用。

**示例：**
```typescript
import { registerReactComponent } from '@/hooks/useReactComponents'
import { MyComponent } from './MyComponent'

registerReactComponent('MyComponent', MyComponent)
```

#### `useReactComponentMounter(dependencies?: any[])`

Hook，自动扫描并挂载页面上的 React 组件。

**示例：**
```typescript
import { useReactComponentMounter } from '@/hooks'

function MyPage({ content }) {
  const { remount } = useReactComponentMounter([content])
  
  useEffect(() => {
    remount()
  }, [content])
  
  return <div dangerouslySetInnerHTML={{ __html: content }} />
}
```

#### `mountReactComponents()`

手动触发组件挂载。

**示例：**
```typescript
import { mountReactComponents } from '@/hooks/useReactComponents'

fetchContent().then(content => {
  setContent(content)
  setTimeout(() => mountReactComponents(), 0)
})
```

## 最佳实践

### 属性验证

在 PHP 端验证和清理属性：

```php
<?php
use Anon\Modules\System\Shortcode;

Shortcode::add_shortcode('SafeComponent', function($attrs) {
    if (empty($attrs['required_field'])) {
        return '<div class="error">缺少必需属性</div>';
    }
    
    $count = max(1, min(100, intval($attrs['count'] ?? 10)));
    $title = htmlspecialchars($attrs['title'] ?? '', ENT_QUOTES, 'UTF-8');
    
    return Shortcode::render_react_component('SafeComponent', [
        'title' => $title,
        'count' => $count,
    ]);
});
```

### 错误处理

在 React 组件中添加错误边界：

```tsx
import { useState, useEffect } from 'react'

export function RobustComponent(props: any) {
  const [error, setError] = useState<string | null>(null)
  
  useEffect(() => {
    try {
      if (!props.requiredProp) {
        throw new Error('缺少必需属性')
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : '未知错误')
    }
  }, [props])
  
  if (error) {
    return <div className="error">组件错误: {error}</div>
  }
  
  return <div>正常内容</div>
}
```

## 相关文件

- 核心类：`core/Modules/System/Shortcode.php`
- React Hook：`dev_cms_admin/src/hooks/useReactComponents.tsx`
- 组件目录：`dev_cms_admin/src/components/ReactComponents/`
