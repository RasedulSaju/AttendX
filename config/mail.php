<?php
declare(strict_types=1);

return [
    'host'       => env('MAIL_HOST',       'smtp.gmail.com'),
    'port'       => (int)env('MAIL_PORT',  587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username'   => env('MAIL_USERNAME',   ''),
    'password'   => env('MAIL_PASSWORD',   ''),
    'from_name'  => env('MAIL_FROM_NAME',  'AttendX'),
    'from_email' => env('MAIL_FROM_EMAIL', ''),
];
