<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 在线商店
 */
class Anon_Cms_Admin_Store
{
    /**
     * GitHub API 仓库地址
     */
    private const THEME_API_REPO = 'https://raw.githubusercontent.com/YuiNijika/AnonCMS-Theme-API/main/list.json';
    private const PLUGIN_API_REPO = 'https://raw.githubusercontent.com/YuiNijika/AnonCMS-Plugin-API/main/list.json';
    
    /**
     * 缓存时间
     */
    private const CACHE_TTL = 3600;

    /**
     * 获取 GitHub URL（支持 raw 和 API 镜像）
     * @param string $url 原始 URL
     * @return string 处理后的 URL
     */
    private static function getGithubUrl(string $url): string
    {
        $mirror = Anon_Cms_Options::get('github_mirror', '');
        $rawMirror = Anon_Cms_Options::get('github_raw_mirror', '');
        
        // 优先使用 raw 镜像（针对 raw.githubusercontent.com）
        if (strpos($url, 'raw.githubusercontent.com') !== false && !empty($rawMirror)) {
            return str_replace('https://raw.githubusercontent.com', rtrim($rawMirror, '/'), $url);
        }
        
        // 使用通用镜像
        if (!empty($mirror)) {
            return str_replace('https://raw.githubusercontent.com', rtrim($mirror, '/'), $url);
        }
        
        return $url;
    }

