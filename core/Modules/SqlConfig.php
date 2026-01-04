<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * SQL 配置工具类
 * 提供加载和管理 useSQL.php 配置的方法
 */
class Anon_SqlConfig
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
            self::$configFile = __DIR__ . '/../../app/useSQL.php';
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
}

