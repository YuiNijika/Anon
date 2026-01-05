#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
重命名类并创建兼容别名
将类重命名为新名称，同时创建别名让旧名称仍然可用
"""

import os
import re
from pathlib import Path

BASE_DIR = Path(__file__).parent.parent.parent
CORE_DIR = BASE_DIR / 'core'
APP_DIR = BASE_DIR / 'app'
MODULES_DIR = CORE_DIR / 'Modules'


def extract_class_name(file_path):
    """从 PHP 文件中提取类名"""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except:
        return None
    
    # 匹配 class 定义
    class_match = re.search(r'\bclass\s+(\w+)\b', content)
    if class_match:
        return class_match.group(1)
    
    # 匹配 interface 定义
    interface_match = re.search(r'\binterface\s+(\w+)\b', content)
    if interface_match:
        return interface_match.group(1)
    
    return None


def generate_new_class_name(file_path, old_class_name):
    """根据文件路径生成新类名"""
    # 获取相对于 Modules 的路径
    try:
        rel_path = file_path.relative_to(MODULES_DIR)
    except:
        return None
    
    # 转换为路径列表
    parts = list(rel_path.parts)
    
    # 移除文件名，保留目录
    filename = parts[-1]
    dirs = parts[:-1]
    
    # 获取文件名不含扩展名
    file_base = Path(filename).stem
    
    # 特殊处理 RequestHelper 和 ResponseHelper 去掉 Helper 后缀
    if file_base == 'RequestHelper':
        file_base = 'Request'
    elif file_base == 'ResponseHelper':
        file_base = 'Response'
    
    # 构建新类名
    if dirs:
        # 有子目录时使用模块名作为前缀
        module_name = dirs[0]
        new_name = f'Anon_{module_name}_{file_base}'
    else:
        # 无子目录时检查是否在 Widgets/Utils 下
        if 'Widgets' in str(file_path) and 'Utils' in str(file_path):
            # Widgets/Utils 下的 Sanitize 映射到 Security 模块
            if 'Sanitize' in filename:
                new_name = old_class_name.replace('Anon_Utils_', 'Anon_Security_')
            else:
                new_name = old_class_name
        else:
            # 根目录下的文件不重命名
            new_name = old_class_name
    
    return new_name


def auto_detect_class_mapping():
    """自动检测类名映射关系"""
    class_map = {}
    
    if not MODULES_DIR.exists():
        return class_map
    
    # 扫描 Modules 子目录
    for root, dirs, files in os.walk(MODULES_DIR):
        # 跳过根目录，只处理子目录
        if root == str(MODULES_DIR):
            continue
        
        for file in files:
            if not file.endswith('.php'):
                continue
            
            file_path = Path(root) / file
            
            # 提取旧类名
            old_class_name = extract_class_name(file_path)
            if not old_class_name or not old_class_name.startswith('Anon_'):
                continue
            
            # 生成新类名
            new_class_name = generate_new_class_name(file_path, old_class_name)
            if not new_class_name or new_class_name == old_class_name:
                continue
            
            class_map[old_class_name] = new_class_name
    
    # 扫描 Widgets/Utils 目录
    widgets_utils_dir = CORE_DIR / 'Widgets' / 'Utils'
    if widgets_utils_dir.exists():
        for file in widgets_utils_dir.glob('*.php'):
            old_class_name = extract_class_name(file)
            if old_class_name and old_class_name.startswith('Anon_Utils_'):
                # Utils 下的 Sanitize 映射到 Security 模块
                if 'Sanitize' in file.name:
                    new_class_name = old_class_name.replace('Anon_Utils_', 'Anon_Security_')
                    class_map[old_class_name] = new_class_name
    
    return class_map


def replace_class_name_in_file(file_path, old_name, new_name):
    """在文件中替换类名"""
    if not file_path.exists() or file_path.suffix != '.php':
        return False
    
    # 排除兼容层文件，避免破坏 class_alias
    if file_path.name == 'Compatibility.php':
        return False
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except:
        return False
    
    original = content
    old_escaped = re.escape(old_name)
    
    # 替换 class 定义
    content = re.sub(
        rf'\bclass\s+{old_escaped}\b',
        f'class {new_name}',
        content
    )
    
    # 替换 interface 定义
    content = re.sub(
        rf'\binterface\s+{old_escaped}\b',
        f'interface {new_name}',
        content
    )
    
    # 替换 extends
    content = re.sub(
        rf'\bextends\s+{old_escaped}\b',
        f'extends {new_name}',
        content
    )
    
    # 替换 implements
    content = re.sub(
        rf'\bimplements\s+{old_escaped}\b',
        f'implements {new_name}',
        content
    )
    
    # 替换静态调用
    content = re.sub(
        rf'\b{old_escaped}::',
        f'{new_name}::',
        content
    )
    
    # 替换 new
    content = re.sub(
        rf'\bnew\s+{old_escaped}\b',
        f'new {new_name}',
        content
    )
    
    # 替换类型提示
    content = re.sub(
        rf'\b{old_escaped}\s+\$',
        f'{new_name} $',
        content
    )
    
    # 替换返回类型
    content = re.sub(
        rf':\s*{old_escaped}\b',
        f': {new_name}',
        content
    )
    
    # 替换 catch
    content = re.sub(
        rf'catch\s*\(\s*{old_escaped}\b',
        f'catch ({new_name}',
        content
    )
    
    # 替换 instanceof
    content = re.sub(
        rf'\binstanceof\s+{old_escaped}\b',
        f'instanceof {new_name}',
        content
    )
    
    if content != original:
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(content)
        return True
    
    return False


def update_directory(directory, class_map, dry_run=False):
    """递归更新目录中所有 PHP 文件的类名"""
    updated = 0
    
    for root, dirs, files in os.walk(directory):
        dirs[:] = [d for d in dirs if d not in ['node_modules', '.git', 'vendor', 'cache']]
        
        for file in files:
            if file.endswith('.php'):
                file_path = Path(root) / file
                
                # 排除兼容层文件
                if file_path.name == 'Compatibility.php':
                    continue
                
                for old_name, new_name in class_map.items():
                    if replace_class_name_in_file(file_path, old_name, new_name):
                        if not dry_run:
                            rel_path = file_path.relative_to(BASE_DIR)
                            print(f"  ✓ {rel_path}: {old_name} -> {new_name}")
                        updated += 1
    
    return updated


def create_compatibility_file(class_map):
    """创建兼容层文件"""
    compat_file = CORE_DIR / 'Compatibility.php'
    
    aliases = []
    for old_name, new_name in class_map.items():
        aliases.append(f"class_alias('{new_name}', '{old_name}');")
    
    content = f"""<?php
