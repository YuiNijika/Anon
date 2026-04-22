<?php
namespace Anon\Modules\Cms\AdminSettings;





use Exception;
use Settings;
use Anon\Modules\Cms\Cms;
use Options;
use Anon\Modules\Http\RequestHelper;
use Anon\Modules\Http\ResponseHelper;

if (!defined('ANON_ALLOWED_ACCESS')) exit;

/**
 * 基本设置管理类
 * 处理系统基本设置相关的功能
 */
class Basic
{
    /**
     * 获取基本设置
     * @return void
     */
    public static function get()
    {
        try {
            $uploadAllowedTypesValue = Options::get('upload_allowed_types', '');
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
                    'image' => Options::get('upload_allowed_image', 'gif,jpg,jpeg,png,tiff,bmp,webp,avif'),
                    'media' => Options::get('upload_allowed_media', 'mp3,mp4,mov,wmv,wma,rmvb,rm,avi,flv,ogg,oga,ogv'),
                    'document' => Options::get('upload_allowed_document', 'txt,doc,docx,xls,xlsx,ppt,pptx,zip,rar,pdf'),
                    'other' => Options::get('upload_allowed_other', ''),
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
            $routesValue = Options::get('routes', '');
            $routes = [];

            if (is_array($routesValue)) {
                $routes = $routesValue;
            } elseif (is_string($routesValue) && !empty($routesValue)) {
                $decoded = json_decode($routesValue, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $routes = $decoded;
                }
            }

            // 如果路由为空，使用默认路由
            if (empty($routes)) {
                $routes = Cms::DEFAULT_ROUTES;
            }

            $settings = [
                'title' => Options::get('title', ''),
                'subtitle' => Options::get('subtitle', 'Powered by AnonEcho'),
                'description' => Options::get('description', ''),
                'keywords' => Options::get('keywords', ''),
                'upload_allowed_types' => $uploadAllowedTypes,
                'routes' => $routes,
                'github_mirror' => Options::get('github_mirror', ''),
                'github_raw_mirror' => Options::get('github_raw_mirror', ''),
            ];

            ResponseHelper::success($settings, '获取基本设置成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * 保存基本设置
     * @return void
     */
    public static function save()
    {
        try {
            $data = RequestHelper::getInput();

            if (empty($data)) {
                ResponseHelper::error('请求数据不能为空', null, 400);
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
            if (isset($data['upload_allowed_types'])) {
                // 如果是数组
                if (is_array($data['upload_allowed_types'])) {
                    $uploadAllowedTypes = [
                        'image' => isset($data['upload_allowed_types']['image']) ? trim($data['upload_allowed_types']['image']) : '',
                        'media' => isset($data['upload_allowed_types']['media']) ? trim($data['upload_allowed_types']['media']) : '',
                        'document' => isset($data['upload_allowed_types']['document']) ? trim($data['upload_allowed_types']['document']) : '',
                        'other' => isset($data['upload_allowed_types']['other']) ? trim($data['upload_allowed_types']['other']) : '',
                    ];
                } else {
                    // 从旧格式字段读取
                    $uploadAllowedTypes = [
                        'image' => isset($data['upload_allowed_image']) ? trim($data['upload_allowed_image']) : '',
                        'media' => isset($data['upload_allowed_media']) ? trim($data['upload_allowed_media']) : '',
                        'document' => isset($data['upload_allowed_document']) ? trim($data['upload_allowed_document']) : '',
                        'other' => isset($data['upload_allowed_other']) ? trim($data['upload_allowed_other']) : '',
                    ];
                }
            } else {
                // 从旧格式字段读取
                $uploadAllowedTypes = [
                    'image' => isset($data['upload_allowed_image']) ? trim($data['upload_allowed_image']) : '',
                    'media' => isset($data['upload_allowed_media']) ? trim($data['upload_allowed_media']) : '',
                    'document' => isset($data['upload_allowed_document']) ? trim($data['upload_allowed_document']) : '',
                    'other' => isset($data['upload_allowed_other']) ? trim($data['upload_allowed_other']) : '',
                ];
            }

            if (empty($siteName)) {
                ResponseHelper::error('站点名称不能为空', null, 400);
                return;
            }

            Options::set('title', $siteName);
            Options::set('subtitle', $siteSubtitle);
            Options::set('description', $siteDescription);
            Options::set('keywords', $keywords);
            Options::set('upload_allowed_types', json_encode($uploadAllowedTypes, JSON_UNESCAPED_UNICODE));
            
            if (isset($data['github_mirror'])) {
                Options::set('github_mirror', trim($data['github_mirror']));
            }
            
            if (isset($data['github_raw_mirror'])) {
                Options::set('github_raw_mirror', trim($data['github_raw_mirror']));
            }
            
            /**
             * 仅当请求中带 routes 时才更新链接设置，链接设置已迁移到链接设置页
             */
            if (isset($data['routes']) && is_array($data['routes'])) {
                Options::set('routes', json_encode($data['routes'], JSON_UNESCAPED_UNICODE));
            }
            Options::clearCache();

            $routesValue = Options::get('routes', '');
            $routes = is_array($routesValue) ? $routesValue : (is_string($routesValue) && $routesValue !== '' ? (json_decode($routesValue, true) ?: []) : []);
            ResponseHelper::success([
                'title' => $siteName,
                'subtitle' => $siteSubtitle,
                'description' => $siteDescription,
                'keywords' => $keywords,
                'upload_allowed_types' => $uploadAllowedTypes,
                'routes' => $routes,
                'github_mirror' => Options::get('github_mirror', ''),
                'github_raw_mirror' => Options::get('github_raw_mirror', ''),
            ], '保存设置成功');
        } catch (Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }
}
