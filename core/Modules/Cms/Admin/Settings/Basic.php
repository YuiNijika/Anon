<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 基本设置管理类
 * 处理系统基本设置相关的功能
 */
class Anon_Cms_Admin_SettingsBasic
{
    /**
     * 获取基本设置
     * @return void
     */
    public static function get()
    {
        try {
            $uploadAllowedTypesValue = Anon_Cms_Options::get('upload_allowed_types', '');
            $uploadAllowedTypes = [];

            /**
             * 解析上传允许类型配置
             * 如果已经是数组则直接使用，否则尝试解析 JSON 字符串
             */
            if (is_array($uploadAllowedTypesValue)) {
                $uploadAllowedTypes = $uploadAllowedTypesValue;
            } elseif (is_string($uploadAllowedTypesValue) && !empty($uploadAllowedTypesValue)) {
                $decoded = json_decode($uploadAllowedTypesValue, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $uploadAllowedTypes = $decoded;
                }
            }

            /**
             * 新格式为空时从旧字段读取
             */
            if (empty($uploadAllowedTypes)) {
                $uploadAllowedTypes = [
                    'image' => Anon_Cms_Options::get('upload_allowed_image', 'gif,jpg,jpeg,png,tiff,bmp,webp,avif'),
                    'media' => Anon_Cms_Options::get('upload_allowed_media', 'mp3,mp4,mov,wmv,wma,rmvb,rm,avi,flv,ogg,oga,ogv'),
                    'document' => Anon_Cms_Options::get('upload_allowed_document', 'txt,doc,docx,xls,xlsx,ppt,pptx,zip,rar,pdf'),
                    'other' => Anon_Cms_Options::get('upload_allowed_other', ''),
                ];
            }

            /**
             * 将各种格式的值转换为布尔值
             * @param mixed $value 待转换的值
             * @return bool
             */
            $toBool = function ($value) {
                if ($value === true || $value === 'true' || $value === '1' || $value === 1) {
                    return true;
                }
                return false;
            };

            /**
             * 获取路由规则配置
             */
            $routesValue = Anon_Cms_Options::get('routes', '');
            $routes = [];

            if (is_array($routesValue)) {
                $routes = $routesValue;
            } elseif (is_string($routesValue) && !empty($routesValue)) {
                $decoded = json_decode($routesValue, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $routes = $decoded;
                }
            }

            $settings = [
                'title' => Anon_Cms_Options::get('title', ''),
                'subtitle' => Anon_Cms_Options::get('subtitle', 'Powered by AnonEcho'),
                'description' => Anon_Cms_Options::get('description', ''),
                'keywords' => Anon_Cms_Options::get('keywords', ''),
                'upload_allowed_types' => $uploadAllowedTypes,
                'routes' => $routes,
            ];

            Anon_Http_Response::success($settings, '获取基本设置成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }

    /**
     * 保存基本设置
     * @return void
     */
    public static function save()
    {
        try {
            $data = Anon_Http_Request::getInput();

            if (empty($data)) {
                Anon_Http_Response::error('请求数据不能为空', 400);
                return;
            }

            /**
             * 辅助函数：将各种格式的布尔值转换为 '1' 或 '0'
             * @param mixed $value 待转换的值
             * @return string '1' 或 '0'
             */
            $toBoolString = function ($value) {
                // 处理布尔值 true/false
                if ($value === true) {
                    return '1';
                }
                if ($value === false) {
                    return '0';
                }
                // 处理字符串 'true'/'false'
                if (is_string($value)) {
                    $lowerValue = strtolower(trim($value));
                    if ($lowerValue === 'true' || $lowerValue === '1') {
                        return '1';
                    }
                    return '0';
                }
                // 处理数字 1/0
                if (is_numeric($value)) {
                    return ($value == 1) ? '1' : '0';
                }
                // 其他情况默认为 '0'
                return '0';
            };

            $siteName = isset($data['title']) ? trim($data['title']) : '';
            $siteSubtitle = isset($data['subtitle']) ? trim($data['subtitle']) : 'Powered by AnonEcho';
            $siteDescription = isset($data['description']) ? trim($data['description']) : '';
            $keywords = isset($data['keywords']) ? trim($data['keywords']) : '';

            /**
             * 处理上传允许类型配置
             */
            $uploadAllowedTypes = [];
            if (isset($data['upload_allowed_types']) && is_array($data['upload_allowed_types'])) {
                $uploadAllowedTypes = $data['upload_allowed_types'];
            } else {
                /**
                 * 从旧格式字段读取
                 */
                $uploadAllowedTypes = [
                    'image' => isset($data['upload_allowed_image']) ? trim($data['upload_allowed_image']) : '',
                    'media' => isset($data['upload_allowed_media']) ? trim($data['upload_allowed_media']) : '',
                    'document' => isset($data['upload_allowed_document']) ? trim($data['upload_allowed_document']) : '',
                    'other' => isset($data['upload_allowed_other']) ? trim($data['upload_allowed_other']) : '',
                ];
            }

            if (empty($siteName)) {
                Anon_Http_Response::error('站点名称不能为空', 400);
                return;
            }

            Anon_Cms_Options::set('title', $siteName);
            Anon_Cms_Options::set('subtitle', $siteSubtitle);
            Anon_Cms_Options::set('description', $siteDescription);
            Anon_Cms_Options::set('keywords', $keywords);
            Anon_Cms_Options::set('upload_allowed_types', json_encode($uploadAllowedTypes, JSON_UNESCAPED_UNICODE));
            /**
             * 仅当请求中带 routes 时才更新链接设置，链接设置已迁移到链接设置页
             */
            if (isset($data['routes']) && is_array($data['routes'])) {
                Anon_Cms_Options::set('routes', json_encode($data['routes'], JSON_UNESCAPED_UNICODE));
            }
            Anon_Cms_Options::clearCache();

            $routesValue = Anon_Cms_Options::get('routes', '');
            $routes = is_array($routesValue) ? $routesValue : (is_string($routesValue) && $routesValue !== '' ? (json_decode($routesValue, true) ?: []) : []);
            Anon_Http_Response::success([
                'title' => $siteName,
                'subtitle' => $siteSubtitle,
                'description' => $siteDescription,
                'keywords' => $keywords,
                'upload_allowed_types' => $uploadAllowedTypes,
                'routes' => $routes,
            ], '保存设置成功');
        } catch (Exception $e) {
            Anon_Http_Response::handleException($e);
        }
    }
}
