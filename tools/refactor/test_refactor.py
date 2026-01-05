#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Anon 框架重构后功能测试
验证重构后所有功能是否正常
"""

import subprocess
import sys
import os
from pathlib import Path

BASE_DIR = Path(__file__).parent.parent.parent
CORE_DIR = BASE_DIR / 'core'
APP_DIR = BASE_DIR / 'app'

# 需要测试的类列表，类名未改变只是文件位置改变
CLASSES_TO_TEST = [
    # System 模块
    'Anon_Env',
    'Anon_Config',
    'Anon_Container',
    'Anon_Hook',
    'Anon_Plugin',
    'Anon_Exception',
    
    # Http 模块
    'Anon_Router',
    'Anon_RequestHelper',
    'Anon_ResponseHelper',
    'Anon_Middleware',
    
    # Auth 模块
    'Anon_Token',
    'Anon_Csrf',
    'Anon_Captcha',
    'Anon_RateLimit',
    
    # Database 模块
    'Anon_QueryBuilder',
    'Anon_QueryOptimizer',
    
    # Cache 模块
    'Anon_Cache',
    'Anon_FileCache',
    'Anon_MemoryCache',
    
    # Security 模块
    'Anon_Security',
    
    # 其他
    'Anon_Database',
    'Anon_Debug',
]

# 需要检查的文件路径
FILE_PATHS_TO_CHECK = [
    'core/Modules/System/Env.php',
    'core/Modules/System/Config.php',
    'core/Modules/System/Container.php',
    'core/Modules/System/Hook.php',
    'core/Modules/System/Plugin.php',
    'core/Modules/System/Exception.php',
    'core/Modules/Http/Router.php',
    'core/Modules/Http/RequestHelper.php',
    'core/Modules/Http/ResponseHelper.php',
    'core/Modules/Http/Middleware.php',
    'core/Modules/Auth/Token.php',
    'core/Modules/Auth/Csrf.php',
    'core/Modules/Auth/Captcha.php',
    'core/Modules/Auth/RateLimit.php',
    'core/Modules/Database/QueryBuilder.php',
    'core/Modules/Database/QueryOptimizer.php',
    'core/Modules/Cache/Cache.php',
    'core/Modules/Security/Security.php',
]


def check_file_exists():
    """检查文件是否存在"""
    print("=" * 60)
    print("步骤 1: 检查文件是否存在")
    print("=" * 60 + "\n")
    
    missing = []
    exists = []
    
    for file_path in FILE_PATHS_TO_CHECK:
        full_path = BASE_DIR / file_path
        if full_path.exists():
            print(f"  ✓ {file_path}")
            exists.append(file_path)
        else:
            print(f"  ✗ {file_path} (不存在)")
            missing.append(file_path)
    
    print(f"\n结果: {len(exists)} 个文件存在, {len(missing)} 个文件缺失")
    
    if missing:
        print("\n缺失的文件:")
        for f in missing:
            print(f"  - {f}")
        return False
    
    return True


def check_php_syntax():
    """检查 PHP 语法"""
    print("\n" + "=" * 60)
    print("步骤 2: 检查 PHP 语法")
    print("=" * 60 + "\n")
    
    errors = []
    
    for file_path in FILE_PATHS_TO_CHECK:
        full_path = BASE_DIR / file_path
        if not full_path.exists():
            continue
        
        result = subprocess.run(
            ['php', '-l', str(full_path)],
            capture_output=True,
            text=True
        )
        
        if result.returncode == 0:
            print(f"  ✓ {file_path}")
        else:
            print(f"  ✗ {file_path}")
            errors.append((file_path, result.stderr))
    
    if errors:
        print("\n语法错误:")
        for file_path, error in errors:
            print(f"\n{file_path}:")
            print(error)
        return False
    
    return True


def test_class_loading():
    """测试类是否能正常加载"""
    print("\n" + "=" * 60)
    print("步骤 3: 测试类加载")
    print("=" * 60 + "\n")
    
    test_script = BASE_DIR / 'tools' / 'refactor' / 'test_classes.php'
    
    # 生成测试脚本
    test_content = """<?php
define('ANON_ALLOWED_ACCESS', true);
define('ANON_DEBUG', false);

// 加载框架
require_once __DIR__ . '/../../core/Main.php';

$classes = [
"""
    
    for cls in CLASSES_TO_TEST:
        test_content += f"    '{cls}',\n"
    
    test_content += """];
$loaded = [];
$failed = [];

foreach ($classes as $class) {
    if (class_exists($class) || interface_exists($class)) {
        $loaded[] = $class;
        echo "  ✓ $class\n";
    } else {
        $failed[] = $class;
        echo "  ✗ $class (未找到)\n";
    }
}

echo "\n结果: " . count($loaded) . " 个类加载成功, " . count($failed) . " 个类加载失败\n";

if (count($failed) > 0) {
    echo "\n加载失败的类:\n";
    foreach ($failed as $cls) {
        echo "  - $cls\n";
    }
    exit(1);
}

