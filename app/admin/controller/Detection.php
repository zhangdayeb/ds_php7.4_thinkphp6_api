<?php

namespace app\admin\controller;

use app\admin\ApiBaseController;
use app\common\service\DetectionService;
use think\facade\Console;

class Detection extends ApiBaseController
{
    public function autoDetection()
    {
        $channel = $this->request->get('channel', 1);
        // 定时任务可以使用：php think admin:jiqiang
        $output = Console::call('admin:domain', [$channel]);
        return $this->success(['res' => $output->fetch(), 'time' => date('Y-m-d H:i:s')]);
    }
}