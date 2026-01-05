#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
更新 VitePress 文档中的类名引用
将旧类名更新为新类名，同时保留旧类名说明
"""

import os
import re
from pathlib import Path

BASE_DIR = Path(__file__).parent.parent.parent
DOCS_DIR = BASE_DIR / 'vitepress' / 'docs'
CORE_DIR = BASE_DIR / 'core'
MODULES_DIR = CORE_DIR / 'Modules'


def extract_class_name(file_path):
    """从 PHP 文件中提取类名"""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except:
        return None
    
    class_match = re.search(r'\bclass\s+(\w+)\b', content)
    if class_match:
        return class_match.group(1)
    
    interface_match = re.search(r'\binterface\s+(\w+)\b', content)
    if interface_match:
        return interface_match.group(1)
    
    return None


def generate_new_class_name(file_path, old_class_name):
    """根据文件路径生成新类名"""
    try:
        rel_path = file_path.relative_to(MODULES_DIR)
    except:
        return None
    
    parts = list(rel_path.parts)
    filename = parts[-1]
    dirs = parts[:-1]
    file_base = Path(filename).stem
    
    if file_base == 'RequestHelper':
        file_base = 'Request'
    elif file_base == 'ResponseHelper':
        file_base = 'Response'
    
    if dirs:
        module_name = dirs[0]
        new_name = f'Anon_{module_name}_{file_base}'
    else:
        if 'Widgets' in str(file_path) and 'Utils' in str(file_path):
            if 'Sanitize' in filename:
                new_name = old_class_name.replace('Anon_Utils_', 'Anon_Security_')
            else:
                new_name = old_class_name
        else:
            new_name = old_class_name
    
    return new_name


def auto_detect_class_mapping():
    """自动检测类名映射关系"""
    class_map = {}
    
    if not MODULES_DIR.exists():
        return class_map
    
    for root, dirs, files in os.walk(MODULES_DIR):
        if root == str(MODULES_DIR):
            continue
        
        for file in files:
            if not file.endswith('.php'):
                continue
            
            file_path = Path(root) / file
            old_class_name = extract_class_name(file_path)
            if not old_class_name or not old_class_name.startswith('Anon_'):
                continue
            
            new_class_name = generate_new_class_name(file_path, old_class_name)
            if not new_class_name or new_class_name == old_class_name:
                continue
            
            class_map[old_class_name] = new_class_name
    
    widgets_utils_dir = CORE_DIR / 'Widgets' / 'Utils'
    if widgets_utils_dir.exists():
        for file in widgets_utils_dir.glob('*.php'):
            old_class_name = extract_class_name(file)
            if old_class_name and old_class_name.startswith('Anon_Utils_'):
                if 'Sanitize' in file.name:
                    new_class_name = old_class_name.replace('Anon_Utils_', 'Anon_Security_')
                    class_map[old_class_name] = new_class_name
    
    return class_map


def update_markdown_file(file_path, class_map, dry_run=False):
    """更新单个 Markdown 文件中的类名"""
    if not file_path.exists() or file_path.suffix != '.md':
        return False
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except:
        return False
    
    original = content
    
    # 按类名长度降序排序，避免短类名覆盖长类名
    sorted_map = sorted(class_map.items(), key=lambda x: len(x[0]), reverse=True)
    
    # 替换代码块中的类名
    def replace_in_code_block(match):
        code_block = match.group(0)
        code = match.group(2)
        
        for old_name, new_name in sorted_map:
            # 在代码块中替换类名
            code = re.sub(
                rf'\b{re.escape(old_name)}\b',
                new_name,
                code
            )
        
        return match.group(1) + code + match.group(3)
    
    # 匹配代码块 ```language\n...\n```
    content = re.sub(
        r'(```[a-z]*\n)(.*?)(\n```)',
        replace_in_code_block,
        content,
        flags=re.DOTALL
    )
    
    # 替换行内代码中的类名
    def replace_in_inline_code(match):
        code = match.group(1)
        for old_name, new_name in sorted_map:
            if old_name in code:
                code = code.replace(old_name, new_name)
        return f'`{code}`'
    
    content = re.sub(
        r'`([^`]+)`',
        replace_in_inline_code,
        content
    )
    
    if content != original:
        if not dry_run:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
        return True
    
    return False


def update_all_docs(class_map, dry_run=False):
    """更新所有文档"""
    print("更新 VitePress 文档...\n")
    
    if not DOCS_DIR.exists():
        print("⚠️  文档目录不存在，跳过")
        return 0
    
    updated = 0
    
    for root, dirs, files in os.walk(DOCS_DIR):
        # 跳过无关目录
        dirs[:] = [d for d in dirs if d not in ['node_modules', '.git', 'dist', 'public']]
        
        for file in files:
            if file.endswith('.md'):
                file_path = Path(root) / file
                if update_markdown_file(file_path, class_map, dry_run):
                    rel_path = file_path.relative_to(BASE_DIR)
                    print(f"  ✓ {rel_path}")
                    updated += 1
    
    return updated


def add_migration_note():
    """在文档中添加迁移说明"""
    migration_note = """
## 类名变更说明

框架已重构，类名已更新为更清晰的命名。旧类名仍然可用，但建议使用新类名。

### 新类名（推荐使用）

```php
// Http 模块
Anon_Http_Request::validate([...]);
Anon_Http_Response::success($data);
Anon_Http_Router::handle();

// Auth 模块
Anon_Auth_Token::generate([...]);
Anon_Auth_Csrf::generate();
```

### 旧类名（仍然可用）

```php
// 旧代码仍然可以正常工作
Anon_RequestHelper::validate([...]);
Anon_ResponseHelper::success($data);
Anon_Token::generate([...]);
```

### 兼容机制

通过 `core/Compatibility.php` 自动创建类别名，旧代码无需修改即可继续工作。

"""
    
    # 检查是否有 API 参考文档
    api_ref_file = DOCS_DIR / 'api' / 'reference.md'
    if api_ref_file.exists():
        with open(api_ref_file, 'r', encoding='utf-8') as f:
            content = f.read()
        
        if '类名变更说明' not in content:
            content = migration_note + '\n' + content
            with open(api_ref_file, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"  ✓ 添加迁移说明到 api/reference.md")


def main():
    """主函数"""
    import sys
    
    dry_run = '--dry-run' in sys.argv
    
    print("VitePress 文档更新工具")
    print("=" * 60)
    print()
    
    # 自动检测类名映射
    print("自动检测类名映射...")
    class_map = auto_detect_class_mapping()
    print(f"检测到 {len(class_map)} 个类需要更新\n")
    
    if dry_run:
        print("⚠️  演练模式（不实际修改文件）\n")
    else:
        confirm = input("确认更新文档？(y/n): ")
        if confirm.lower() != 'y':
            print("已取消")
            return
    
    # 更新文档
    updated = update_all_docs(class_map, dry_run)
    
    # 添加迁移说明
    if not dry_run:
        add_migration_note()
    
    print(f"\n完成！更新 {updated} 个文档文件")
    
    if not dry_run:
        print("\n下一步：")
        print("1. cd vitepress")
        print("2. npm run docs:dev  # 预览文档")
        print("3. npm run docs:build  # 构建文档")


if __name__ == '__main__':
    main()

