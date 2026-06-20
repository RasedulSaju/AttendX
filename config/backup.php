<?php
declare(strict_types=1);

return [
    // Absolute path to store backup files
    'path' => env('BACKUP_PATH', storage_path('backups')),

    // Max number of backups to retain (0 = unlimited)
    'max_backups' => (int)env('BACKUP_MAX', 10),

    // Compress SQL dumps with gzip
    'compress' => true,
];
