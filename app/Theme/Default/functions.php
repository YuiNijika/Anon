<?php
/**
 * 主题自定义代码文件
 *
 * 说明：
 * - 用于主题初始化时执行自定义代码
 * - 用于注册主题设置项
 * - 用于注册钩子
 * - 用于注册自定义路由
 * - 用于注册错误处理器
 * - 用于添加自定义函数和类
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册主题设置项

// 注册文本类型设置项
// Anon_Theme_Options::register('site_title', [
//     'type' => 'text',
//     'label' => '网站标题',
//     'description' => '显示在网站首页的标题',
//     'default' => '我的网站',
//     'sanitize_callback' => function($value) {
//         return trim(strip_tags($value));
//     },
//     'validate_callback' => function($value) {
//         return strlen($value) <= 100;
//     },
// ]);

// 注册文本域类型设置项
// Anon_Theme_Options::register('site_description', [
//     'type' => 'textarea',
//     'label' => '网站描述',
//     'description' => '网站的描述信息',
//     'default' => '',
// ]);

// 注册选择类型设置项
// Anon_Theme_Options::register('color_scheme', [
//     'type' => 'select',
//     'label' => '配色方案',
//     'description' => '选择主题的配色方案',
//     'default' => 'light',
//     'options' => [
//         'light' => '浅色',
//         'dark' => '深色',
//         'auto' => '自动',
//     ],
// ]);

// 注册开关类型设置项
// Anon_Theme_Options::register('show_sidebar', [
//     'type' => 'checkbox',
//     'label' => '显示侧边栏',
//     'description' => '是否在页面中显示侧边栏',
//     'default' => true,
// ]);

// 使用主题设置

// 获取主题设置值
// $siteTitle = Anon_Theme_Options::get('site_title', '默认标题');
// $showSidebar = Anon_Theme_Options::get('show_sidebar', false);

// 设置主题设置值
// Anon_Theme_Options::set('site_title', '新标题');

// 获取所有主题设置
// $allSettings = Anon_Theme_Options::all();

// 注册动作钩子

// Anon_System_Hook::add_action('theme_head', function () {
//     // 输出自定义 meta
//     echo '<meta name="custom-meta" content="value">';
// });

// Anon_System_Hook::add_action('theme_foot', function () {
//     // 输出自定义脚本
//     echo '<script>console.log("Theme loaded");</script>';
// });

// Anon_System_Hook::add_action('theme_before_render', function ($templateName, $data) {
//     // 渲染模板前执行
//     Anon_Debug::info("准备渲染模板: {$templateName}");
// });

// 注册过滤器钩子

// Anon_System_Hook::add_filter('theme_page_title', function ($title) {
//     // 修改页面标题
//     $siteTitle = Anon_Theme_Options::get('site_title', '');
//     return $siteTitle ? "{$title} - {$siteTitle}" : $title;
// });

// Anon_System_Hook::add_filter('theme_post_content', function ($content) {
//     // 修改文章内容
//     return $content;
// });

// 注册自定义路由

// Anon_System_Config::addRoute('/theme/custom', function () {
//     Anon_Common::Header();
//     $setting = Anon_Theme_Options::get('custom_setting', 'default');
//     Anon_Http_Response::success(['setting' => $setting], '获取主题设置成功');
// });

// 注册错误处理器

// Anon_System_Config::addErrorHandler(404, function () {
//     Anon_Common::Header(404);
//     // 渲染主题错误页
// });

// 自定义函数和类

// function theme_custom_function($param) {
//     // 自定义函数
//     return $param;
// }

// class Theme_Custom_Class {
//     // 自定义类
//     public function method() {
//         return 'custom';
//     }
// }
