<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 链接设置
 * 读写 options 表 routes 字段，供后台链接设置页使用
 *
 * @package Anon/Cms/Admin/Settings
 */
class Anon_Cms_Admin_Settings_Page
{
    /**
     * 获取链接设置
     * @return void
     */
    public static function get(): void
    {
        try {
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
            Anon_Http_Response::success(['routes' => $routes], '获取链接设置成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 保存链接设置
     * @return void
     */
    public static function save(): void
    {
        try {
            $data = Anon_Http_Request::getInput();
            $routes = isset($data['routes']) && is_array($data['routes']) ? $data['routes'] : [];
            Anon_Cms_Options::set('routes', json_encode($routes, JSON_UNESCAPED_UNICODE));
            Anon_Cms_Options::clearCache();
            Anon_Http_Response::success(['routes' => $routes], '保存链接设置成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}
