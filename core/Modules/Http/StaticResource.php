<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Http_StaticResource
{
    /**
     * 服务静态文件
     * @param string $filePath 文件路径
     * @param string $mimeType MIME类型
     * @param int $cacheTime 缓存时间
     * @param bool $enableCompression 是否启用压缩
     * @return void
     */
    public static function serveFile(string $filePath, string $mimeType = 'application/octet-stream', int $cacheTime = 315360000, bool $enableCompression = true)
    {
        // 文件存在性检查
        if (!file_exists($filePath) || !is_readable($filePath)) {
            http_response_code(404);
            exit('File not found');
        }

        self::sendStrongCacheHeaders($filePath, $cacheTime);

        if (self::isCacheHit($filePath)) {
            http_response_code(304);
            exit;
        }

        $fileSize = filesize($filePath);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);

        if ($enableCompression && self::shouldCompress($mimeType)) {
            self::serveCompressedContent($filePath, $mimeType);
            return;
        }

        readfile($filePath);
        exit;
    }

    /**
     * 服务图片文件并支持格式转换
     * @param string $sourcePath 源文件路径
     * @param string $targetFormat 目标格式
     * @param int $quality 质量参数
     * @return void
     */
    public static function serveImageWithConversion(string $sourcePath, string $targetFormat = '', int $quality = 80)
    {
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            http_response_code(404);
            exit('Image not found');
        }

        if (!function_exists('imagecreatefromstring')) {
            http_response_code(500);
            exit('GD extension not available');
        }

        if (empty($targetFormat)) {
            $mimeType = self::detectMimeType($sourcePath);
            self::serveFile($sourcePath, $mimeType);
            return;
        }

        $targetFormat = strtolower(trim($targetFormat));
        $supportedFormats = ['webp', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($targetFormat, $supportedFormats, true)) {
            http_response_code(400);
            exit('Unsupported image format');
        }

        $cacheKey = md5($sourcePath . $targetFormat . $quality);
        header('Cache-Control: public, max-age=315360000');
        header('ETag: "' . $cacheKey . '"');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 315360000) . ' GMT');

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch === '"' . $cacheKey . '"') {
            http_response_code(304);
            exit;
        }

        $raw = @file_get_contents($sourcePath);
        if ($raw === false) {
            http_response_code(500);
            exit('Failed to read image');
        }

        $src = @imagecreatefromstring($raw);
        if (!$src) {
            http_response_code(500);
            exit('Invalid image file');
        }

        if (in_array($targetFormat, ['png', 'webp'], true)) {
            @imagealphablending($src, true);
            @imagesavealpha($src, true);
        }

        $mimeMap = [
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];
        $mimeType = $mimeMap[$targetFormat] ?? 'application/octet-stream';
        header('Content-Type: ' . $mimeType);

        $success = false;
        switch ($targetFormat) {
            case 'webp':
                $success = function_exists('imagewebp') ? @imagewebp($src, null, $quality) : false;
                break;
            case 'png':
                $compression = min(9, max(0, intval((100 - $quality) / 10)));
                $success = @imagepng($src, null, $compression);
                break;
            case 'jpg':
            case 'jpeg':
                $success = @imagejpeg($src, null, $quality);
                break;
        }

        @imagedestroy($src);

        if (!$success) {
            http_response_code(500);
            exit('Failed to convert image');
        }

        exit;
    }

    /**
     * 发送缓存头信息
     * @param string $filePath 文件路径
     * @param int $cacheTime 缓存时间
     * @return void
     */
    private static function sendStrongCacheHeaders(string $filePath, int $cacheTime)
    {
        $mtime = filemtime($filePath);
        $etag = md5($filePath . $mtime);
        
        // 基本缓存头
        header('Cache-Control: public, max-age=' . $cacheTime);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('ETag: "' . $etag . '"');
    }

    /**
     * 检查缓存是否命中
     * @param string $filePath 文件路径
     * @return bool
     */
    private static function isCacheHit(string $filePath): bool
    {
        $mtime = filemtime($filePath);
        $etag = md5($filePath . $mtime);
        
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

        return (
            $ifNoneMatch === '"' . $etag . '"' ||
            (!empty($ifModifiedSince) && strtotime($ifModifiedSince) >= $mtime)
        );
    }

    /**
     * 判断是否应该压缩
     * @param string $mimeType MIME类型
     * @return bool
     */
    private static function shouldCompress(string $mimeType): bool
    {
        $compressibleTypes = [
            'text/html',
            'text/css', 
            'text/javascript',
            'application/javascript',
            'application/json',
            'text/xml',
            'application/xml'
        ];
        
        return in_array($mimeType, $compressibleTypes);
    }

    /**
     * 服务压缩内容
     * @param string $filePath 文件路径
     * @param string $mimeType MIME类型
     * @return void
     */
    private static function serveCompressedContent(string $filePath, string $mimeType)
    {
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        if (strpos($acceptEncoding, 'gzip') !== false && extension_loaded('zlib')) {
            $fileContent = file_get_contents($filePath);
            if ($fileContent !== false) {
                $compressed = gzencode($fileContent);
                header('Content-Encoding: gzip');
                header('Content-Length: ' . strlen($compressed));
                echo $compressed;
                exit;
            }
        }

        // 如果不能压缩，回退到普通输出
        readfile($filePath);
        exit;
    }

    /**
     * 检测MIME类型
     * @param string $filePath 文件路径
     * @return string
     */
    private static function detectMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'html' => 'text/html',
            'txt' => 'text/plain',
            'xml' => 'text/xml',
            'pdf' => 'application/pdf',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * 注册静态资源路由
     * @param string $route 路由路径
     * @param string|callable $filePath 文件路径或回调函数
     * @param string|callable $mimeType MIME类型或回调函数
     * @param int $cacheTime 缓存时间
     * @param array $meta 路由元数据
     * @return void
     */
    public static function registerRoute(string $route, $filePath, $mimeType = 'application/octet-stream', int $cacheTime = 315360000, array $meta = [])
    {
        $defaultMeta = [
            'header' => false,
            'requireLogin' => false,
            'requireAdmin' => false,
            'method' => 'GET',
            'token' => false,
        ];
        $meta = array_merge($defaultMeta, $meta);

        try {
            Anon_System_Config::addRoute($route, function () use ($filePath, $mimeType, $cacheTime) {
                $actualFilePath = is_callable($filePath) ? $filePath() : $filePath;
                $actualMimeType = is_callable($mimeType) ? $mimeType() : $mimeType;
                
                if (!$actualFilePath || !file_exists($actualFilePath) || !is_readable($actualFilePath)) {
                    http_response_code(404);
                    exit;
                }

                self::serveFile($actualFilePath, $actualMimeType, $cacheTime);
            }, $meta);
            
            // 记录路由注册成功
            Anon_Debug::info('Static resource route registered', [
                'route' => $route,
                'file_path' => is_string($filePath) ? $filePath : 'callable',
                'mime_type' => is_string($mimeType) ? $mimeType : 'callable'
            ]);
        } catch (Exception $e) {
            Anon_Debug::error('Failed to register static resource route', [
                'route' => $route,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 创建主题静态资源路由
     * @param string $themeName 主题名称
     * @param string $resourcePath 资源相对路径
     * @param string $basePath 基础路径
     * @return void
     */
    public static function registerThemeRoute(string $themeName, string $resourcePath = '', string $basePath = '')
    {
        $themeDir = Anon_Main::APP_DIR . 'Theme/' . $themeName . '/';
        if (!empty($basePath)) {
            $themeDir .= trim($basePath, '/') . '/';
        }

        $routePattern = '/theme/' . $themeName;
        if (!empty($resourcePath)) {
            $routePattern .= '/' . trim($resourcePath, '/');
        }
        $routePattern .= '/{file}';

        self::registerRoute(
            $routePattern,
            function () use ($themeDir) {
                $file = $_GET['file'] ?? '';
                if (empty($file)) return null;

                $file = preg_replace('/[^a-zA-Z0-9._-]/', '', $file);
                if (empty($file)) return null;

                $filePath = $themeDir . $file;
                return file_exists($filePath) ? $filePath : null;
            },
            function () use ($themeDir) {
                $file = $_GET['file'] ?? '';
                if (empty($file)) return 'application/octet-stream';

                $filePath = $themeDir . preg_replace('/[^a-zA-Z0-9._-]/', '', $file);
                return self::detectMimeType($filePath);
            },
            315360000,
            [
                'header' => false,
                'requireLogin' => false,
                'requireAdmin' => false,
                'method' => 'GET',
                'token' => false,
            ]
        );
    }
}