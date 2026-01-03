<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 安全工具类
 * 提供 SQL 注入检查、XSS 自动过滤等功能
 */
class Anon_Security
{
    /**
     * 自动过滤输入数据防止 XSS
     * @param array $data 原始数据
     * @param array $options 过滤选项
     *   - 'stripHtml' => bool 是否移除 HTML 标签，默认 true
     *   - 'allowedFields' => array 允许的字段列表，为空则允许所有
     *   - 'skipFields' => array 跳过的字段列表，不进行过滤
     * @return array 过滤后的数据
     */
    public static function filterInput(array $data, array $options = []): array
    {
        $stripHtml = $options['stripHtml'] ?? true;
        $allowedFields = $options['allowedFields'] ?? [];
        $skipFields = $options['skipFields'] ?? [];
        
        $filtered = [];
        
        foreach ($data as $key => $value) {
            // 检查是否在允许列表中
            if (!empty($allowedFields) && !in_array($key, $allowedFields)) {
                continue;
            }
            
            // 检查是否在跳过列表中
            if (in_array($key, $skipFields)) {
                $filtered[$key] = $value;
                continue;
            }
            
            // 递归处理数组
            if (is_array($value)) {
                $filtered[$key] = self::filterInput($value, $options);
            } elseif (is_string($value)) {
                // 过滤字符串
                $filtered[$key] = $stripHtml 
                    ? Anon_Utils_Sanitize::text($value)
                    : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } else {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }
    
    /**
     * 检查 SQL 查询是否使用了预处理语句
     * 此方法用于开发环境检查，帮助开发者发现潜在的 SQL 注入风险
     * @param string $sql SQL 查询语句
     * @param array $params 参数数组
     * @return bool 如果使用了预处理语句返回 true，否则返回 false
     */
    public static function isUsingPreparedStatement(string $sql, array $params = []): bool
    {
        // 如果提供了参数，认为使用了预处理语句
        if (!empty($params)) {
            return true;
        }
        
        // 检查 SQL 中是否包含占位符
        if (preg_match('/[?:\$]\w+/', $sql)) {
            return true;
        }
        
        // 检查是否包含用户输入（简单检查，不保证准确）
        $hasUserInput = false;
        foreach ($_GET as $value) {
            if (is_string($value) && strpos($sql, $value) !== false) {
                $hasUserInput = true;
                break;
            }
        }
        
        if (!$hasUserInput) {
            foreach ($_POST as $value) {
                if (is_string($value) && strpos($sql, $value) !== false) {
                    $hasUserInput = true;
                    break;
                }
            }
        }
        
        // 如果包含用户输入但没有参数，可能存在风险
        if ($hasUserInput) {
            return false;
        }
        
        // 默认返回 true，静态 SQL 查询
        return true;
    }
    
    /**
     * 验证 SQL 查询安全性，开发环境使用
     * @param string $sql SQL 查询语句
     * @param array $params 参数数组
     * @param bool $throwException 是否抛出异常
     * @return bool 如果安全返回 true
     * @throws RuntimeException 如果检测到潜在风险且 $throwException 为 true
     */
    public static function validateSqlQuery(string $sql, array $params = [], bool $throwException = false): bool
    {
        // 只在开发环境或调试模式下检查
        if (!Anon_Debug::isEnabled()) {
            return true;
        }
        
        // 检查是否使用了预处理语句
        if (!self::isUsingPreparedStatement($sql, $params)) {
            $message = "潜在 SQL 注入风险：查询未使用预处理语句。SQL: " . substr($sql, 0, 100);
            
            if ($throwException) {
                throw new RuntimeException($message);
            }
            
            // 记录警告日志
            error_log("Security Warning: " . $message);
            return false;
        }
        
        return true;
    }
    
    /**
     * 转义 SQL LIKE 查询中的特殊字符
     * 注意：此方法仅用于 LIKE 查询，其他查询应使用预处理语句
     * @param string $string 原始字符串
     * @return string 转义后的字符串
     */
    public static function escapeLike(string $string): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $string);
    }
    
    /**
     * 检查字符串是否包含潜在的 SQL 注入代码
     * @param string $string 要检查的字符串
     * @return bool 如果包含潜在风险返回 true
     */
    public static function containsSqlInjection(string $string): bool
    {
        $patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i',
            '/(\bSCRIPT\b)/i',
            '/(--|\#|\/\*|\*\/)/',
            '/(\bOR\b.*=.*)/i',
            '/(\bAND\b.*=.*)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $string)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 检查字符串是否包含潜在的 XSS 代码
     * @param string $string 要检查的字符串
     * @return bool 如果包含潜在风险返回 true
     */
    public static function containsXss(string $string): bool
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<img[^>]+src[^>]*=.*javascript:/i',
            '/<link[^>]+href[^>]*=.*javascript:/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $string)) {
                return true;
            }
        }
        
        return false;
    }
}

