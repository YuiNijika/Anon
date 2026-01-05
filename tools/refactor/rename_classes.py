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

# 类名映射：旧类名 -> 新类名
CLASS_RENAME_MAP = {
    # Http 模块
    'Anon_RequestHelper': 'Anon_Http_Request',
    'Anon_ResponseHelper': 'Anon_Http_Response',
    'Anon_Router': 'Anon_Http_Router',
    'Anon_Middleware': 'Anon_Http_Middleware',
    'Anon_MiddlewareInterface': 'Anon_Http_MiddlewareInterface',
    
    # Auth 模块
    'Anon_Token': 'Anon_Auth_Token',
    'Anon_Csrf': 'Anon_Auth_Csrf',
    'Anon_Captcha': 'Anon_Auth_Captcha',
    'Anon_RateLimit': 'Anon_Auth_RateLimit',
    'Anon_Capability': 'Anon_Auth_Capability',
    
    # Database 模块
    'Anon_QueryBuilder': 'Anon_Database_QueryBuilder',
    'Anon_QueryOptimizer': 'Anon_Database_QueryOptimizer',
    'Anon_Sharding': 'Anon_Database_Sharding',
    
    # System 模块
    'Anon_Config': 'Anon_System_Config',
    'Anon_Env': 'Anon_System_Env',
    'Anon_Container': 'Anon_System_Container',
    'Anon_Hook': 'Anon_System_Hook',
    'Anon_Plugin': 'Anon_System_Plugin',
    'Anon_Exception': 'Anon_System_Exception',
    'Anon_UnauthorizedException': 'Anon_System_UnauthorizedException',
    'Anon_ForbiddenException': 'Anon_System_ForbiddenException',
    'Anon_NotFoundException': 'Anon_System_NotFoundException',
    'Anon_ValidationException': 'Anon_System_ValidationException',
    
    # Cache 模块
    'Anon_Cache': 'Anon_Cache_Manager',
    'Anon_FileCache': 'Anon_Cache_FileCache',
    'Anon_MemoryCache': 'Anon_Cache_MemoryCache',
    'Anon_CacheInterface': 'Anon_Cache_CacheInterface',
    
    # Security 模块
    'Anon_Utils_Sanitize': 'Anon_Security_Sanitize',
}


def replace_class_name_in_file(file_path, old_name, new_name):
    """在文件中替换类名"""
    if not file_path.exists() or file_path.suffix != '.php':
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


def update_directory(directory, dry_run=False):
    """递归更新目录中所有 PHP 文件的类名"""
    updated = 0
    
    for root, dirs, files in os.walk(directory):
        dirs[:] = [d for d in dirs if d not in ['node_modules', '.git', 'vendor', 'cache']]
        
        for file in files:
            if file.endswith('.php'):
                file_path = Path(root) / file
                
                for old_name, new_name in CLASS_RENAME_MAP.items():
                    if replace_class_name_in_file(file_path, old_name, new_name):
                        if not dry_run:
                            rel_path = file_path.relative_to(BASE_DIR)
                            print(f"  ✓ {rel_path}: {old_name} -> {new_name}")
                        updated += 1
    
    return updated


def create_compatibility_file():
    """创建兼容层文件"""
    compat_file = CORE_DIR / 'Compatibility.php'
    
    aliases = []
    for old_name, new_name in CLASS_RENAME_MAP.items():
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
    
    if dry_run:
        print("⚠️  演练模式（不实际修改文件）\n")
    else:
        confirm = input("确认重命名类并创建兼容别名？(yes/no): ")
        if confirm.lower() != 'yes':
            print("已取消")
            return
    
    print("\n步骤 1: 重命名类...")
    print("-" * 60)
    
    # 更新 core 目录
    core_updated = update_directory(CORE_DIR, dry_run)
    print(f"core/ 目录: 更新 {core_updated} 处引用")
    
    # 更新 app 目录
    if APP_DIR.exists():
        app_updated = update_directory(APP_DIR, dry_run)
        print(f"app/ 目录: 更新 {app_updated} 处引用")
    
    if not dry_run:
        print("\n步骤 2: 创建兼容层...")
        print("-" * 60)
        alias_count = create_compatibility_file()
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

