<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms
{
    private const TEMPLATE_EXTENSIONS = ['php', 'html', 'htm'];
    private static $pageStartTime = null;

    /**
     * 获取页面类型
     * @param string $templateName 模板名称
     * @return string
     */
    public static function getPageType(string $templateName): string
    {
        $templateNameLower = strtolower($templateName);
        $fileName = pathinfo(basename($templateNameLower), PATHINFO_FILENAME);
        $specialTypes = ['index', 'post', 'page', 'error'];
        
        if (in_array($fileName, $specialTypes)) {
            return $fileName;
        }
        
        $pathParts = explode('/', str_replace('\\', '/', $templateNameLower));
        foreach ($pathParts as $part) {
            $part = pathinfo($part, PATHINFO_FILENAME);
            if (in_array($part, $specialTypes)) {
                return $part;
            }
        }
        
        return 'other';
    }

    /**
     * 获取支持的模板文件扩展名
     * @return array
     */
    public static function getTemplateExtensions(): array
    {
        return self::TEMPLATE_EXTENSIONS;
    }

    /**
     * 查找目录
     * @param string $baseDir 基础目录
     * @param string $dirName 目录名称
     * @return string|null
     */
    public static function findDirectoryCaseInsensitive(string $baseDir, string $dirName): ?string
    {
        $exactPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $dirName . DIRECTORY_SEPARATOR;
        if (is_dir($exactPath)) {
            return $exactPath;
        }
        
        $dirNameLower = strtolower($dirName);
        $items = self::scanDirectory($baseDir);
        
        if ($items === null) {
            return null;
        }
        
        foreach ($items as $item) {
            $itemPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath) && strtolower($item) === $dirNameLower) {
                return $itemPath . DIRECTORY_SEPARATOR;
            }
        }
        
        return null;
    }

    /**
     * 查找文件
     * @param string $baseDir 基础目录
     * @param string $fileName 文件名
     * @param array|null $extensions 扩展名数组
     * @return string|null
     */
    public static function findFileCaseInsensitive(string $baseDir, string $fileName, ?array $extensions = null): ?string
    {
        if ($extensions === null) {
            $extensions = self::TEMPLATE_EXTENSIONS;
        }
        
        $fileNameLower = strtolower($fileName);
        
        foreach ($extensions as $ext) {
            $exactPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $fileName . '.' . $ext;
            if (file_exists($exactPath)) {
                return $exactPath;
            }
        }
        
        $items = self::scanDirectory($baseDir);
        if ($items === null) {
            return null;
        }
        
        foreach ($items as $item) {
            $itemPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $item;
            if (!is_file($itemPath)) {
                continue;
            }
            
            $itemName = pathinfo($item, PATHINFO_FILENAME);
            $itemExt = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            
            if (strtolower($itemName) === $fileNameLower && in_array($itemExt, $extensions)) {
                return $itemPath;
            }
        }
        
        return null;
    }

    /**
     * 扫描目录
     * @param string $dir 目录路径
     * @return array|null
     */
    public static function scanDirectory(string $dir): ?array
    {
        if (!is_dir($dir)) {
            return null;
        }
        
        $items = scandir($dir);
        if ($items === false) {
            return null;
        }
        
        return array_filter($items, function($item) {
            return $item !== '.' && $item !== '..';
        });
    }

    /**
     * 记录页面加载开始时间
     * @return void
     */
    public static function startPageLoad(): void
    {
        if (self::$pageStartTime === null) {
            self::$pageStartTime = microtime(true);
        }
    }

    /**
     * 获取页面加载耗时
     * @return float
     */
    public static function getPageLoadTime(): float
    {
        if (self::$pageStartTime === null) {
            return 0.0;
        }
        
        return round((microtime(true) - self::$pageStartTime) * 1000, 2);
    }

    /**
     * 输出版权
     * @return void
     */
    public static function outputCopyright(): void
    {
        $version = Anon_Common::VERSION;
        echo '<script>';
        echo 'console.log("%c Anon Framework v' . $version . ' %c https://github.com/YuiNijika/Anon", "color: #fff; background: #34495e; padding:5px 0;", "color: #fff; background: #d6293e; padding:5px 0;");';
        echo '</script>' . "\n";
    }

    /**
     * 输出页面加载耗时脚本
     * @return void
     */
    public static function outputPageLoadTimeScript(): void
    {
        $loadTime = self::getPageLoadTime();
        echo '<script>';
        echo 'console.log("页面加载耗时: ' . $loadTime . 'ms");';
        echo '</script>' . "\n";
    }
}

