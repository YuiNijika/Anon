<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 验证码
 */
class Anon_Auth_Captcha
{
    /**
     * 生成
     * @param int $width 宽度
     * @param int $height 高度
     * @param int $length 长度
     * @return array
     */
    public static function generate(int $width = 120, int $height = 40, int $length = 4): array
    {
        // 生成验证码字符串
        $code = self::generateCode($length);
        
        // 生成 SVG
        $svg = self::generateSvg($code, $width, $height);
        
        // 转换为 base64
        $base64 = 'data:image/svg+xml;base64,' . base64_encode($svg);
        
        return [
            'code' => $code,
            'image' => $base64
        ];
    }
    
    /**
     * 生成验证码字符串
     * @param int $length 长度
     * @return string
     */
    private static function generateCode(int $length): string
    {
        $min = (int)pow(10, $length - 1);
        $max = (int)pow(10, $length) - 1;
        return (string)random_int($min, $max);
    }
    
    /**
     * 生成SVG
     * @param string $code 验证码
     * @param int $width 宽度
     * @param int $height 高度
     * @return string
     */
    private static function generateSvg(string $code, int $width, int $height): string
    {
        $svg = '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">';
        
        $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="#ffffff"/>';
        
        // 绘制干扰线
        for ($i = 0; $i < 5; $i++) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            $strokeColor = sprintf('#%02x%02x%02x', random_int(180, 220), random_int(180, 220), random_int(180, 220));
            $svg .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . $strokeColor . '" stroke-width="1"/>';
        }
        
        // 绘制干扰点
        for ($i = 0; $i < 50; $i++) {
            $x = random_int(0, $width);
            $y = random_int(0, $height);
            $fillColor = sprintf('#%02x%02x%02x', random_int(150, 200), random_int(150, 200), random_int(150, 200));
            $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="1" fill="' . $fillColor . '"/>';
        }
        
        // 绘制验证码文字
        $fontSize = 24;
        $charWidth = $width / (strlen($code) + 1);
        $y = ($height + $fontSize) / 2;
        
        for ($i = 0; $i < strlen($code); $i++) {
            $char = $code[$i];
            $x = $charWidth * ($i + 1);
            $angle = random_int(-20, 20);
            $textColor = sprintf('#%02x%02x%02x', random_int(0, 100), random_int(0, 100), random_int(0, 100));
            
            $svg .= '<text x="' . $x . '" y="' . $y . '" font-size="' . $fontSize . '" fill="' . $textColor . '" font-weight="bold" transform="rotate(' . $angle . ' ' . $x . ' ' . $y . ')">' . htmlspecialchars($char) . '</text>';
        }
        
        $svg .= '</svg>';
        
        return $svg;
    }
    
    /**
     * 验证
     * @param string $inputCode 输入
     * @param bool $caseSensitive 区分大小写
     * @return bool
     */
    public static function verify(string $inputCode, bool $caseSensitive = false): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['captcha_code'])) {
            return false;
        }
        
        $sessionCode = $_SESSION['captcha_code'];
        
        // 验证码5分钟有效期
        $captchaTime = $_SESSION['captcha_time'] ?? 0;
        if (time() - $captchaTime > 300) {
            self::clear();
            return false;
        }
        
        if (!$caseSensitive) {
            $inputCode = strtoupper($inputCode);
            $sessionCode = strtoupper($sessionCode);
        }
        
        return hash_equals($sessionCode, $inputCode);
    }
    
    /**
     * 保存到Session
     * @param string $code 验证码
     * @return void
     */
    public static function saveToSession(string $code): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['captcha_code'] = $code;
        $_SESSION['captcha_time'] = time();
    }
    
    /**
     * 清除
     * @return void
     */
    public static function clear(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['captcha_code']);
        unset($_SESSION['captcha_time']);
    }
    
    /**
     * 检查启用
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (Anon_System_Env::isInitialized()) {
            return Anon_System_Env::get('app.captcha.enabled', false);
        }
        return defined('ANON_CAPTCHA_ENABLED') ? ANON_CAPTCHA_ENABLED : false;
    }
}
