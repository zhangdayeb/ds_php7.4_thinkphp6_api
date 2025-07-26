<?php

namespace app\command;

use app\common\service\DetectionService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class DomainCommand extends Command
{
    protected function configure()
    {
        $this->setName('domain')->addArgument('channel', Argument::OPTIONAL, "检测通道")->setDescription('更新域名检测');
    }

    protected function execute(Input $input, Output $output)
    {
        // echo '[!]域名检测开始' . PHP_EOL;
        // 1=极强检测，2=麒麟域名检测，3=查小宝检测
        // 定时任务可以使用：php think admin:jiqiang
        $channel = $input->getArgument('channel');
        $channels = $channel ? explode(',', $channel) : [1];
        $service = new DetectionService();
        $result = $service->detection($channels);
        $output->writeln(date('Y-m-d H:i:s') . '更新域名检测完成:result:' . $result);
    }
}