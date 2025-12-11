<?php
/**
 * 自定义代码文件
 * 
 * - 注册钩子
 *      使用 Anon_Hook::add_action 和 Anon_Hook::add_filter 注册钩子
 * - 注册自定义路由
 *      使用 Anon_Config::addRoute 注册自定义路由
 * - 注册错误处理器
 *      使用 Anon_Config::addErrorHandler 注册错误处理器
 * - 添加自定义函数和类
 *      在文件中添加自定义函数和类
 * - 执行任何初始化代码
 *      在文件中执行任何初始化代码
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

// 注册一个动作钩子
// Anon_Hook::add_action('router_before_init', function () {
//     // 在路由初始化前执行
//     Anon_Debug::info('自定义代码：路由初始化前');
// });

// 注册一个过滤器钩子
// Anon_Hook::add_filter('router_request_path', function ($path) {
//     // 修改请求路径
//     return $path;
// });

// 注册自定义路由
// Anon_Config::addRoute('/api/custom', function () {
//     Anon_Common::Header();
//     Anon_ResponseHelper::success(['message' => '自定义路由']);
// });

// 注册错误处理器
// Anon_Config::addErrorHandler(404, function () {
//     Anon_Common::Header(404);
//     Anon_ResponseHelper::notFound('页面不存在');
// });
