<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 安全模块
 */
class Anon_Security_Security
{
    /**
     * 过滤输入
     * @param array $data 原始数据
     * @param array $options 选项
     * @return array
     */
    public static function filterInput(array $data, array $options = []): array
    {
        $stripHtml = $options['stripHtml'] ?? true;
        $allowedFields = $options['allowedFields'] ?? [];
        $skipFields = $options['skipFields'] ?? [];
        
        $filtered = [];
        
        foreach ($data as $key => $value) {
            if (!empty($allowedFields) && !in_array($key, $allowedFields)) {
                continue;
            }
            
            if (in_array($key, $skipFields)) {
                $filtered[$key] = $value;
                continue;
            }
            
            if (is_array($value)) {
                $filtered[$key] = self::filterInput($value, $options);
            } elseif (is_string($value)) {
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
     * 检查预处理
     * @param string $sql SQL
     * @param array $params 参数
     * @return bool
     */
    public static function isUsingPreparedStatement(string $sql, array $params = []): bool
    {
        if (!empty($params)) {
            return true;
        }
        
        if (preg_match('/[?:\$]\w+/', $sql)) {
            return true;
        }
        
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
        
        if ($hasUserInput) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 验证SQL安全
     * @param string $sql SQL
     * @param array $params 参数
     * @param bool $throwException 抛出异常
     * @return bool
     */
    public static function validateSqlQuery(string $sql, array $params = [], bool $throwException = false): bool
    {
        if (!Anon_Debug::isEnabled()) {
            return true;
        }
        
        if (!self::isUsingPreparedStatement($sql, $params)) {
            $message = "潜在 SQL 注入风险：查询未使用预处理语句。SQL: " . substr($sql, 0, 100);
            
            if ($throwException) {
                throw new RuntimeException($message);
            }
            
            Anon_Debug::warn("Security Warning", ['message' => $message]);
            return false;
        }
        
        return true;
    }
    
    /**
     * 转义LIKE
     * @param string $string 字符串
     * @return string
     */
    public static function escapeLike(string $string): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $string);
    }
    
    /**
     * 检查SQL注入
     * @param string $string 字符串
     * @return bool
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
     * 检查XSS
     * @param string $string 字符串
     * @return bool
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

