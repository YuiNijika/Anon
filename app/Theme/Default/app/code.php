<?php

/**
 * 主题自定义代码
 *
 * 说明：
 * - 主题初始化时自动加载
 * - 用于注册钩子、自定义路由、错误处理器等
 */

if (!defined('ANON_ALLOWED_ACCESS')) exit;

$customCodeHead = $this->options('custom_code_head', '', false);
$customCodeFoot = $this->options('custom_code_foot', '', false);

if ($customCodeHead) {
    Anon_Hook::add_action('theme_head', function () use ($customCodeHead) {
        echo $customCodeHead;
    });
}
if ($customCodeFoot) {
    Anon_Hook::add_action('theme_foot', function () use ($customCodeFoot) {
        echo $customCodeFoot;
    });
}
