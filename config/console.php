<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'admin:statistics' => \app\command\StatisticsAgentUserCommand::class,   // 初始化代理商统计数据
        'admin:domain' => \app\command\DomainCommand::class,                    // 域名检测
        'admin:today_count' => \app\command\TodayCount::class,                  // 今日数据统计 暂时放弃
        'admin:ossdo' => \app\command\OssDo::class,                  // 今日数据统计 暂时放弃
        'rabbitmq:ossdownload' => \app\command\OssDownloadConsumer::class,      // oss 文件下载
        'admin:tempfilekey' => \app\command\TempFileKeyCommand::class,          // 处理临时数据表中 OSS key问题
        'admin:compareoss' => \app\command\CompareOssCommand::class,            // 比对 OSS 文件内容
    ],
];
