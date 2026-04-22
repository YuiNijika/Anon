<?php
namespace Anon\Widgets\Utils;


use Utils;
use Env;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Validate
{
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function url(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

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

        if ($requireSpecial && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':\"\\\\|,.<>\/?`~]/', $password)) {
            $errors[] = '密码必须包含至少一个特殊字符';
        }

        $commonPasswords = [
            'password', '123456', '12345678', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            '1234567890', 'password1', '111111', 'iloveyou', 'sunshine'
        ];
        if (in_array(strtolower($password), $commonPasswords, true)) {
            $errors[] = '密码过于简单，请使用更复杂的密码';
        }

        return $errors;
    }

    private static function getPasswordConfig(string $key, $default)
    {
        if (Env::isInitialized()) {
            return Env::get("app.base.security.password.{$key}", $default);
        }
        return $default;
    }

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
