<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    | Mengizinkan akses dari localhost serta berbagai IP jaringan lokal tempat kerja 
    | (aman untuk dibiarkan bersamaan tanpa harus dihapus salah satu).
    */
    'allowed_origins' => [
        'http://localhost:5173',
        'http://192.168.1.246:5173',
        'http://10.14.206.87:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | Diatur true agar token/cookie autentikasi dapat dikirim dan diterima dengan lancar
    */
    'supports_credentials' => true,

];