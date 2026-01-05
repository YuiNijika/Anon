<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * SQL 配置工具类
 * 提供加载和管理 useSQL.php 配置的方法
 */
class Anon_Database_SqlConfig
{
    /**
     * SQL 配置文件路径
     */
    private static $configFile = null;

    /**
     * 缓存的配置数据
     */
    private static $cachedConfig = null;

    /**
     * 加载 SQL 配置
     * @param string|null $configFile 配置文件路径，默认使用 useSQL.php
     * @return array SQL 配置数组
     * @throws RuntimeException 如果配置文件不存在
     */
    public static function load(?string $configFile = null): array
    {
        if ($configFile === null) {
            $configFile = self::getDefaultConfigFile();
        }

        // 如果使用默认文件且已缓存，直接返回缓存
        if ($configFile === self::getDefaultConfigFile() && self::$cachedConfig !== null) {
            return self::$cachedConfig;
        }

        if (!file_exists($configFile)) {
            throw new RuntimeException("SQL 配置文件不存在: {$configFile}");
        }

        $config = require $configFile;

        // 缓存默认配置
        if ($configFile === self::getDefaultConfigFile()) {
            self::$cachedConfig = $config;
        }

        return $config;
    }

    /**
     * 获取默认配置文件路径
     * @return string
     */
    private static function getDefaultConfigFile(): string
    {
        if (self::$configFile === null) {
            self::$configFile = __DIR__ . '/../../../app/useSQL.php';
        }
        return self::$configFile;
    }

    /**
     * 清除配置缓存
     */
    public static function clearCache(): void
    {
        self::$cachedConfig = null;
    }

    /**
     * 获取指定表的 SQL 定义
     * @param string $tableKey 表键名
     * @param string|null $configFile 配置文件路径
     * @return string|null SQL 定义，如果不存在返回 null
     */
    public static function getTableSql(string $tableKey, ?string $configFile = null): ?string
    {
        $config = self::load($configFile);
        return $config[$tableKey] ?? null;
    }

    /**
     * 获取所有表键名
     * @param string|null $configFile 配置文件路径
     * @return array 表键名数组
     */
    public static function getTableKeys(?string $configFile = null): array
    {
        $config = self::load($configFile);
        return array_keys($config);
    }

    /**
     * 从 SQL 中提取表名
     * @param string $sql SQL 语句
     * @param string $prefix 表前缀
     * @return string 表名
     * @throws RuntimeException 如果无法提取表名
     */
    public static function extractTableName(string $sql, string $prefix = ''): string
    {
        // 匹配 CREATE TABLE IF NOT EXISTS `{prefix}table_name`
        if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?([^`\s]+)`?/i', $sql, $matches)) {
            $tableName = $matches[1];
            // 替换 {prefix} 占位符
            $tableName = str_replace('{prefix}', $prefix, $tableName);
            return $tableName;
        }
        
        throw new RuntimeException("无法从 SQL 中提取表名");
    }

    /**
     * 解析 CREATE TABLE SQL 语句
     * @param string $sql SQL 语句
     * @param string $prefix 表前缀
     * @return array 包含 columns 和 options 的数组
     * @throws RuntimeException 如果无法解析 SQL
     */
    public static function parseCreateTableSql(string $sql, string $prefix = ''): array
    {
        // 提取字段定义部分（括号内的内容，支持多行）
        if (!preg_match('/CREATE TABLE[^(]*\(([\s\S]+)\)/i', $sql, $matches)) {
            throw new RuntimeException("无法解析 SQL 字段定义");
        }

        $columnSection = $matches[1];
        
        // 提取表选项
        $options = self::extractTableOptions($sql);
        
        // 解析字段定义
        $columns = self::parseColumns($columnSection);

        return [
            'columns' => $columns,
            'options' => $options
        ];
    }

    /**
     * 提取表选项（ENGINE, CHARSET, COLLATE）
     * @param string $sql SQL 语句
     * @return array
     */
    public static function extractTableOptions(string $sql): array
    {
        $options = [
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collate' => 'utf8mb4_unicode_ci',
            'ifNotExists' => true
        ];

        // 提取 ENGINE
        if (preg_match('/ENGINE\s*=\s*(\w+)/i', $sql, $matches)) {
            $options['engine'] = $matches[1];
        }

        // 提取 CHARSET
        if (preg_match('/CHARSET\s*=\s*(\w+)/i', $sql, $matches)) {
            $options['charset'] = $matches[1];
        }

        // 提取 COLLATE
        if (preg_match('/COLLATE\s*=\s*([^\s]+)/i', $sql, $matches)) {
            $options['collate'] = $matches[1];
        }

        return $options;
    }

    /**
     * 解析字段定义
     * @param string $columnSection 字段定义部分
     * @return array 字段定义数组
     */
    private static function parseColumns(string $columnSection): array
    {
        $columns = [];
        
        // 按逗号分割字段，但要注意处理括号内的内容（如函数调用）
        $parts = self::splitColumnDefinitions($columnSection);
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            // 跳过索引定义（INDEX, UNIQUE, PRIMARY KEY 等）
            if (preg_match('/^\s*(INDEX|UNIQUE|PRIMARY\s+KEY|FOREIGN\s+KEY|KEY)\s+/i', $part)) {
                continue;
            }
            
            // 解析字段定义：`field_name` TYPE [NOT NULL] [DEFAULT value] [AUTO_INCREMENT] [PRIMARY KEY] [COMMENT 'comment']
            if (preg_match('/`([^`]+)`\s+(.+)/i', $part, $matches)) {
                $fieldName = $matches[1];
                $fieldDef = trim($matches[2]);
                
                // 将字段定义作为字符串直接使用（查询构建器会验证安全性）
                $columns[$fieldName] = $fieldDef;
            }
        }

        return $columns;
    }

    /**
     * 分割字段定义
     * @param string $columnSection 字段定义部分
     * @return array
     */
    private static function splitColumnDefinitions(string $columnSection): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($columnSection);

        for ($i = 0; $i < $length; $i++) {
            $char = $columnSection[$i];
            
            if ($char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                if (trim($current) !== '') {
                    $parts[] = $current;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}

