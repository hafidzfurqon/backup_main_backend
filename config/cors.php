<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for managing settings for Cross-Origin Resource Sharing (CORS).
    | You can adjust the settings below as per your application's requirements.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Allow all HTTP methods (GET, POST, PUT, DELETE, etc.)
    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    // You can leave this empty or set patterns if needed (optional)
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [''],

    // Set max age for CORS preflight request (optional)
    'max_age' => 0,

    // Whether or not to allow credentials (e.g., cookies, authorization headers)
    'supports_credentials' => true,  // Set to false if you're not sending cookies or authorization headers
];
