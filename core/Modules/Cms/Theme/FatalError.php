<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Theme_FatalError
{
    /**
     * 显示严重错误页面
     * @param string $message 错误消息
     * @param string|null $file 错误文件
     * @param int|null $line 错误行号
     * @param string|null $type 错误类型
     * @return void
     */
    public static function render(string $message, ?string $file = null, ?int $line = null, ?string $type = null): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        
        $errorType = $type ?: 'Fatal Error';
        $errorFile = $file ? self::sanitizePath($file) : null;
        $errorLine = $line;
        $showDetails = defined('ANON_DEBUG') && ANON_DEBUG;
        
        echo self::renderFatalError($errorType, htmlspecialchars($message), $errorFile, $errorLine, $showDetails);
        exit;
    }
    
    /**
     * 渲染严重错误页面HTML
     * @param string $errorType 错误类型
     * @param string $message 错误消息
     * @param string|null $file 错误文件
     * @param int|null $line 错误行号
     * @param bool $showDetails 是否显示详细信息
     * @return string
     */
    private static function renderFatalError(string $errorType, string $message, ?string $file, ?int $line, bool $showDetails): string
    {
        $siteName = 'Anon CMS';
        if (class_exists('Anon_Common') && defined('Anon_Common::NAME')) {
            $siteName = Anon_Common::NAME;
        }
        $siteUrl = isset($_SERVER['HTTP_HOST']) ? 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] : '';
        
        $detailsHtml = '';
        if ($showDetails && $file) {
            $detailsHtml = '<div class="error-details">';
            $detailsHtml .= '<h3>错误详情</h3>';
            $detailsHtml .= '<p><strong>文件：</strong><code>' . htmlspecialchars($file) . '</code></p>';
            if ($line) {
                $detailsHtml .= '<p><strong>行号：</strong><code>' . $line . '</code></p>';
            }
            $detailsHtml .= '</div>';
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>严重错误 - {$siteName}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f0f0f1;
            color: #1d2327;
            line-height: 1.6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .error-container {
            background: #fff;
            border-left: 4px solid #d63638;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.13);
            max-width: 600px;
            width: 100%;
            padding: 30px;
        }
        .error-header {
            margin-bottom: 20px;
        }
        .error-icon {
            width: 60px;
            height: 60px;
            background: #d63638;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .error-icon::before {
            content: "!";
            color: #fff;
            font-size: 36px;
            font-weight: bold;
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1d2327;
            margin-bottom: 10px;
        }
        .error-type {
            font-size: 14px;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-left: 4px solid #d63638;
            padding: 15px;
            margin: 20px 0;
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            color: #1d2327;
            word-wrap: break-word;
        }
        .error-details {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dcdcde;
        }
        .error-details h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1d2327;
        }
        .error-details p {
            margin: 8px 0;
            font-size: 14px;
        }
        .error-details code {
            background: #f6f7f7;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
            color: #d63638;
        }
        .error-help {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dcdcde;
        }
        .error-help h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1d2327;
        }
        .error-help ul {
            list-style: none;
            padding-left: 0;
        }
        .error-help li {
            margin: 8px 0;
            padding-left: 20px;
            position: relative;
            font-size: 14px;
        }
        .error-help li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #646970;
        }
        .error-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dcdcde;
            text-align: center;
            font-size: 12px;
            color: #646970;
        }
        .error-footer a {
            color: #2271b1;
            text-decoration: none;
        }
        .error-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <div class="error-icon"></div>
            <h1>站点遇到严重错误</h1>
            <div class="error-type">{$errorType}</div>
        </div>
        
        <div class="error-message">
            {$message}
        </div>
        
        {$detailsHtml}
        
        <div class="error-help">
            <h3>如何解决？</h3>
            <ul>
                <li>检查服务器错误日志以获取更多信息</li>
                <li>确保所有必要的文件都已正确上传</li>
                <li>检查文件权限是否正确设置</li>
                <li>如果问题持续存在，请联系网站管理员</li>
            </ul>
        </div>
        
        <div class="error-footer">
            <p>如需帮助，请访问 <a href="https://github.com/YuiNijika/Anon" target="_blank">GitHub</a> 提交 issue。</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * 清理文件路径移除敏感信息
     * @param string $path 文件路径
     * @return string
     */
    private static function sanitizePath(string $path): string
    {
        $rootPath = defined('ANON_ROOT') ? ANON_ROOT : (defined('__DIR__') ? __DIR__ : '');
        if (!empty($rootPath) && strpos($path, $rootPath) === 0) {
            return '...' . substr($path, strlen($rootPath));
        }
        return $path;
    }
}

