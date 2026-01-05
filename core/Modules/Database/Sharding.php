<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 分库分表适配层
 * 支持按主键、时间等规则分片，兼容 ShardingSphere-PHP/PHP-Sharding 扩展
 */
class Anon_Database_Sharding
{
    /**
     * @var array 分片配置
     */
    private static $config = [];

    /**
     * @var bool 是否已初始化
     */
    private static $initialized = false;

    /**
     * 初始化分片配置
     * @param array $config 分片配置
     */
    public static function init(array $config): void
    {
        self::$config = $config;
        self::$initialized = true;
    }

    /**
     * 根据分片键获取表名
     * @param string $baseTable 基础表名
     * @param mixed $shardKey 分片键值
     * @param string $strategy 分片策略 (id|time|hash)
     * @return string 实际表名
     */
    public static function getTableName(string $baseTable, $shardKey, string $strategy = 'id'): string
    {
        if (!self::$initialized || empty(self::$config)) {
            return $baseTable;
        }

        // 检查是否有该表的分片配置
        if (!isset(self::$config[$baseTable])) {
            return $baseTable;
        }

        $tableConfig = self::$config[$baseTable];
        $shardCount = $tableConfig['shard_count'] ?? 1;
        $shardStrategy = $tableConfig['strategy'] ?? $strategy;

        $shardIndex = self::calculateShardIndex($shardKey, $shardCount, $shardStrategy);

        return $baseTable . '_' . $shardIndex;
    }

    /**
     * 计算分片索引
     * @param mixed $shardKey 分片键值
     * @param int $shardCount 分片数量
     * @param string $strategy 分片策略
     * @return int 分片索引
     */
    private static function calculateShardIndex($shardKey, int $shardCount, string $strategy): int
    {
        switch ($strategy) {
            case 'id':
                // 按 ID 取模
                return (int)$shardKey % $shardCount;

            case 'time':
                // 按时间分片（按月或按年）
                if (is_numeric($shardKey)) {
                    $timestamp = $shardKey;
                } else {
                    $timestamp = strtotime($shardKey);
                }
                $month = (int)date('m', $timestamp);
                return ($month - 1) % $shardCount;

            case 'hash':
                // 按哈希值分片
                return abs(crc32((string)$shardKey)) % $shardCount;

            default:
                return 0;
        }
    }

    /**
     * 获取所有分片表名
     * @param string $baseTable 基础表名
     * @return array 所有分片表名数组
     */
    public static function getAllShardTables(string $baseTable): array
    {
        if (!self::$initialized || empty(self::$config)) {
            return [$baseTable];
        }

        if (!isset(self::$config[$baseTable])) {
            return [$baseTable];
        }

        $shardCount = self::$config[$baseTable]['shard_count'] ?? 1;
        $tables = [];

        for ($i = 0; $i < $shardCount; $i++) {
            $tables[] = $baseTable . '_' . $i;
        }

        return $tables;
    }

    /**
     * 检查是否启用分片
     * @param string $table 表名
     * @return bool
     */
    public static function isSharded(string $table): bool
    {
        return self::$initialized && isset(self::$config[$table]);
    }
}

