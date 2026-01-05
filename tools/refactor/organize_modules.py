#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Anon 框架重构工具
自动化整理 core/Modules/ 目录结构
"""

import os
import shutil
from pathlib import Path

BASE_DIR = Path(__file__).parent.parent.parent
MODULES_DIR = BASE_DIR / 'core' / 'Modules'

# 文件移动映射表，按功能模块分类
FILE_MOVES = {
    # Auth 模块
    'Auth': [
        'Token.php',
        'Csrf.php', 
        'Captcha.php',
        'RateLimit.php',
        'Capability.php'
    ],
    
    # Database 模块
    'Database': [
        'QueryBuilder.php',
        'QueryOptimizer.php',
        'Sharding.php',
        'SqlConfig.php'
    ],
    
    # Http 模块
    'Http': [
        'Router.php',
        'RequestHelper.php',
        'ResponseHelper.php',
        'Middleware.php'
    ],
    
    # Security 模块
    'Security': [
        'Security.php'
    ],
    
    # Cache 模块
    'Cache': [
        'Cache.php'
    ],
    
    # System 模块
    'System': [
        'Config.php',
        'Env.php',
        'Container.php',
        'Hook.php',
        'Plugin.php',
        'Exception.php',
        'Install.php',
        'Widget.php',
        'Console.php'
    ]
}


def move_files(dry_run=False):
    """移动文件到功能子目录"""
    print("开始重构 core/Modules/ 目录结构...\n")
    
    moved = 0
    skipped = 0
    
    for subdir, files in FILE_MOVES.items():
        target_dir = MODULES_DIR / subdir
        
        # 创建目标目录
        if not dry_run:
            target_dir.mkdir(exist_ok=True)
        
        print(f"📁 {subdir}/")
        
        for filename in files:
            source = MODULES_DIR / filename
            target = target_dir / filename
            
            # 源文件不存在则跳过
            if not source.exists():
                print(f"  ⚠️  跳过: {filename} (不存在)")
                skipped += 1
                continue
            
            # 目标文件已存在则跳过
            if target.exists():
                print(f"  ⚠️  跳过: {filename} (目标已存在)")
                skipped += 1
                continue
            
            if not dry_run:
                shutil.move(str(source), str(target))
            
            print(f"  ✓ 移动: {filename}")
            moved += 1
        
        print()
    
    print(f"完成！移动 {moved} 个文件，跳过 {skipped} 个")
    print("\n后续步骤：")
    print("1. 运行 composer dump-autoload")
    print("2. 测试功能是否正常")
    print("3. 如有问题，手动调整 require_once 路径")


if __name__ == '__main__':
    import sys
    
    dry_run = '--dry-run' in sys.argv
    
    if dry_run:
        print("⚠️  演练模式（不实际移动文件）\n")
    else:
        confirm = input("确认重构 core/Modules/ 目录？(yes/no): ")
        if confirm.lower() != 'yes':
            print("已取消")
            sys.exit(0)
    
    move_files(dry_run)

