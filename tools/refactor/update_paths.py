#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
更新 PHP 代码中的 require_once 路径
适配新的 Modules 子目录结构
"""

import os
import re
from pathlib import Path

BASE_DIR = Path(__file__).parent.parent.parent
CORE_DIR = BASE_DIR / 'core'
APP_DIR = BASE_DIR / 'app'

# 旧路径到新路径的映射表
PATH_MAP = {
    # Auth 模块
    "Modules/Token.php": "Modules/Auth/Token.php",
    "Modules/Csrf.php": "Modules/Auth/Csrf.php",
    "Modules/Captcha.php": "Modules/Auth/Captcha.php",
    "Modules/RateLimit.php": "Modules/Auth/RateLimit.php",
    "Modules/Capability.php": "Modules/Auth/Capability.php",
    
    # Database 模块
    "Modules/QueryBuilder.php": "Modules/Database/QueryBuilder.php",
    "Modules/QueryOptimizer.php": "Modules/Database/QueryOptimizer.php",
    "Modules/Sharding.php": "Modules/Database/Sharding.php",
    "Modules/SqlConfig.php": "Modules/Database/SqlConfig.php",
    
    # Http 模块
    "Modules/Router.php": "Modules/Http/Router.php",
    "Modules/RequestHelper.php": "Modules/Http/RequestHelper.php",
    "Modules/ResponseHelper.php": "Modules/Http/ResponseHelper.php",
    "Modules/Middleware.php": "Modules/Http/Middleware.php",
    
    # Security 模块
    "Modules/Security.php": "Modules/Security/Security.php",
    
    # Cache 模块
    "Modules/Cache.php": "Modules/Cache/Cache.php",
    
    # System 模块
    "Modules/Config.php": "Modules/System/Config.php",
    "Modules/Env.php": "Modules/System/Env.php",
    "Modules/Container.php": "Modules/System/Container.php",
    "Modules/Hook.php": "Modules/System/Hook.php",
    "Modules/Plugin.php": "Modules/System/Plugin.php",
    "Modules/Exception.php": "Modules/System/Exception.php",
    "Modules/Install.php": "Modules/System/Install.php",
    "Modules/Widget.php": "Modules/System/Widget.php",
    "Modules/Console.php": "Modules/System/Console.php",
}


def update_file(file_path, dry_run=False):
    """更新单个文件中的 require_once 路径"""
    # 只处理 PHP 文件
    if file_path.suffix != '.php':
        return False
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except:
        return False
    
    original = content
    updated = False
    
    # 遍历路径映射表进行替换
    for old_path, new_path in PATH_MAP.items():
        pattern1 = re.escape(old_path)
        if re.search(pattern1, content):
            content = re.sub(pattern1, new_path, content)
            updated = True
    
    if updated and content != original:
        if not dry_run:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
        return True
    
    return False


def update_directory(directory, dry_run=False):
    """递归更新目录中所有 PHP 文件的路径引用"""
    updated = 0
    
    # 跳过无关目录
    for root, dirs, files in os.walk(directory):
        dirs[:] = [d for d in dirs if d not in ['node_modules', '.git', 'vendor', 'cache']]
        
        for file in files:
            if file.endswith('.php'):
                file_path = Path(root) / file
                if update_file(file_path, dry_run):
                    rel_path = file_path.relative_to(BASE_DIR)
                    print(f"  ✓ {rel_path}")
                    updated += 1
    
    return updated


def main():
    import sys
    
    dry_run = '--dry-run' in sys.argv
    
    print("更新 require_once 路径...\n")
    
    if dry_run:
        print("⚠️  演练模式（不实际修改文件）\n")
    
    # 更新 core/
    print("📁 core/")
    core_updated = update_directory(CORE_DIR, dry_run)
    print(f"  更新 {core_updated} 个文件\n")
    
    # 更新 app/
    if APP_DIR.exists():
        print("📁 app/")
        app_updated = update_directory(APP_DIR, dry_run)
        print(f"  更新 {app_updated} 个文件\n")
    
    total = core_updated + (app_updated if APP_DIR.exists() else 0)
    print(f"完成！共更新 {total} 个文件")


if __name__ == '__main__':
    main()

