<?php
return [
    // ... 其他数据库配置
    'rabbitmq' => [
        'host'     => env('rabbitmq.host', 'localhost'),
        'port'     => 5672,
        'username' => env('rabbitmq.username', 'guest'),
        'password' => env('rabbitmq.password', 'guest'),
        'vhost'    => env('rabbitmq.vhost', '/'),
        'queue'    => env('rabbitmq.queue', 'oss_queue'), // 队列名称
    ],
];