    /**
     * 获取项目列表
     * @return void
     */
    public static function getList(): void
    {
        $type = $_GET['type'] ?? 'theme';
        
        try {
            $apiUrl = $type === 'plugin' ? self::PLUGIN_API_REPO : self::THEME_API_REPO;
            $apiUrl = self::getGithubUrl($apiUrl);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Anon-CMS-Store',
                    'timeout' => 10,
                ]
            ]);

            $response = @file_get_contents($apiUrl, false, $context);
            
            if ($response === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : '无法连接到仓库';
                Anon_Http_Response::error('获取列表失败：' . $errorMsg, 500);
                return;
            }

            $items = json_decode($response, true);
            
            if (!is_array($items)) {
                Anon_Http_Response::error('数据格式错误', 500);
                return;
            }

            // 添加类型字段
            foreach ($items as &$item) {
                $item['type'] = $type;
            }

            Anon_Http_Response::success([
                'items' => $items,
                'total' => count($items),
                'type' => $type
            ], '获取列表成功');
        } catch (Exception $e) {
            Anon_Http_Response::error('获取列表失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取项目详情（README）
     * @return void
     */
    public static function getDetail(): void
    {
        $data = Anon_Http_Request::getInput();
        $name = $data['name'] ?? '';
        $type = $data['type'] ?? 'theme';

        if (empty($name)) {
            Anon_Http_Response::error('项目名称不能为空', 400);
            return;
        }

        try {
            // 获取 README.md
            $repoName = $type === 'plugin' ? 'AnonCMS-Plugin' : 'AnonCMS-Theme';
            $readmeUrl = "https://raw.githubusercontent.com/YuiNijika/{$repoName}/main/{$name}/README.md";
            $readmeUrl = self::getGithubUrl($readmeUrl);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Anon-CMS-Store',
                    'timeout' => 5,
                ]
            ]);

            $readmeContent = @file_get_contents($readmeUrl, false, $context);
            
            if ($readmeContent === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : '无法连接到仓库';
                Anon_Http_Response::error('获取README失败：' . $errorMsg, 500);
                return;
            }

            // 获取版本信息
            $packageUrl = "https://raw.githubusercontent.com/YuiNijika/{$repoName}/main/{$name}/package.json";
            $packageUrl = self::getGithubUrl($packageUrl);
            $packageInfo = @file_get_contents($packageUrl, false, $context);
            $remoteVersion = '1.0.0';
            
            if ($packageInfo !== false) {
                $package = json_decode($packageInfo, true);
                if (is_array($package) && isset($package['version'])) {
                    $remoteVersion = $package['version'];
                }
            }

            // 检查本地版本
            $targetDir = $type === 'plugin' ? Anon_Main::APP_DIR . 'Plugin/' : Anon_Main::APP_DIR . 'Theme/';
            $itemDir = $targetDir . $name . '/';
            $localVersion = null;
            
            if (is_dir($itemDir)) {
                $localPackagePath = $itemDir . 'package.json';
                if (file_exists($localPackagePath)) {
                    $localPackageContent = file_get_contents($localPackagePath);
                    $localPackage = json_decode($localPackageContent, true);
                    if (is_array($localPackage) && isset($localPackage['version'])) {
                        $localVersion = $localPackage['version'];
                    }
                } else if ($type === 'theme') {
                    $indexPath = $itemDir . 'index.php';
                    if (file_exists($indexPath)) {
                        $indexContent = file_get_contents($indexPath);
                        if (preg_match('/Version:\s*(.+)/i', $indexContent, $matches)) {
                            $localVersion = trim($matches[1]);
                        }
                    }
                }
            }

            Anon_Http_Response::success([
                'name' => $name,
                'type' => $type,
                'readme' => $readmeContent,
                'remote_version' => $remoteVersion,
                'local_version' => $localVersion,
                'is_installed' => $localVersion !== null,
                'needs_update' => $localVersion !== null && version_compare($localVersion, $remoteVersion) < 0
            ], '获取详情成功');
        } catch (Exception $e) {
            Anon_Http_Response::error('获取详情失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 下载项目
     * @return void
     */
    public static function download(): void
    {
        $data = Anon_Http_Request::getInput();
        $itemName = $data['name'] ?? '';
        $type = $data['type'] ?? 'theme';

        if (empty($itemName)) {
            Anon_Http_Response::error('项目名称不能为空', 400);
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $itemName)) {
            Anon_Http_Response::error('无效的项目名称', 400);
            return;
        }

        try {
            // 获取远程版本信息
            $repoName = $type === 'plugin' ? 'AnonCMS-Plugin' : 'AnonCMS-Theme';
            $packageUrl = "https://raw.githubusercontent.com/YuiNijika/{$repoName}/main/{$itemName}/package.json";
            $packageUrl = self::getGithubUrl($packageUrl);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Anon-CMS-Store',
                    'timeout' => 5,
                ]
            ]);

            $packageInfo = @file_get_contents($packageUrl, false, $context);
            $remoteVersion = '1.0.0';
            
            if ($packageInfo !== false) {
                $package = json_decode($packageInfo, true);
                if (is_array($package) && isset($package['version'])) {
                    $remoteVersion = $package['version'];
                }
            }

            // 检查本地是否已安装
            $targetDir = $type === 'plugin' ? Anon_Main::APP_DIR . 'Plugin/' : Anon_Main::APP_DIR . 'Theme/';
            $itemDir = $targetDir . $itemName . '/';
            $localVersion = null;
            
            if (is_dir($itemDir)) {
                $localPackagePath = $itemDir . 'package.json';
                if (file_exists($localPackagePath)) {
                    $localPackageContent = file_get_contents($localPackagePath);
                    $localPackage = json_decode($localPackageContent, true);
                    if (is_array($localPackage) && isset($localPackage['version'])) {
                        $localVersion = $localPackage['version'];
                    }
                } else if ($type === 'theme') {
                    $indexPath = $itemDir . 'index.php';
                    if (file_exists($indexPath)) {
                        $indexContent = file_get_contents($indexPath);
                        if (preg_match('/Version:\s*(.+)/i', $indexContent, $matches)) {
                            $localVersion = trim($matches[1]);
                        }
                    }
                }
            }

            // 版本比较
            if ($localVersion !== null) {
                $versionCompare = version_compare($localVersion, $remoteVersion);
                
                if ($versionCompare >= 0) {
                    Anon_Http_Response::success([
                        'name' => $itemName,
                        'local_version' => $localVersion,
                        'remote_version' => $remoteVersion,
                        'status' => 'installed',
                        'message' => '已是最新版本'
                    ], '已安装');
                    return;
                }
                
                $action = 'upgrade';
            } else {
                $action = 'install';
            }

            $downloadUrl = "https://github.com/YuiNijika/{$repoName}/archive/refs/heads/main.zip";
            
            $tmpFile = tempnam(sys_get_temp_dir(), 'store_');
            
            $downloadContext = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Anon-CMS-Store',
                    'timeout' => 60,
                    'follow_location' => true,
                ]
            ]);

            $zipContent = @file_get_contents($downloadUrl, false, $downloadContext);
            
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

            if (is_dir($itemDir)) {
                self::deleteDirectory($itemDir);
            }

            mkdir($itemDir, 0755, true);

            $extractDir = sys_get_temp_dir() . '/store_extract_' . uniqid();
            mkdir($extractDir, 0755, true);
            
            $zip->extractTo($extractDir);
            $zip->close();
            @unlink($tmpFile);

            $extractedItemDir = $extractDir . '/' . $repoName . '-main/' . $itemName . '/';
            
            if (!is_dir($extractedItemDir)) {
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('项目文件结构不正确', 500);
                return;
            }

            self::copyDirectory($extractedItemDir, $itemDir);
            self::deleteDirectory($extractDir);

            Anon_Http_Response::success([
                'name' => $itemName,
                'path' => $itemDir,
                'type' => $type,
                'action' => $action,
                'local_version' => $localVersion,
                'remote_version' => $remoteVersion
            ], $action === 'upgrade' ? '升级成功' : '安装成功');
        } catch (Exception $e) {
            Anon_Http_Response::error('下载项目失败：' . $e->getMessage(), 500);
        }
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
