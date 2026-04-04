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

    'allowed_methods' => ['*'], // بيسمح بكل العمليات GET, POST, OPTIONS, إلخ

    'allowed_origins' => ['*'], // بيسمح بأي دومين يكلم السيرفر

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // دي أهم واحدة.. بتسمح بمرور الـ Token (Authorization Header)

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // مهمة جداً لو فيه Cookies أو Sessions

];
