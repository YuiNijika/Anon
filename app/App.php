<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

return [
    'auth' => [
        'login' => [
            'view' => 'Auth/Login',
            'useLoginCheck' => false,
        ],
        'logout' => [
            'view' => 'Auth/Logout',
            'useLoginCheck' => false,
        ],
        'check-login' => [
            'view' => 'Auth/CheckLogin',
            'useLoginCheck' => false,
        ],
        'token' => [
            'view' => 'Auth/Token',
            'useLoginCheck' => true,
        ],
        'captcha' => [
            'view' => 'Auth/Captcha',
            'useLoginCheck' => false,
        ],
    ],
    'user' => [
        'info' => [
            'view' => 'User/Info',
            'useLoginCheck' => true,
        ],
    ],
];
