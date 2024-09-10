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

    // Allow requests from the following origins (add any other origins as needed)
    'allowed_origins' => [
        'http://localhost:3032',  // Your primary frontend URL
        'http://localhost:3033',  // Additional local origins if needed
        'http://localhost:5173',
        'http://localhost:5174',
        'http://127.0.0.1:3032',
        'http://127.0.0.1:8000'
    ],

    // You can leave this empty or set patterns if needed (optional)
    'allowed_origins_patterns' => [],

    // Allow all common headers, including those for file uploads (e.g., Authorization, CSRF tokens)
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'Origin', 'Cache-Control', 'X-CSRF-Token', '*'],

    // Expose specific headers to the client (if needed). Usually, this can be empty.
    'exposed_headers' => [],

    // Set max age for CORS preflight request (optional)
    'max_age' => 0,

    // Whether or not to allow credentials (e.g., cookies, authorization headers)
    'supports_credentials' => true,  // Set to false if you're not sending cookies or authorization headers
];
