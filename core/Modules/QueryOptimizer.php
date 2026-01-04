<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 查询优化器
 * 提供关联查询优化、N+1 查询检测等功能
 */
class Anon_QueryOptimizer
{
    /**
     * @var array 已加载的关联数据缓存
     */
    private static $eagerLoaded = [];

    /**
     * 预加载关联数据，避免 N+1 查询
     * @param array $items 主数据数组
     * @param string $foreignKey 外键字段名
     * @param string $table 关联表名
     * @param string $localKey 本地键字段名，默认为 id
     * @param callable|null $callback 自定义查询回调
     * @return array 包含关联数据的完整数组
     */
    public static function eagerLoad(array $items, string $foreignKey, string $table, string $localKey = 'id', ?callable $callback = null): array
    {
        if (empty($items)) {
            return $items;
        }

        // 提取所有需要查询的键值
        $keys = [];
        foreach ($items as $item) {
            if (isset($item[$localKey])) {
                $keys[] = $item[$localKey];
            }
        }

        if (empty($keys)) {
            return $items;
        }

        // 去重
        $keys = array_unique($keys);
        $cacheKey = "eager:{$table}:" . hash('sha256', implode(',', $keys));

        // 检查缓存
        if (isset(self::$eagerLoaded[$cacheKey])) {
            $relatedData = self::$eagerLoaded[$cacheKey];
        } else {
            // 批量查询关联数据
            $db = new Anon_Database();
            $query = $db->db($table)->whereIn($foreignKey, $keys);

            // 如果有自定义回调，应用它
            if ($callback !== null) {
                $callback($query);
            }

            $relatedData = $query->get();

            // 按外键分组
            $grouped = [];
            foreach ($relatedData as $row) {
                $key = $row[$foreignKey] ?? null;
                if ($key !== null) {
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [];
                    }
                    $grouped[$key][] = $row;
                }
            }

            // 缓存结果
            self::$eagerLoaded[$cacheKey] = $grouped;
            $relatedData = $grouped;
        }

        // 将关联数据附加到主数据
        foreach ($items as &$item) {
            $key = $item[$localKey] ?? null;
            if ($key !== null && isset($relatedData[$key])) {
                $relations = $relatedData[$key];
                // 如果只有一条，直接赋值；如果有多条，赋值数组
                $item[$table] = count($relations) === 1 ? $relations[0] : $relations;
            } else {
                $item[$table] = null;
            }
        }

        return $items;
    }

    /**
     * 预加载一对一关联
     * @param array $items 主数据数组
     * @param string $foreignKey 外键字段名
     * @param string $table 关联表名
     * @param string $localKey 本地键字段名
     * @return array
     */
    public static function eagerLoadOne(array $items, string $foreignKey, string $table, string $localKey = 'id'): array
    {
        if (empty($items)) {
            return $items;
        }

        $keys = [];
        foreach ($items as $item) {
            if (isset($item[$localKey])) {
                $keys[] = $item[$localKey];
            }
        }

        if (empty($keys)) {
            return $items;
        }

        $keys = array_unique($keys);
        $cacheKey = "eager_one:{$table}:" . hash('sha256', implode(',', $keys));

        if (isset(self::$eagerLoaded[$cacheKey])) {
            $relatedData = self::$eagerLoaded[$cacheKey];
        } else {
            $db = new Anon_Database();
            $relatedData = $db->db($table)->whereIn($foreignKey, $keys)->get();

            $grouped = [];
            foreach ($relatedData as $row) {
                $key = $row[$foreignKey] ?? null;
                if ($key !== null) {
                    $grouped[$key] = $row;
                }
            }

            self::$eagerLoaded[$cacheKey] = $grouped;
            $relatedData = $grouped;
        }

        foreach ($items as &$item) {
            $key = $item[$localKey] ?? null;
            $item[$table] = $relatedData[$key] ?? null;
        }

        return $items;
    }

    /**
     * 清空预加载缓存
     */
    public static function clearEagerCache(): void
    {
        self::$eagerLoaded = [];
    }
}