/**
 * 类别名兼容层
 * 为了向后兼容，将旧类名映射到新类名
 * 
 * 注意：这是临时方案，建议逐步迁移到新类名
 * 未来版本可能会移除此文件
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 自动类别名映射
{chr(10).join(aliases)}
"""
    
    with open(compat_file, 'w', encoding='utf-8') as f:
        f.write(content)
    
    return len(aliases)


def main():
    """主函数"""
    import sys
    
    dry_run = '--dry-run' in sys.argv
    
    print("Anon 框架类名重构工具")
    print("=" * 60)
    print()
    
    # 自动检测类名映射
    print("步骤 0: 自动检测类名映射...")
    print("-" * 60)
    global CLASS_RENAME_MAP
    CLASS_RENAME_MAP = auto_detect_class_mapping()
    
    if not CLASS_RENAME_MAP:
        print("⚠️  未检测到需要重命名的类")
        return
    
    print(f"检测到 {len(CLASS_RENAME_MAP)} 个类需要重命名：")
    for old_name, new_name in sorted(CLASS_RENAME_MAP.items()):
        print(f"  {old_name} -> {new_name}")
    print()
    
    if dry_run:
        print("⚠️  演练模式（不实际修改文件）\n")
    else:
        confirm = input("确认重命名类并创建兼容别名？(y/n): ")
        if confirm.lower() != 'y':
            print("已取消")
            return
    
    print("\n步骤 1: 重命名类...")
    print("-" * 60)
    
    # 更新 core 目录
    core_updated = update_directory(CORE_DIR, CLASS_RENAME_MAP, dry_run)
    print(f"core/ 目录: 更新 {core_updated} 处引用")
    
    # 更新 app 目录
    if APP_DIR.exists():
        app_updated = update_directory(APP_DIR, CLASS_RENAME_MAP, dry_run)
        print(f"app/ 目录: 更新 {app_updated} 处引用")
    
    if not dry_run:
        print("\n步骤 2: 创建兼容层...")
        print("-" * 60)
        alias_count = create_compatibility_file(CLASS_RENAME_MAP)
        print(f"✓ 创建 {alias_count} 个别名")
    
    print("\n" + "=" * 60)
    print("完成！")
    print("=" * 60)
    print("\n说明：")
    print("- 新类名（如 Anon_Http_Request）是推荐使用的")
    print("- 旧类名（如 Anon_RequestHelper）通过别名仍然可用")
    print("- 在 core/Main.php 中加载 Compatibility.php 即可启用兼容层")


if __name__ == '__main__':
    main()

