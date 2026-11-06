<?php

// Manager Library settings. Every secret comes from the environment (constitution, article IX).
return [
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_ttl' => 28800, // 8 hours (CA-01.1)

    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),

    'covers_dir' => env('COVERS_DIR', storage_path('app/covers')),
    'cover_max_bytes' => 2 * 1024 * 1024, // 2 MB (CA-03.4)

    'cors_origin' => env('CORS_ORIGIN', 'http://localhost:5173'),
];
