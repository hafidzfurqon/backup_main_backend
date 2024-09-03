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

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => ['http://127.0.0.1:8000', 'http://localhost:5173', 'http://localhost:3032'],

    // Menggunakan REGEX untuk pola pattern localhost dengan segala macam portnya :(
    'allowed_origins_patterns' => ['^http://localhost:\d+$'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

//ibnu perubaha
// <?php

// return [

//     /*
//     |--------------------------------------------------------------------------
//     | Cross-Origin Resource Sharing (CORS) Configuration
//     |--------------------------------------------------------------------------
//     |
//     | Here you may configure your settings for cross-origin resource sharing
//     | or "CORS". This determines what cross-origin operations may execute
//     | in web browsers. You are free to adjust these settings as needed.
//     |
//     | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
//     |
//     */

//     'paths' => ['api/*', 'sanctum/csrf-cookie'],

//     'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

//     'allowed_origins' => ['http://127.0.0.1:8000'],

//     // Menggunakan REGEX untuk pola pattern localhost dengan segala macam portnya :(
//     'allowed_origins_patterns' => ['^http://localhost:\d+$'],

//     'allowed_headers' => ['*'],

//     'exposed_headers' => [],

//     'max_age' => 0,

//      // coba ubah ini ke true jika terjadi CORS. kalo masih kena CORS, i dont know :)
//     'supports_credentials' => false,

// ];
