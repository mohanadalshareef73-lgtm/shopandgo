<?php


return [
    'active' => env('SYSTEM_ACTIVE', false),
    // ⚡ القيم هذي كانت تتقرا بـ env() مباشرة؛ نقرأوها من هني باش «تسريع المنظومة» (config:cache) ما يكسرهاش
    'company_name' => env('COMPANY_NAME'),
    'almadar_token' => env('ALMADAR_TOKEN'),
    'almadar_api' => env('ALMADAR_API'),
];
