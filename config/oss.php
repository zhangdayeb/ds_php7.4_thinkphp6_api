<?php

return [
    'endpoint' => env('OSS_ENDPOINT', ''),
    'accessKeyId' => env('OSS_ACCESS_KEY_ID', ''),
    'accessKeySecret' => env('OSS_ACCESS_KEY_SECRET', ''),
    'bucket' => [
        'default' => env('OSS_BUCKET', ''),
        // 其他bucket配置...
    ],
    'url' => env('OSS_URL', ''), // OSS服务的URL地址
];