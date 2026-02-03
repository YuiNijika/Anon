<?php

/**
 * CMS 当前用户对象
 * 主题与插件内通过 $this->user() 获取，未登录时为 null
 *
 * @package Anon/Core/Widgets/Cms
 */
if (!defined('ANON_ALLOWED_ACCESS')) exit;

class Anon_Cms_User
{
    /**
     * @var array 用户数据
     */
    private $data;

    /**
     * 构造
     * @param array $data 用户数据
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * 用户 ID
     * @return int
     */
    public function uid(): int
    {
        return (int) ($this->data['uid'] ?? $this->data['id'] ?? 0);
    }

    /**
     * 登录名
     * @return string
     */
    public function name(): string
    {
        return (string) ($this->data['name'] ?? '');
    }

    /**
     * 邮箱
     * @return string
     */
    public function email(): string
    {
        return (string) ($this->data['email'] ?? '');
    }

    /**
     * 显示名称，缺省为登录名
     * @return string
     */
    public function displayName(): string
    {
        $dn = $this->data['display_name'] ?? '';
        return (string) $dn !== '' ? (string) $dn : $this->name();
    }

    /**
     * 头像 URL
     * @return string
     */
    public function avatar(): string
    {
        return (string) ($this->data['avatar'] ?? '');
    }

    /**
     * 当前请求是否已登录
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return class_exists('Anon_Check') && Anon_Check::isLoggedIn();
    }

    /**
     * 该用户是否为当前登录用户
     * @return bool
     */
    public function isCurrentUser(): bool
    {
        if (!self::isLoggedIn() || $this->uid() <= 0) {
            return false;
        }
        $currentUid = class_exists('Anon_Http_Request') ? Anon_Http_Request::getUserId() : 0;
        return $currentUid && (int) $currentUid === $this->uid();
    }

    /**
     * 用户或作者页固定链接，与 url 一致
     * @return string
     */
    public function permalink(): string
    {
        return $this->url();
    }

    /**
     * 用户或作者页 URL，按链接设置中的路径规则生成
     * @return string
     */
    public function url(): string
    {
        if (!class_exists('Anon_Cms_Theme') || !method_exists('Anon_Cms_Theme', 'getSiteBaseUrl')) {
            return '';
        }
        $pattern = self::getUserPathPattern();
        $path = str_replace('{uid}', (string) $this->uid(), $pattern);
        $path = str_replace('{name}', rawurlencode($this->name()), $path);
        return rtrim(Anon_Cms_Theme::getSiteBaseUrl(), '/') . $path;
    }

    /**
     * 从 options 的 routes 中取用户路径规则
     * @return string
     */
    private static function getUserPathPattern(): string
    {
        if (!class_exists('Anon_Cms_Options')) {
            return '/user/{uid}/';
        }
        $routesValue = Anon_Cms_Options::get('routes', '');
        $routes = [];
        if (is_array($routesValue)) {
            $routes = $routesValue;
        } elseif (is_string($routesValue) && $routesValue !== '') {
            $decoded = json_decode($routesValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $routes = $decoded;
            }
        }
        foreach ($routes as $path => $type) {
            if ($type === 'user' && is_string($path) && $path !== '') {
                return $path;
            }
        }
        return '/user/{uid}/';
    }

    /**
     * 转为数组
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * 按键取值
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
}
