<?php


namespace app\command;

use app\common\service\VideoTempService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\service\OssService;
use think\facade\Cache;

/**
 * 执行一些oss 测试任务
 */
class OssDo extends Command
{
    protected function configure()
    {
        $this->setName('ossdo')->setDescription('Here is the ossdo');
    }

    protected function execute(Input $input, Output $output)
    {
        // 创建新的 任务列表
        // $this->startNewTask();
        // 重新下载任务列表
        // $this->reStartTask();
        // 重新 梳理 临时表 跟 视频表
        // $this->reNewVideo();
    }

    /**
     * 启动对比 任务
     * @return void
     */
    protected function startNewTask(){
        $ossServer = new OssService();
        // 执行对比
        dd($ossServer->compareOss(1));
    }

    /**
     * 重启已经排队的消息队列 重新推送一下 方便消费者 获取
     * @return void
     */
    protected function reStartTask(){
        $ossServer = new OssService();

        // 缓存
        $redis = Cache::store('redis');
        $task_run_time_push_start = $redis->get('task_run_time_push_start','0');
        $task_run_time_run = $redis->get('task_run_time_run','0');

        // 如果挂了 就重新 推一下
        if($task_run_time_push_start < $task_run_time_run){
            // 消费者 重新启动了一次 完成未完成的队列
            $ossServer->reStartTaskWhenDownFail();
            $redis->set('task_run_time_push_start',$task_run_time_run);
            echo "重新发送给消费者 第".$task_run_time_run.'次数' . PHP_EOL;
        }else{
            echo "消费者生存良好 没有启动的必要了" . PHP_EOL;
        }
    }

    /**
     * 临时维护任务
     * @return void
     */
    protected function reNewVideo(){
        $videTemp = new VideoTempService();
        $videTemp->reNewVieo();
    }
// 类结束了    
}