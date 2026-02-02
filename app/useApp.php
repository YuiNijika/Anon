<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'app' => [
        'autoRouter' => true,
        'avatar' => 'https://www.cravatar.cn/avatar',
        'cache' => [
            'enabled' => true,
            'time' => 3600,
            'exclude' => [
                '/auth/',
                '/anon/debug/',
                '/anon/install',
            ],
        ],
        'debug' => [
            'global' => true,
            'router' => true,
            'logDetailedErrors' => false,
        ],
        'token' => [
            'enabled' => true,
            'refresh' => false,
            'whitelist' => [
                '/auth/login',
                '/auth/logout',
                '/auth/check-login',
                '/auth/token',
                '/auth/captcha'
            ],
        ],
        'captcha' => [
            'enabled' => true,
        ],
        'rateLimit' => [
            'register' => [
                'ip' => [
                    'enabled' => false,
                    'maxAttempts' => 5,
                    'windowSeconds' => 3600,
                ],
                'device' => [
                    'enabled' => false,
                    'maxAttempts' => 3,
                    'windowSeconds' => 3600,
                ],
            ],
        ],
        'security' => [
            'csrf' => [
                'enabled' => true,
                'stateless' => true,
            ],
            'xss' => [
                'enabled' => true,
                'stripHtml' => true,
                'skipFields' => ['password', 'token', 'csrf_token'],
            ],
            'sql' => [
                'validateInDebug' => true,
            ],
            'password' => [
                'minLength' => 8,
                'maxLength' => 128,
                'requireUppercase' => false,
                'requireLowercase' => false,
                'requireDigit' => false,
                'requireSpecial' => false,
            ],
            'cors' => [
                'origins' => [],
            ],
        ],
        'plugins' => [
            'enabled' => false,
            'active' => [],
        ]
    ]
];
