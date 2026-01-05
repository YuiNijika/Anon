#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
创建类别名兼容层
让旧类名继续可用，同时支持新类名
"""

import re
from pathlib import Path

BASE_DIR = Path(__file__).parent.parent.parent
CORE_DIR = BASE_DIR / 'core'

# 类名映射：新类名 -> 旧类名
CLASS_ALIAS_MAP = {
    # Http 模块
    'Anon_Http_Request': 'Anon_RequestHelper',
    'Anon_Http_Response': 'Anon_ResponseHelper',
    'Anon_Http_Router': 'Anon_Router',
    'Anon_Http_Middleware': 'Anon_Middleware',
    
    # Auth 模块
    'Anon_Auth_Token': 'Anon_Token',
    'Anon_Auth_Csrf': 'Anon_Csrf',
    'Anon_Auth_Captcha': 'Anon_Captcha',
    'Anon_Auth_RateLimit': 'Anon_RateLimit',
    'Anon_Auth_Capability': 'Anon_Capability',
    
    # Database 模块
    'Anon_Database_QueryBuilder': 'Anon_QueryBuilder',
    'Anon_Database_QueryOptimizer': 'Anon_QueryOptimizer',
    'Anon_Database_Sharding': 'Anon_Sharding',
    
    # System 模块
    'Anon_System_Config': 'Anon_Config',
    'Anon_System_Env': 'Anon_Env',
    'Anon_System_Container': 'Anon_Container',
    'Anon_System_Hook': 'Anon_Hook',
    'Anon_System_Plugin': 'Anon_Plugin',
    'Anon_System_Exception': 'Anon_Exception',
    
    # Cache 模块
    'Anon_Cache_Manager': 'Anon_Cache',
    'Anon_Cache_FileCache': 'Anon_FileCache',
    'Anon_Cache_MemoryCache': 'Anon_MemoryCache',
    
    # Security 模块
    'Anon_Security_Sanitize': 'Anon_Utils_Sanitize',
}


def rename_class_in_file(file_path, old_name, new_name):
    """在文件中重命名类"""
    if not file_path.exists():
        return False
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except:
        return False
    
    original = content
    
    # 替换 class 定义
    content = re.sub(
        rf'\bclass\s+{re.escape(old_name)}\b',
        f'class {new_name}',
        content
    )
    
    # 替换 extends
    content = re.sub(
        rf'\bextends\s+{re.escape(old_name)}\b',
        f'extends {new_name}',
        content
    )
    
    # 替换 implements
    content = re.sub(
        rf'\bimplements\s+{re.escape(old_name)}\b',
        f'implements {new_name}',
        content
    )
    
    if content != original:
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(content)
        return True
    
    return False


def create_compatibility_file():
    """创建兼容层文件"""
    compat_file = CORE_DIR / 'Compatibility.php'
    
    aliases = []
    for new_name, old_name in CLASS_ALIAS_MAP.items():
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
    
    print(f"✓ 创建兼容层文件: core/Compatibility.php ({len(aliases)} 个别名)")


def main():
    """主函数"""
    import sys
    
    print("创建类别名兼容层...\n")
    
    # 创建兼容层文件
    create_compatibility_file()
    
    print("\n完成！")
    print("\n说明：")
    print("- 新类名（如 Anon_Http_Request）是推荐使用的")
    print("- 旧类名（如 Anon_RequestHelper）通过别名仍然可用")
    print("- 在 core/Main.php 中加载 Compatibility.php 即可启用兼容层")


if __name__ == '__main__':
    main()

