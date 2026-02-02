<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    '基础设置' => [
        'color_scheme' => [
            'type' => 'select',
            'label' => '配色方案',
            'default' => 'dark',
            'options' => [
                'dark' => '深色',
                'light' => '浅色'
            ]
        ],
    ],
];