exit(0);
"""
    
    with open(test_script, 'w', encoding='utf-8') as f:
        f.write(test_content)
    
    # 执行测试，抑制警告因为命令行环境没有 HTTP 请求
    result = subprocess.run(
        ['php', '-d', 'display_errors=0', '-d', 'error_reporting=E_ALL & ~E_WARNING & ~E_NOTICE', str(test_script)],
        cwd=BASE_DIR,
        capture_output=True,
        text=True
    )
    
    print(result.stdout)
    
    if result.returncode != 0:
        print(result.stderr)
        return False
    
    return True


def test_basic_functionality():
    """测试基本功能"""
    print("\n" + "=" * 60)
    print("步骤 4: 测试基本功能")
    print("=" * 60 + "\n")
    
    test_script = BASE_DIR / 'tools' / 'refactor' / 'test_functionality.php'
    
    test_content = """<?php
define('ANON_ALLOWED_ACCESS', true);
define('ANON_DEBUG', false);

// 加载框架
require_once __DIR__ . '/../../core/Main.php';

$tests = [];
$passed = 0;
$failed = 0;

// 测试 1: Env 是否初始化
try {
    $env = Anon_Env::get('system.db.host', 'localhost');
    echo "  ✓ Env 初始化成功\n";
    $passed++;
} catch (Exception $e) {
    echo "  ✗ Env 初始化失败: " . $e->getMessage() . "\n";
    $failed++;
}

// 测试 2: Config 是否可用
try {
    if (class_exists('Anon_Config')) {
        echo "  ✓ Config 类存在\n";
        $passed++;
    } else {
        echo "  ✗ Config 类不存在\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "  ✗ Config 测试失败: " . $e->getMessage() . "\n";
    $failed++;
}

// 测试 3: Hook 是否可用
try {
    if (class_exists('Anon_Hook')) {
        echo "  ✓ Hook 类存在\n";
        $passed++;
    } else {
        echo "  ✗ Hook 类不存在\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "  ✗ Hook 测试失败: " . $e->getMessage() . "\n";
    $failed++;
}

// 测试 4: Container 是否可用
try {
    if (class_exists('Anon_Container')) {
        echo "  ✓ Container 类存在\n";
        $passed++;
    } else {
        echo "  ✗ Container 类不存在\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "  ✗ Container 测试失败: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n结果: {$passed} 个测试通过, {$failed} 个测试失败\n";

if ($failed > 0) {
    exit(1);
}

exit(0);
"""
    
    with open(test_script, 'w', encoding='utf-8') as f:
        f.write(test_content)
    
    result = subprocess.run(
        ['php', '-d', 'display_errors=0', '-d', 'error_reporting=E_ALL & ~E_WARNING & ~E_NOTICE', str(test_script)],
        cwd=BASE_DIR,
        capture_output=True,
        text=True
    )
    
    # 只显示测试结果，过滤掉框架启动时的警告
    output_lines = result.stdout.split('\n')
    for line in output_lines:
        if '✓' in line or '✗' in line or '结果:' in line or '加载失败的类:' in line or line.strip().startswith('-'):
            print(line)
    
    if result.returncode != 0:
        print(result.stderr)
        return False
    
    return True


def check_old_files():
    """检查是否还有旧文件在根目录"""
    print("\n" + "=" * 60)
    print("步骤 5: 检查旧文件")
    print("=" * 60 + "\n")
    
    old_files = [
        'core/Modules/Token.php',
        'core/Modules/Csrf.php',
        'core/Modules/Captcha.php',
        'core/Modules/RateLimit.php',
        'core/Modules/Router.php',
        'core/Modules/RequestHelper.php',
        'core/Modules/ResponseHelper.php',
        'core/Modules/Middleware.php',
        'core/Modules/QueryBuilder.php',
        'core/Modules/Cache.php',
        'core/Modules/Config.php',
        'core/Modules/Hook.php',
        'core/Modules/Plugin.php',
        'core/Modules/Exception.php',
    ]
    
    found_old = []
    
    for old_file in old_files:
        full_path = BASE_DIR / old_file
        if full_path.exists():
            print(f"  ⚠️  {old_file} 应该已移动到子目录")
            found_old.append(old_file)
    
    if found_old:
        print(f"\n发现 {len(found_old)} 个旧文件仍在根目录")
        print("建议删除这些文件，它们已经移动到子目录")
        return False
    
    print("  ✓ 没有发现旧文件")
    return True


def main():
    """主函数"""
    print("Anon 框架重构后功能测试")
    print("=" * 60)
    print()
    
    results = []
    
    # 步骤 1: 检查文件
    results.append(("文件检查", check_file_exists()))
    
    # 步骤 2: 检查语法
    results.append(("PHP 语法检查", check_php_syntax()))
    
    # 步骤 3: 测试类加载
    results.append(("类加载测试", test_class_loading()))
    
    # 步骤 4: 测试基本功能
    results.append(("基本功能测试", test_basic_functionality()))
    
    # 步骤 5: 检查旧文件
    results.append(("旧文件检查", check_old_files()))
    
    # 总结
    print("\n" + "=" * 60)
    print("测试总结")
    print("=" * 60 + "\n")
    
    all_passed = True
    for name, result in results:
        status = "✓ 通过" if result else "✗ 失败"
        print(f"{name}: {status}")
        if not result:
            all_passed = False
    
    print()
    
    if all_passed:
        print("🎉 所有测试通过！重构成功！")
        return 0
    else:
        print("❌ 部分测试失败，请检查上述错误")
        return 1


if __name__ == '__main__':
    sys.exit(main())

