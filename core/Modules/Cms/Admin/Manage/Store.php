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

        Anon_Debug::info('开始下载项目', [
            'name' => $itemName,
            'type' => $type,
            'original_url' => $downloadUrl
        ]);

        if (empty($itemName) || empty($downloadUrl)) {
            Anon_Debug::error('下载参数不完整', [
                'name_empty' => empty($itemName),
                'url_empty' => empty($downloadUrl)
            ]);
            Anon_Http_Response::error('项目名称和下载地址不能为空', 400);
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $itemName)) {
            Anon_Debug::error('项目名称格式无效', ['name' => $itemName]);
            Anon_Http_Response::error('无效的项目名称', 400);
            return;
        }

        try {
            // 应用 GitHub 镜像
            $originalUrl = $downloadUrl;
            $downloadUrl = self::applyGithubMirror($downloadUrl);
            
            Anon_Debug::info('URL处理完成', [
                'original_url' => $originalUrl,
                'mirrored_url' => $downloadUrl
            ]);
            
            $tmpFile = tempnam(sys_get_temp_dir(), 'store_');
            Anon_Debug::info('创建临时文件', ['tmp_file' => $tmpFile]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Anon-CMS-Store',
                    'timeout' => 60,
                    'follow_location' => true,
                ]
            ]);

            Anon_Debug::info('开始下载文件...');
            $zipContent = @file_get_contents($downloadUrl, false, $context);
            
            if ($zipContent === false) {
                $error = error_get_last();
                Anon_Debug::error('下载文件失败', [
                    'url' => $downloadUrl,
                    'error' => $error ? $error['message'] : '未知错误',
                    'tmp_file' => $tmpFile
                ]);
                @unlink($tmpFile);
                Anon_Http_Response::error('下载项目失败：无法连接到下载源', 500);
                return;
            }

            $fileSize = strlen($zipContent);
            Anon_Debug::info('文件下载成功', ['size' => $fileSize . ' bytes']);

            file_put_contents($tmpFile, $zipContent);
            Anon_Debug::info('临时文件写入完成');

            $zip = new ZipArchive();
            $zipResult = $zip->open($tmpFile);
            
            if ($zipResult !== true) {
                Anon_Debug::error('ZIP文件打开失败', [
                    'tmp_file' => $tmpFile,
                    'zip_error_code' => $zipResult,
                    'file_size' => filesize($tmpFile)
                ]);
                @unlink($tmpFile);
                Anon_Http_Response::error('解压项目失败：无效的ZIP文件', 500);
                return;
            }

            Anon_Debug::info('ZIP文件打开成功', ['files_count' => $zip->numFiles]);

            // 确定目标目录
            $targetDir = $type === 'plugin' ? Anon_Main::APP_DIR . 'Plugin/' : Anon_Main::APP_DIR . 'Theme/';
            $itemDir = $targetDir . $itemName . '/';

            Anon_Debug::info('目标目录', [
                'target_dir' => $targetDir,
                'item_dir' => $itemDir,
                'exists' => is_dir($itemDir)
            ]);

            // 如果已存在则删除
            if (is_dir($itemDir)) {
                Anon_Debug::info('删除已存在的目录', ['dir' => $itemDir]);
                self::deleteDirectory($itemDir);
            }

            mkdir($itemDir, 0755, true);
            Anon_Debug::info('创建目标目录成功');

            // 解压到临时目录
            $extractDir = sys_get_temp_dir() . '/store_extract_' . uniqid();
            mkdir($extractDir, 0755, true);
            Anon_Debug::info('创建解压目录', ['extract_dir' => $extractDir]);
            
            $zip->extractTo($extractDir);
            $zip->close();
            @unlink($tmpFile);
            Anon_Debug::info('文件解压完成');

            // 查找实际的项目目录
            $files = scandir($extractDir);
            Anon_Debug::info('解压后的文件列表', ['files' => $files]);
            
            $rootDir = null;
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_dir($extractDir . '/' . $file)) {
                    $rootDir = $extractDir . '/' . $file;
                    Anon_Debug::info('找到根目录', ['root_dir' => $rootDir]);
                    break;
                }
            }

            if (!$rootDir) {
                Anon_Debug::error('未找到项目根目录', ['extract_dir' => $extractDir]);
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('项目文件结构不正确：未找到根目录', 500);
                return;
            }

            $expectedPath = $rootDir . '/' . $itemName;
            if (!is_dir($expectedPath)) {
                Anon_Debug::error('项目目录不存在', [
                    'expected_path' => $expectedPath,
                    'root_dir_contents' => scandir($rootDir)
                ]);
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error("项目文件结构不正确：缺少 {$itemName} 目录", 500);
                return;
            }

            Anon_Debug::info('验证项目结构成功');

            // 复制项目文件
            $sourceDir = $rootDir . '/' . $itemName;
            Anon_Debug::info('开始复制文件', [
                'source' => $sourceDir,
                'destination' => $itemDir
            ]);
            
            self::copyDirectory($sourceDir, $itemDir);
            self::deleteDirectory($extractDir);
            
            Anon_Debug::info('文件复制完成，清理临时文件');

            Anon_Http_Response::success([
                'name' => $itemName,
                'path' => $itemDir,
                'type' => $type
            ], '安装成功');
            
            Anon_Debug::info('项目安装成功', [
                'name' => $itemName,
                'type' => $type,
                'path' => $itemDir
            ]);
        } catch (Exception $e) {
            Anon_Debug::error('下载项目异常', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
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
