<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Utils_Validate
{
    /**
     * 验证邮箱格式
     * @param string $email 邮箱地址
     * @return bool
     */
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * 验证 URL 格式
     * @param string $url URL 地址
     * @return bool
     */
    public static function url(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 验证密码强度
     * @param string $password 密码
     * @param array $options 自定义选项
     * @return array 错误信息数组，空数组表示密码合格
     */
    public static function passwordStrength(string $password, array $options = []): array
    {
        $errors = [];
        
        $minLength = $options['minLength'] ?? self::getPasswordConfig('minLength', 8);
        $maxLength = $options['maxLength'] ?? self::getPasswordConfig('maxLength', 128);
        $requireUppercase = $options['requireUppercase'] ?? self::getPasswordConfig('requireUppercase', true);
        $requireLowercase = $options['requireLowercase'] ?? self::getPasswordConfig('requireLowercase', true);
        $requireDigit = $options['requireDigit'] ?? self::getPasswordConfig('requireDigit', true);
        $requireSpecial = $options['requireSpecial'] ?? self::getPasswordConfig('requireSpecial', true);
        
        $length = strlen($password);
        if ($length < $minLength) {
            $errors[] = "密码长度至少 {$minLength} 个字符";
        }
        if ($length > $maxLength) {
            $errors[] = "密码长度不能超过 {$maxLength} 个字符";
        }
        
        if ($requireUppercase && !preg_match('/[A-Z]/', $password)) {
            $errors[] = '密码必须包含至少一个大写字母';
        }
        
        if ($requireLowercase && !preg_match('/[a-z]/', $password)) {
            $errors[] = '密码必须包含至少一个小写字母';
        }
        
        if ($requireDigit && !preg_match('/[0-9]/', $password)) {
            $errors[] = '密码必须包含至少一个数字';
        }
        
        if ($requireSpecial && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':\"\\|,.<>\/?`~]/', $password)) {
            $errors[] = '密码必须包含至少一个特殊字符';
        }
        
        // 常见弱密码列表
        $commonPasswords = [
            'password', '123456', '12345678', 'qwerty', 'abc123', 
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            '1234567890', 'password1', '111111', 'iloveyou', 'sunshine'
        ];
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = '密码过于简单，请使用更复杂的密码';
        }
        
        return $errors;
    }

    /**
     * 获取密码配置
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    private static function getPasswordConfig(string $key, $default)
    {
        if (class_exists('Anon_Env') && Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get("app.security.password.{$key}", $default);
        }
        return $default;
    }

    /**
     * 获取密码要求说明
     * @return array
     */
    public static function getPasswordRequirements(): array
    {
        $minLength = self::getPasswordConfig('minLength', 8);
        $requirements = ["至少 {$minLength} 个字符"];
        
        if (self::getPasswordConfig('requireUppercase', true)) {
            $requirements[] = '至少一个大写字母';
        }
        if (self::getPasswordConfig('requireLowercase', true)) {
            $requirements[] = '至少一个小写字母';
        }
        if (self::getPasswordConfig('requireDigit', true)) {
            $requirements[] = '至少一个数字';
        }
        if (self::getPasswordConfig('requireSpecial', true)) {
            $requirements[] = '至少一个特殊字符';
        }
        
        return $requirements;
    }

    /**
     * 验证用户名格式
     * @param string $username 用户名
     * @param int $minLength 最小长度
     * @param int $maxLength 最大长度
     * @return array 错误信息数组
     */
    public static function username(string $username, int $minLength = 3, int $maxLength = 20): array
    {
        $errors = [];
        $length = strlen($username);
        
        if ($length < $minLength || $length > $maxLength) {
            $errors[] = "用户名长度必须在 {$minLength}-{$maxLength} 个字符之间";
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = '用户名只能包含字母、数字和下划线';
        }
        
        if (preg_match('/^[0-9]/', $username)) {
            $errors[] = '用户名不能以数字开头';
        }
        
        return $errors;
    }
}

