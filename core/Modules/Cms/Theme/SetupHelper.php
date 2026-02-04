<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * Setup 文件辅助类
 * 为 app/setup.php 提供 $this 上下文，支持调用 siteUrl() 等方法
 */
class Anon_Cms_Theme_Setup_Helper
{
    /**
     * @var string 主题名
     */
    private $themeName;

    /**
     * @param string $themeName 主题名
     */
    public function __construct(string $themeName)
    {
        $this->themeName = $themeName;
    }

    /**
     * 加载 setup.php 文件
     * @param string $setupFile setup.php 文件路径
     * @return mixed
     */
    public function loadSetupFile(string $setupFile)
    {
        return include $setupFile;
    }

    /**
     * 获取站点 URL
     * @param string $suffix 相对路径
     * @return string
     */
    public function siteUrl(string $suffix = ''): string
    {
        $base = Anon_Cms_Theme_View::getSiteBaseUrl();
        if ($suffix === '') {
            return $base;
        }
        $suffix = '/' . ltrim($suffix, '/');
        return $base . $suffix;
    }

    /**
     * 获取主题 URL
     * @param string $path 资源路径
     * @return string
     */
    public function themeUrl(string $path = ''): string
    {
        $base = Anon_Cms_Theme_View::getSiteBaseUrl();
        if ($path === '') {
            return rtrim($base, '/');
        }
        $url = Anon_Cms_Theme::getAssetUrl($path);
        return $url !== '' ? $base . $url : rtrim($base, '/') . '/assets/files/' . ltrim($path, '/');
    }

    /**
     * 获取选项代理对象
     * @return Anon_Cms_Options_Proxy
     */
    public function options(): Anon_Cms_Options_Proxy
    {
        return new Anon_Cms_Options_Proxy('theme', null, $this->themeName);
    }

    /**
     * 获取主题辅助对象
     * @return Anon_Cms_Theme_Helper
     */
    public function theme(): Anon_Cms_Theme_Helper
    {
        return new Anon_Cms_Theme_Helper($this->themeName);
    }
}
