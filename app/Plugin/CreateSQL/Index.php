<?php
/**
 * Plugin Name: CreateSQL
 * Plugin Description: 同步创建useSQL中的数据表
 * Version: 1.0.0
 * Author: YuiNijika
 * Plugin URI: https://github.com/YuiNijika/AnonPlugin-CreateSQL
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Plugin_CreateSQL
{
    /**
     * 插件初始化方法
     */
    public static function init()
    {
        Anon::route('/plugin/createsql/sync', function () {
            if (!Anon::isLoggedIn()) {
                Anon::error('请先登录', [], 401);
                return;
            }

            $userId = Anon::userId();
            if (!$userId) {
                Anon::error('无法获取用户ID', [], 401);
                return;
            }

            $db = Anon_Database::getInstance();
            if (!$db->isUserAdmin($userId)) {
                Anon::error('需要管理员权限才能执行此操作', [], 403);
                return;
            }

            try {
                $result = self::syncTables();
                Anon::success($result, '数据表同步完成');
            } catch (Exception $e) {
                Anon::error($e->getMessage(), [], 500);
            }
        }, [
            'requireLogin' => true,
            'method' => ['POST', 'GET'],
            'token' => false,
        ]);
    }

    /**
     * 同步创建所有表
     * @return array 同步结果
     */
    public static function syncTables(): array
    {
        $db = new Anon_Database();
        
        try {
            $sqlConfig = Anon_Database_SqlConfig::load();
        } catch (RuntimeException $e) {
            throw new RuntimeException("无法加载 SQL 配置: " . $e->getMessage());
        }
        
        $results = [];
        $prefix = defined('ANON_DB_PREFIX') ? ANON_DB_PREFIX : '';

        foreach ($sqlConfig as $tableKey => $sql) {
            try {
                $tableName = Anon_Database_SqlConfig::extractTableName($sql, $prefix);
                
                // 检查表是否存在
                if ($db->tableExists($tableName)) {
                    $results[$tableKey] = [
                        'table' => $tableName,
                        'status' => 'exists',
                        'message' => '表已存在，跳过创建'
                    ];
                    continue;
                }

                // 方法解析 SQL
                $parsed = Anon_Database_SqlConfig::parseCreateTableSql($sql, $prefix);
                
                // 使用查询构建器创建表
                $db->db($tableName)->createTable($parsed['columns'], $parsed['options']);
                
                $results[$tableKey] = [
                    'table' => $tableName,
                    'status' => 'created',
                    'message' => '表创建成功'
                ];
            } catch (Exception $e) {
                $results[$tableKey] = [
                    'table' => $tableKey,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * 插件激活时调用
     */
    public static function activate()
    {
        Anon_Debug::info('CreateSQL 插件已激活');
    }

    /**
     * 插件停用时调用
     */
    public static function deactivate()
    {
        Anon_Debug::info('CreateSQL 插件已停用');
    }
}
