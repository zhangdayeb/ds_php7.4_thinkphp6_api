<?php

namespace app\common\service;

use app\common\model\Task;
use Exception;

class TaskService
{
    public function getTaskList($adminId)
    {
        $fields = 'id,status,task_sn,remark,if(total = 0, 0, round(ack / total, 2) * 100) as process,back_time,content';
        return Task::field($fields)->order('id', 'desc')->select()->toArray();
    }

    public function reBuildTask($taskId)
    {
        $task = Task::where('id', $taskId)->find();
        $taskStatus = $task['status'];
        if ($taskStatus != 'fail') {
            throw new Exception('当前任务不是失败状态，不能重试');
        }
        $content = $task['content'];
        $contentList = explode(',', $content);
        foreach ($contentList as $item) {
            $path = root_path() . 'public/storage/videotemp/' . str_replace('#', '_', $item);
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        // 投递消息
        $rabbitConf = config('rabbitmq.rabbitmq');
        $rabbitMQService = new RabbitMQService($rabbitConf);
        $rabbitMQService->publish('oss_download', $rabbitConf['queue'], $taskId);

        return [
            'status' => $taskStatus,
            'message' => '任务已重新投递',
            'task_id' => $taskId
        ];
    }
}