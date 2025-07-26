<?php
declare (strict_types = 1);

namespace app\command;

use app\common\service\OssService;
use app\common\service\RabbitMQService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;

class OssDownloadConsumer extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('ossdownload')
            ->setDescription('the app\command\ossdownloadconsumer command');
    }

    protected function execute(Input $input, Output $output)
    {
        $redis = Cache::store('redis');
        $task_run_time_run = $redis->get('task_run_time_run','0');
        $redis->inc('task_run_time_run');
        $output->writeln('第'.$task_run_time_run.'次启动');

        // 启动成功提示
        $output->writeln('下载消息队列消费者模式，启动监听！');
        // 读取配置文件
        $config = config('rabbitmq.rabbitmq');
        // 启动消息队列服务
        $rabbitMQService = new RabbitMQService($config);
        // 启动oss服务
        $service = new OssService();

        // 回调函数 暂不执行 进行回调执行
        $callback = function ($envelope) use ($output, $service, $rabbitMQService) {
            $message = $envelope->getBody();
            echo '[!]running task:' . $message . '  时间：' . date('Y-m-d H:i:s') . PHP_EOL;
            $service->actionOssDownloadTask($message);
            // $deliveryTag = $envelope->get('delivery_tag');
            // $rabbitMQService->channel->basic_ack($deliveryTag);
            $output->writeln(PHP_EOL . "Received message: $message" . PHP_EOL . PHP_EOL);
        };

        // 进入消费队列  本质上 这个 哥们就是一个 worker 
        $rabbitMQService->consume('oss_download', $config['queue'], $callback);
        // 执行运行
        $output->writeln('当你看见这个消息的时候！消息队列的监听任务已经关闭了！需要重新启动');
    }
}
