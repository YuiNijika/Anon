<?php

/**
 * CMS 主题辅助对象
 *
 * 主题/插件内通过 $this->theme() 获取，提供主题名、主题选项、站点/主题 URL 等，类似 Typecho 的 $this->options 与 themeUrl。
 * 需在 Cms/Theme/Options.php 与 Cms/Theme/Theme.php 加载后使用。
 *
 * @package Anon/Core/Widgets/Cms
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_Theme_Helper
{
    /**
     * @var string 当前主题名
     */
    private $themeName;

    /**
     * 构造主题辅助对象
     * @param string $themeName 主题名，通常由 Anon_Cms_Theme::getCurrentTheme() 传入
     */
    public function __construct(string $themeName)
    {
        $this->themeName = $themeName;
    }

    /**
     * 当前主题名
     * @return string
     */
    public function name(): string
    {
        return $this->themeName;
    }

    /**
     * 仅读当前主题选项，不包含插件与系统选项
     * @param string $key 选项键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Anon_Theme_Options::get($key, $default, $this->themeName);
    }

    /**
     * 统一选项代理，优先级 theme > plugin > system，与 $this->options() 一致
     * @return Anon_Cms_Options_Proxy
     */
    public function options(): Anon_Cms_Options_Proxy
    {
        return new Anon_Cms_Options_Proxy('theme', null, $this->themeName);
    }

    /**
     * 站点根 URL，带参数时拼接相对路径，类似 Typecho options->siteUrl
     * @param string $suffix 相对路径，如 /about、/post/1
     * @return string
     */
    public function siteUrl(string $suffix = ''): string
    {
        $base = Anon_Cms_Theme::getSiteBaseUrl();
        if ($suffix === '') {
            return $base;
        }
        $suffix = '/' . ltrim($suffix, '/');
        return $base . $suffix;
    }

    /**
     * 当前主题资源 URL，无参返回站点根 URL，带参返回该资源完整 URL，类似 Typecho options->themeUrl
     * @param string $path 资源相对路径，如 style.css、js/main.js
     * @return string
     */
    public function themeUrl(string $path = ''): string
    {
        $base = Anon_Cms_Theme::getSiteBaseUrl();
        if ($path === '') {
            return rtrim($base, '/');
        }
        $url = Anon_Cms_Theme::getAssetUrl($path);
        return $url !== '' ? $base . $url : rtrim($base, '/') . '/assets/files/' . ltrim($path, '/');
    }

    /**
     * 主题资源 URL，等同 themeUrl($path)，便于链式书写
     * @param string $path 资源相对路径
     * @return string
     */
    public function url(string $path = ''): string
    {
        return $this->themeUrl($path);
    }

    /**
     * 站点首页 URL，类似 Typecho options->index
     * @return string
     */
    public function index(): string
    {
        return Anon_Cms_Theme::getSiteBaseUrl();
    }
}
