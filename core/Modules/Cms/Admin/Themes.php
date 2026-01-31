<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 主题管理类
 */
class Anon_Cms_Admin_Themes
{
    /**
     * 上传主题
     * @return void
     */
    public static function upload()
    {
        try {
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                Anon_Http_Response::error('文件上传失败', 400);
                return;
            }

            $file = $_FILES['file'];
            $fileName = $file['name'];
            $tmpPath = $file['tmp_name'];

            if (!preg_match('/\.(zip)$/i', $fileName)) {
                Anon_Http_Response::error('只支持 ZIP 格式的主题包', 400);
                return;
            }

            $themeDir = Anon_Main::APP_DIR . 'Theme/';
            if (!is_dir($themeDir)) {
                mkdir($themeDir, 0755, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                Anon_Http_Response::error('无法打开 ZIP 文件', 400);
                return;
            }

            $extractDir = sys_get_temp_dir() . '/anon_theme_' . uniqid();
            if (!mkdir($extractDir, 0755, true)) {
                $zip->close();
                Anon_Http_Response::error('无法创建临时目录', 500);
                return;
            }

            if (!$zip->extractTo($extractDir)) {
                $zip->close();
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('解压 ZIP 文件失败', 500);
                return;
            }

            $zip->close();

            $themeName = null;
            $entries = scandir($extractDir);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $entryPath = $extractDir . '/' . $entry;
                if (is_dir($entryPath)) {
                    $themeName = $entry;
                    break;
                }
            }

            if (!$themeName) {
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('ZIP 文件中未找到主题目录', 400);
                return;
            }

            $targetDir = $themeDir . $themeName;
            if (is_dir($targetDir)) {
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('主题已存在', 400);
                return;
            }

            if (!rename($extractDir . '/' . $themeName, $targetDir)) {
                self::deleteDirectory($extractDir);
                Anon_Http_Response::error('移动主题文件失败', 500);
                return;
            }

            self::deleteDirectory($extractDir);
            Anon_Cms_Options::clearCache();

            Anon_Http_Response::success(['name' => $themeName], '上传主题成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 删除主题
     * @return void
     */
    public static function delete()
    {
        try {
            $data = Anon_Http_Request::getInput();
            $themeName = isset($data['name']) ? trim($data['name']) : '';

            if (empty($themeName)) {
                Anon_Http_Response::error('主题名称不能为空', 400);
                return;
            }

            $currentTheme = Anon_Cms_Options::get('theme', 'default');
            if ($themeName === $currentTheme) {
                Anon_Http_Response::error('不能删除当前使用的主题', 400);
                return;
            }

            $themeDir = Anon_Main::APP_DIR . 'Theme/';
            $themePath = $themeDir . $themeName;

            if (!is_dir($themePath)) {
                Anon_Http_Response::error('主题不存在', 404);
                return;
            }

            self::deleteDirectory($themePath);
            Anon_Cms_Options::clearCache();

            Anon_Http_Response::success(['name' => $themeName], '删除主题成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 递归删除目录
     * @param string $dir 目录路径
     * @return void
     */
    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

