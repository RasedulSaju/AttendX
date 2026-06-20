<?php
declare(strict_types=1);

return [
    'name'     => env('APP_NAME',  'AttendX'),
    'env'      => env('APP_ENV',   'production'),
    'debug'    => (bool)env('APP_DEBUG', false),
    'url'      => env('APP_URL',   'http://localhost'),
    'timezone' => env('APP_TZ',    'Asia/Dhaka'),
    'locale'   => env('APP_LOCALE','en'),
    'key'      => env('APP_KEY',   ''),          // 32-char random key for encryption

    'session' => [
        'name'     => env('SESSION_NAME',     'attendx_session'),
        'lifetime' => (int)env('SESSION_LIFETIME', 7200),  // 2 hours
        'path'     => '/',
        'secure'   => (bool)env('SESSION_SECURE', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    'pagination' => [
        'per_page' => 20,
    ],

    'upload' => [
        'max_size'        => 5 * 1024 * 1024,   // 5 MB
        'allowed_images'  => ['jpg','jpeg','png','webp'],
        'allowed_docs'    => ['pdf','doc','docx','xls','xlsx'],
        'path'            => 'storage/uploads',
    ],
];
