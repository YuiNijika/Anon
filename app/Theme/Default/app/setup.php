<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    '基础设置' => [
        'site_title' => [
            'type' => 'text',
            'label' => '网站标题',
            'description' => '显示在网站首页的标题',
            'default' => '我的网站',
        ],
        'site_description' => [
            'type' => 'textarea',
            'label' => '网站描述',
            'default' => '',
        ],
    ],
    '外观设置' => [
        'color_scheme' => [
            'type' => 'select',
            'label' => '配色方案',
            'default' => 'light',
            'options' => ['light' => '浅色', 'dark' => '深色', 'auto' => '自动'],
        ],
        'show_sidebar' => [
            'type' => 'checkbox',
            'label' => '显示侧边栏',
            'default' => true,
        ],
    ],
];
