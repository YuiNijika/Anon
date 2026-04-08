<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 在线商店
 */
class Anon_Cms_Admin_Store
{
    /**
     * 检查本地版本
     */
    public static function checkVersion(): void
    {
        $name = $_GET['name'] ?? '';
        $type = $_GET['type'] ?? 'theme';

        if (empty($name)) {
            Anon_Http_Response::error('项目名称不能为空', 400);
            return;
        }

        try {
            // 确定目标目录
            $targetDir = $type === 'plugin' ? Anon_Main::APP_DIR . 'Plugin/' : Anon_Main::APP_DIR . 'Theme/';
            $itemDir = $targetDir . $name . '/';
            
            $version = null;
            
            if (is_dir($itemDir)) {
                // 尝试从 package.json 读取版本
                $packagePath = $itemDir . 'package.json';
                if (file_exists($packagePath)) {
                    $packageContent = file_get_contents($packagePath);
                    $package = json_decode($packageContent, true);
                    if (is_array($package) && isset($package['version'])) {
                        $version = $package['version'];
                    }
                } else if ($type === 'theme') {
                    // 主题可以从 index.php 读取版本
                    $indexPath = $itemDir . 'index.php';
                    if (file_exists($indexPath)) {
                        $indexContent = file_get_contents($indexPath);
                        if (preg_match('/Version:\s*(.+)/i', $indexContent, $matches)) {
                            $version = trim($matches[1]);
                        }
                    }
                }
            }

            Anon_Http_Response::success([
                'name' => $name,
                'type' => $type,
                'version' => $version,
                'installed' => $version !== null
            ], '获取版本信息成功');
        } catch (Exception $e) {
            Anon_Http_Response::error('检查版本失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 下载项目
     */
    public static function download(): void
    {
        $data = Anon_Http_Request::getInput();
        $itemName = $data['name'] ?? '';
        $type = $data['type'] ?? 'theme';
        $downloadUrl = $data['url'] ?? '';

        if (empty($itemName) || empty($downloadUrl)) {
            Anon_Http_Response::error('项目名称和下载地址不能为空', 400);
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $itemName)) {
            Anon_Http_Response::error('无效的项目名称', 400);
            return;
        }

        try {
            // 应用 GitHub 镜像
            $downloadUrl = self::applyGithubMirror($downloadUrl);
            
            $tmpFile = tempnam(sys_get_temp_dir(), 'store_');
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Anon-CMS-Store',
                    'timeout' => 60,
                    'follow_location' => true,
                ]
            ]);

            $zipContent = @file_get_contents($downloadUrl, false, $context);
            
            if ($zipContent === false) {
                @unlink($tmpFile);
                Anon_Http_Response::error('下载项目失败', 500);
                return;
            }

            file_put_contents($tmpFile, $zipContent);

            $zip = new ZipArchive();
            if ($zip->open($tmpFile) !== true) {
                @unlink($tmpFile);
                Anon_Http_Response::error('解压项目失败', 500);
                return;
            }

            // 确定目标目录
            $targetDir = $type === 'plugin' ? Anon_Main::APP_DIR . 'Plugin/' : Anon_Main::APP_DIR . 'Theme/';
            $itemDir = $targetDir . $itemName . '/';

            // 如果已存在则删除
            if (is_dir($itemDir)) {
                self::deleteDirectory($itemDir);
            }

            mkdir($itemDir, 0755, true);

            // 解压到临时目录
            $extractDir = sys_get_temp_dir() . '/store_extract_' . uniqid();
            mkdir($extractDir, 0755, true);
            
            $zip->extractTo($extractDir);
            $zip->close();
            @unlink($tmpFile);

            // 查找实际的项目目录
            $files = scandir($extractDir);
            $rootDir = null;
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_dir($extractDir . '/' . $file)) {
                    $rootDir = $extractDir . '/' . $file;
                    break;
                }
            }

            if (!$rootDir || !is_dir($rootDir . '/' . $itemName)) {
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('项目文件结构不正确', 500);
                return;
            }

            // 复制项目文件
            $sourceDir = $rootDir . '/' . $itemName;
            self::copyDirectory($sourceDir, $itemDir);
            self::deleteDirectory($extractDir);

            Anon_Http_Response::success([
                'name' => $itemName,
                'path' => $itemDir,
                'type' => $type
            ], '安装成功');
        } catch (Exception $e) {
            Anon_Http_Response::error('下载项目失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 应用 GitHub 镜像
     */
    private static function applyGithubMirror(string $url): string
    {
        $mirror = Anon_Cms_Options::get('github_mirror', '');
        $rawMirror = Anon_Cms_Options::get('github_raw_mirror', '');
        
        // 优先使用 raw 镜像
        if (strpos($url, 'raw.githubusercontent.com') !== false && !empty($rawMirror)) {
            return str_replace('https://raw.githubusercontent.com', rtrim($rawMirror, '/'), $url);
        }
        
        // 使用通用镜像
        if (!empty($mirror)) {
            return str_replace('https://github.com', rtrim($mirror, '/'), $url);
        }
        
        return $url;
    }

    /**
     * 复制目录
     */
    private static function copyDirectory(string $src, string $dst): void
    {
        $dir = opendir($src);
        
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            if (is_dir($srcPath)) {
                self::copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }

        closedir($dir);
    }

    /**
     * 删除目录
     */
    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
