#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Anon 框架重构
"""

import subprocess
import sys
from pathlib import Path

TOOLS_DIR = Path(__file__).parent


def run(script_name, dry_run=False):
    """执行指定的 Python 脚本"""
    script = TOOLS_DIR / script_name
    cmd = [sys.executable, str(script)]
    
    # 演练模式添加参数
    if dry_run:
        cmd.append('--dry-run')
    
    print(f"\n{'='*60}")
    print(f"执行: {script_name}")
    print(f"{'='*60}\n")
    
    result = subprocess.run(cmd)
    return result.returncode == 0


def main():
    dry_run = '--dry-run' in sys.argv
    
    print("Anon 框架重构工具\n")
    
    if dry_run:
        print("⚠️  演练模式\n")
    else:
        confirm = input("确认开始重构？(y/n): ")
        if confirm.lower() != 'y':
            print("已取消")
            return
    
    # 整理 Modules 目录结构
    if not run('organize_modules.py', dry_run):
        print("\n❌ 目录整理失败")
        return
    
    # 更新 require_once 路径引用
    if not run('update_paths.py', dry_run):
        print("\n❌ 路径更新失败")
        return
    
    # 重命名类并创建兼容别名
    if not run('rename_classes.py', dry_run):
        print("\n❌ 类名重命名失败")
        return
    
    # 更新 VitePress 文档
    if not run('update_docs.py', dry_run):
        print("\n⚠️  文档更新失败，但代码重构已完成")
    
    print(f"\n{'='*60}")
    print("✅ 重构完成！")
    print(f"{'='*60}\n")
    
    if not dry_run:
        print("后续步骤：")
        print("1. composer dump-autoload")
        print("2. 测试功能")
        print("3. cd vitepress && npm run docs:dev  # 预览文档")


if __name__ == '__main__':
    main()

